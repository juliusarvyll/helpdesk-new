<?php

namespace App\Filament\Resources\PreventiveMaintenanceSessions;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\PreventiveMaintenanceSessions\Pages\CreatePreventiveMaintenanceSession;
use App\Filament\Resources\PreventiveMaintenanceSessions\Pages\EditPreventiveMaintenanceSession;
use App\Filament\Resources\PreventiveMaintenanceSessions\Pages\ListPreventiveMaintenanceSessions;
use App\Filament\Resources\PreventiveMaintenanceSessions\Pages\ViewPreventiveMaintenanceSession;
use App\Filament\Resources\PreventiveMaintenanceSessions\RelationManagers\AssetChecksRelationManager;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceSession;
use App\PreventiveMaintenanceAssetCheckStatus;
use App\PreventiveMaintenanceSessionStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreventiveMaintenanceSessionResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = PreventiveMaintenanceSession::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'PMS Sessions';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('PMS Session')->schema([
                Select::make('location_id')->label('Location')
                    ->relationship('location', 'name', fn (Builder $query): Builder => $query->where('department_id', Filament::getTenant()?->id)->where('is_deleted', false))
                    ->required()->searchable()->preload()->disabledOn('edit'),
                DateTimePicker::make('started_at')->label('Start Date')->default(now())->disabledOn('create'),
                Select::make('checklist_template_id')->label('Checklist Template')
                    ->options(fn (): array => PmsChecklistTemplate::query()->where('is_active', true)->whereHas('items')->orderBy('name')->pluck('name', 'id')->all())
                    ->required()->searchable()->visibleOn('create'),
                Select::make('status')->options([
                    PreventiveMaintenanceSessionStatus::Active->value => 'Active',
                    PreventiveMaintenanceSessionStatus::Completed->value => 'Completed',
                    PreventiveMaintenanceSessionStatus::Cancelled->value => 'Cancelled',
                ])->disabled(),
                Textarea::make('remarks')->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('PMS Session')->schema([
                TextEntry::make('location.name')->label('Location'),
                TextEntry::make('starter.name')->label('Started By'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (PreventiveMaintenanceSessionStatus $state): string => str($state->value)->headline()->toString())->color(fn (PreventiveMaintenanceSessionStatus $state): string => match ($state) {
                    PreventiveMaintenanceSessionStatus::Active => 'warning', PreventiveMaintenanceSessionStatus::Completed => 'success', PreventiveMaintenanceSessionStatus::Cancelled => 'danger'
                }),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime(),
                TextEntry::make('remarks')->columnSpanFull()->placeholder('No remarks'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('location.name'), 32)->label('Location')->searchable()->sortable(),
                static::compactTextColumn(TextColumn::make('starter.name'), 28)->label('Started By'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PreventiveMaintenanceSessionStatus $state): string => str($state->value)->headline()->toString())->color(fn (PreventiveMaintenanceSessionStatus $state): string => match ($state) {
                    PreventiveMaintenanceSessionStatus::Active => 'warning', PreventiveMaintenanceSessionStatus::Completed => 'success', PreventiveMaintenanceSessionStatus::Cancelled => 'danger'
                }),
                TextColumn::make('asset_checks_count')->label('Total Assets'),
                TextColumn::make('passed_count')->label('Passed')->color('success'),
                TextColumn::make('failed_count')->label('Failed')->color('danger'),
                TextColumn::make('needs_repair_count')->label('Needs Repair')->color('warning'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['active' => 'Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled'])])
            ->recordActions([
                Action::make('complete')->label('Complete Session')->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (PreventiveMaintenanceSession $record): bool => $record->status === PreventiveMaintenanceSessionStatus::Active && ! $record->assetChecks()->whereIn('status', ['pending', 'in_progress'])->exists())
                    ->requiresConfirmation()->action(fn (PreventiveMaintenanceSession $record): bool => $record->update(['status' => PreventiveMaintenanceSessionStatus::Completed, 'completed_at' => now()])),
                Action::make('cancel')->label('Cancel Session')->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (PreventiveMaintenanceSession $record): bool => $record->status === PreventiveMaintenanceSessionStatus::Active)
                    ->requiresConfirmation()->action(fn (PreventiveMaintenanceSession $record): bool => $record->update(['status' => PreventiveMaintenanceSessionStatus::Cancelled, 'completed_at' => now()])),
                ViewAction::make()->label('View Session'),
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [AssetChecksRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventiveMaintenanceSessions::route('/'),
            'create' => CreatePreventiveMaintenanceSession::route('/create'),
            'view' => ViewPreventiveMaintenanceSession::route('/{record}'),
            'edit' => EditPreventiveMaintenanceSession::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['location', 'starter'])
            ->withCount([
                'assetChecks',
                'assetChecks as passed_count' => fn (Builder $query): Builder => $query->where('status', PreventiveMaintenanceAssetCheckStatus::Passed->value),
                'assetChecks as failed_count' => fn (Builder $query): Builder => $query->where('status', PreventiveMaintenanceAssetCheckStatus::Failed->value),
                'assetChecks as needs_repair_count' => fn (Builder $query): Builder => $query->where('status', PreventiveMaintenanceAssetCheckStatus::NeedsRepair->value),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }
}
