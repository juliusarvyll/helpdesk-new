<?php

namespace App\Filament\Resources\PreventiveMaintenanceAssetChecks;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\PreventiveMaintenanceAssetChecks\Pages\ListPreventiveMaintenanceAssetChecks;
use App\Filament\Resources\PreventiveMaintenanceAssetChecks\Pages\ViewPreventiveMaintenanceAssetCheck;
use App\Filament\Resources\TicketResource;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\PreventiveMaintenanceAssetCheckStatus;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PreventiveMaintenanceAssetCheckResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = PreventiveMaintenanceAssetCheck::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Asset Inspection')->schema([
                TextEntry::make('session.location.name')->label('Location'),
                TextEntry::make('inventoryItem.name')->label('Asset'),
                TextEntry::make('inventoryItem.asset_tag')->label('Asset Tag'),
                TextEntry::make('serialNumber.serial_number')->label('Serial Number'),
                TextEntry::make('checklistTemplate.name')->label('Checklist Template'),
                TextEntry::make('inspector.name')->label('Inspector'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->label())->color(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->color()),
                TextEntry::make('completed_at')->dateTime(),
                TextEntry::make('remarks')->columnSpanFull()->placeholder('No remarks'),
                TextEntry::make('ticket.id')->label('Repair Ticket')->url(fn (PreventiveMaintenanceAssetCheck $record): ?string => $record->ticket ? TicketResource::getUrl('view', ['record' => $record->ticket]) : null)->placeholder('None'),
            ])->columns(2),
            Section::make('Checklist Results')->schema([
                RepeatableEntry::make('results')->hiddenLabel()->schema([
                    TextEntry::make('checklistItem.label')->label('Checklist Item'),
                    TextEntry::make('value')->label('Result'),
                    TextEntry::make('remarks')->placeholder('No remarks'),
                ])->columns(3),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('session.location.name'), 30)->label('Location'),
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 30)->label('Asset'),
                static::compactTextColumn(TextColumn::make('serialNumber.serial_number'), 24)->label('Serial Number'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->label())->color(fn (PreventiveMaintenanceAssetCheckStatus $state): string => $state->color()),
                static::compactTextColumn(TextColumn::make('inspector.name'), 28)->label('Inspector'),
                TextColumn::make('completed_at')->dateTime()->sortable(),
            ])
            ->defaultSort('completed_at', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('session', fn (Builder $query): Builder => $query->where('department_id', filament()->getTenant()?->getKey()))
            ->with(['session.location', 'inventoryItem', 'serialNumber', 'checklistTemplate', 'inspector', 'ticket', 'results.checklistItem']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventiveMaintenanceAssetChecks::route('/'),
            'view' => ViewPreventiveMaintenanceAssetCheck::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
