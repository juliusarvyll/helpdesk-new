<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers\InventoryItemsRelationManager;
use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use App\Models\Location;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceSchedule;
use App\PmsInspectionService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = Location::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('name'), 32)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('inventory_item_serial_numbers_count')
                    ->counts('inventoryItemSerialNumbers')
                    ->label('Serials')
                    ->sortable(),
                TextColumn::make('total_it_assets')
                    ->label('Total IT Assets')
                    ->state(fn (Location $record): int => $record->itAssetSerialNumbers()->count()),
                TextColumn::make('last_pms_date')
                    ->label('Last PMS Date')
                    ->state(fn (Location $record) => PreventiveMaintenanceAssetCheck::query()->whereHas('serialNumber', fn ($query) => $query->where('location_id', $record->id))->max('completed_at'))
                    ->dateTime(),
                TextColumn::make('pending_pms_count')
                    ->label('Pending PMS')
                    ->state(fn (Location $record): int => $record->itAssetSerialNumbers()->whereDoesntHave('preventiveMaintenanceAssetChecks')->count()),
                TextColumn::make('overdue_pms_count')
                    ->label('Overdue PMS')
                    ->state(fn (Location $record): int => PreventiveMaintenanceSchedule::query()->where('is_active', true)->where('next_due_at', '<', now())->whereHas('inventoryItemSerialNumber', fn ($query) => $query->where('location_id', $record->id))->count()),
                static::compactTextColumn(TextColumn::make('description'), 44)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->actions([
                Action::make('startPmsSession')
                    ->label('Start PMS Session')
                    ->icon('heroicon-o-play')
                    ->visible(fn (): bool => auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false)
                    ->form([
                        Select::make('checklist_template_id')
                            ->label('Checklist Template')
                            ->options(fn (): array => PmsChecklistTemplate::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (Location $record, array $data) {
                        $session = app(PmsInspectionService::class)->startSession($record, auth()->user(), PmsChecklistTemplate::query()->findOrFail($data['checklist_template_id']));

                        return redirect(PreventiveMaintenanceSessionResource::getUrl('view', ['record' => $session]));
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            TextEntry::make('name'),
            TextEntry::make('department.name')
                ->label('Department'),
            TextEntry::make('description')
                ->placeholder('No description')
                ->columnSpanFull(),
            TextEntry::make('inventory_item_serial_numbers_count')
                ->label('Inventory Serials')
                ->state(fn (Location $record): int => $record->inventoryItemSerialNumbers()->count()),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            InventoryItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocations::route('/'),
            'create' => Pages\CreateLocation::route('/create'),
            'view' => Pages\ViewLocation::route('/{record}'),
            'edit' => Pages\EditLocation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_deleted', false)
            ->withCount('inventoryItemSerialNumbers');
    }
}
