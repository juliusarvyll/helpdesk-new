<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\LocationResource\Pages;
use App\Filament\Resources\LocationResource\RelationManagers\InventoryItemsRelationManager;
use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
