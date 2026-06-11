<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\InventoryTransactionResource\Pages;
use App\Models\InventoryTransaction;
use Filament\Facades\Filament;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransactionResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = InventoryTransaction::class;

    protected static bool $isScopedToTenant = false;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('inventory_item_id')
                ->relationship(
                    'inventoryItem',
                    'name',
                    fn ($query) => $query
                        ->where('is_deleted', false)
                        ->where('department_id', Filament::getTenant()?->id)
                )
                ->required()
                ->searchable()
                ->preload(),
            Select::make('ticket_id')
                ->relationship('ticket', 'id')
                ->searchable()
                ->preload(),
            Select::make('assigned_to_user_id')
                ->relationship('assignedToUser', 'name', fn ($query) => $query->where('status', 1)->where('is_deleted', 0))
                ->searchable()
                ->preload(),
            Select::make('type')
                ->options([
                    'created' => 'Created',
                    'assigned' => 'Assigned',
                    'returned' => 'Returned',
                    'consumed' => 'Consumed',
                    'transferred' => 'Transferred',
                    'repaired' => 'Repaired',
                    'retired' => 'Retired',
                    'adjusted' => 'Adjusted',
                ])
                ->required(),
            TextInput::make('quantity')
                ->required()
                ->numeric()
                ->default(1)
                ->minValue(1),
            TextInput::make('from_status')
                ->maxLength(255),
            TextInput::make('to_status')
                ->maxLength(255),
            Textarea::make('notes')
                ->columnSpanFull(),
            KeyValue::make('metadata')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 32)
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'assigned' => 'info',
                        'returned' => 'primary',
                        'consumed' => 'warning',
                        'transferred' => 'gray',
                        'repaired' => 'success',
                        'retired', 'adjusted' => 'danger',
                    }),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('user.name'), 28)
                    ->label('Performed By')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('assignedToUser.name'), 28)
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ticket.id')
                    ->label('Ticket')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_status')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('to_status')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'created' => 'Created',
                        'assigned' => 'Assigned',
                        'returned' => 'Returned',
                        'consumed' => 'Consumed',
                        'transferred' => 'Transferred',
                        'repaired' => 'Repaired',
                        'retired' => 'Retired',
                        'adjusted' => 'Adjusted',
                    ]),
                SelectFilter::make('inventory_item_id')
                    ->relationship(
                        'inventoryItem',
                        'name',
                        fn ($query) => $query->where('department_id', Filament::getTenant()?->id)
                    )
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'assignedToUser',
                'inventoryItem',
                'ticket',
                'user',
            ])
            ->whereHas(
                'inventoryItem',
                fn ($query) => $query->where('department_id', Filament::getTenant()?->id)
            );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view_any_inventory::transaction') ?? false;
    }
}
