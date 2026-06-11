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

    protected static string $relationship = 'inventoryItems';

    protected static ?string $title = 'Items In This Location';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'assignedToUser',
                'category',
                'department',
            ]))
            ->columns([
                static::compactTextColumn(TextColumn::make('asset_tag'), 24)
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('name'), 32)
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('category.name'), 28)
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
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('assignedToUser.name'), 28)
                    ->label('Assigned To')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('department.name'), 28)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty_expires_at')
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
