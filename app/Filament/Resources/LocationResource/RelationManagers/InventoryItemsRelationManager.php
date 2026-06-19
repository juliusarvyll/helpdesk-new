<?php

namespace App\Filament\Resources\LocationResource\RelationManagers;

use App\Filament\Concerns\HasCompactTableColumns;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoryItemsRelationManager extends RelationManager
{
    use HasCompactTableColumns;

    protected static string $relationship = 'inventoryItemSerialNumbers';

    protected static ?string $title = 'Serial Numbers In This Location';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'inventoryItem.assignedToUser',
                'inventoryItem.category',
                'inventoryItem.department',
                'assignedToUser',
            ]))
            ->columns([
                static::compactTextColumn(TextColumn::make('serial_number'), 28)
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('inventoryItem.asset_tag'), 24)
                    ->label('Asset Tag')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 32)
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('inventoryItem.category.name'), 28)
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'assigned' => 'info',
                        'in_repair' => 'warning',
                        'retired', 'lost', 'disposed' => 'danger',
                    }),
                static::compactTextColumn(TextColumn::make('assignedToUser.name'), 28)
                    ->label('Assigned To')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('inventoryItem.department.name'), 28)
                    ->label('Department')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inventoryItem.warranty_expires_at')
                    ->label('Warranty Expires')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'assigned' => 'Assigned',
                        'in_repair' => 'In Repair',
                        'retired' => 'Retired',
                        'lost' => 'Lost',
                        'disposed' => 'Disposed',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
