<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\InventoryItemResource\Pages;
use App\Filament\Resources\InventoryItemResource\RelationManagers\SerialNumbersRelationManager;
use App\InventoryMovementService;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class InventoryItemResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = InventoryItem::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Inventory';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('inventory_category_id')
                ->relationship(
                    'category',
                    'name',
                    fn ($query) => $query
                        ->where('is_deleted', false)
                        ->where('department_id', Filament::getTenant()?->id)
                )
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('asset_tag')
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            Textarea::make('description')
                ->columnSpanFull(),
            Select::make('status')
                ->options([
                    'available' => 'Available',
                    'assigned' => 'Assigned',
                    'in_repair' => 'In Repair',
                    'retired' => 'Retired',
                    'lost' => 'Lost',
                    'disposed' => 'Disposed',
                ])
                ->required()
                ->default('available')
                ->hiddenOn('edit'),
            TextInput::make('quantity')
                ->required()
                ->numeric()
                ->default(1)
                ->minValue(0)
                ->hiddenOn('edit'),
            TextInput::make('unit')
                ->maxLength(255),
            Select::make('location_id')
                ->label('Location')
                ->relationship(
                    'location',
                    'name',
                    fn ($query) => $query
                        ->where('is_deleted', false)
                        ->where('department_id', Filament::getTenant()?->id)
                )
                ->searchable()
                ->preload()
                ->createOptionForm([
                    TextInput::make('name')->required(),
                    Textarea::make('description'),
                ])
                ->createOptionUsing(fn (array $data): int => Location::create([
                    ...$data,
                    'department_id' => Filament::getTenant()?->id,
                    'is_deleted' => false,
                ])->id)
                ->hiddenOn('edit'),
            Select::make('assigned_to_user_id')
                ->relationship(
                    'assignedToUser',
                    'name',
                    fn ($query) => $query
                        ->where('status', 1)
                        ->where('is_deleted', 0)
                        ->where(function ($query): void {
                            $query
                                ->where('department_id', Filament::getTenant()?->id)
                                ->orWhereHas('departments', fn ($query) => $query->whereKey(Filament::getTenant()?->id));
                        })
                )
                ->searchable()
                ->preload()
                ->hidden(),
            KeyValue::make('metadata')
                ->formatStateUsing(fn (?array $state): ?array => static::metadataForKeyValue($state))
                ->dehydrateStateUsing(fn (?array $state): ?array => static::metadataForKeyValue($state))
                ->columnSpanFull(),
            DatePicker::make('purchased_at'),
            DatePicker::make('warranty_expires_at'),
            Repeater::make('serialNumbers')
                ->relationship('serialNumbers')
                ->schema([
                    TextInput::make('serial_number')
                        ->label('Serial Number')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Select::make('location_id')
                        ->label('Location')
                        ->relationship(
                            'location',
                            'name',
                            fn ($query) => $query
                                ->where('is_deleted', false)
                                ->where('department_id', Filament::getTenant()?->id)
                        )
                        ->searchable()
                        ->preload(),
                    Select::make('status')
                        ->options([
                            'available' => 'Available',
                            'assigned' => 'Assigned',
                            'in_repair' => 'In Repair',
                            'retired' => 'Retired',
                            'lost' => 'Lost',
                            'disposed' => 'Disposed',
                        ])
                        ->required()
                        ->default('available'),
                    Select::make('assigned_to_user_id')
                        ->label('Assigned To')
                        ->relationship(
                            'assignedToUser',
                            'name',
                            fn ($query) => $query
                                ->where('status', 1)
                                ->where('is_deleted', 0)
                                ->where(function ($query): void {
                                    $query
                                        ->where('department_id', Filament::getTenant()?->id)
                                        ->orWhereHas('departments', fn ($query) => $query->whereKey(Filament::getTenant()?->id));
                                })
                        )
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['serial_number'] ?? null)
                ->hiddenOn('create'),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            SerialNumbersRelationManager::class,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, string|null>|null
     */
    public static function metadataForKeyValue(?array $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }

        return collect($metadata)
            ->mapWithKeys(fn (mixed $value, string|int $key): array => [(string) $key => static::metadataValueForKeyValue($value)])
            ->all();
    }

    private static function metadataValueForKeyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                static::compactTextColumn(TextColumn::make('location.name'), 28)
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                static::compactTextColumn(TextColumn::make('assignedToUser.name'), 28)
                    ->label('Assigned To')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('department.name'), 28)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('warranty_expires_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
                SelectFilter::make('inventory_category_id')
                    ->relationship(
                        'category',
                        'name',
                        fn ($query) => $query->where('department_id', Filament::getTenant()?->id)
                    )
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && (auth()->user()?->can('assign', $record) ?? false))
                    ->form([
                        Select::make('assigned_to_user_id')
                            ->label('Assigned To')
                            ->options(fn () => User::query()
                                ->where('status', 1)
                                ->where('is_deleted', 0)
                                ->where(function ($query): void {
                                    $query
                                        ->where('department_id', Filament::getTenant()?->id)
                                        ->orWhereHas('departments', fn ($query) => $query->whereKey(Filament::getTenant()?->id));
                                })
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Select::make('ticket_id')
                            ->label('Related Ticket')
                            ->options(fn () => Ticket::query()
                                ->where('department_id', Filament::getTenant()?->id)
                                ->latest()
                                ->limit(50)
                                ->pluck('subject', 'id'))
                            ->searchable(),
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->assign(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            assignedToUser: User::findOrFail($data['assigned_to_user_id']),
                            ticketId: $data['ticket_id'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory item assigned.'),
                Action::make('return')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && ($record->status === 'assigned') && (auth()->user()?->can('assign', $record) ?? false))
                    ->form([
                        Select::make('ticket_id')
                            ->label('Related Ticket')
                            ->options(fn () => Ticket::query()
                                ->where('department_id', Filament::getTenant()?->id)
                                ->latest()
                                ->limit(50)
                                ->pluck('subject', 'id'))
                            ->searchable(),
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->return(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            ticketId: $data['ticket_id'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory item returned.'),
                Action::make('consume')
                    ->icon('heroicon-o-minus-circle')
                    ->color('warning')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && ($record->quantity > 0) && (auth()->user()?->can('adjustStock', $record) ?? false))
                    ->form([
                        TextInput::make('quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1),
                        Select::make('ticket_id')
                            ->label('Related Ticket')
                            ->options(fn () => Ticket::query()
                                ->where('department_id', Filament::getTenant()?->id)
                                ->latest()
                                ->limit(50)
                                ->pluck('subject', 'id'))
                            ->searchable(),
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->consume(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            quantity: (int) $data['quantity'],
                            ticketId: $data['ticket_id'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory stock consumed.'),
                Action::make('transfer')
                    ->icon('heroicon-o-map-pin')
                    ->color('primary')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && (auth()->user()?->can('assign', $record) ?? false))
                    ->form([
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => Location::query()
                                ->where('is_deleted', false)
                                ->where('department_id', Filament::getTenant()?->id)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable(),
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->transfer(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            locationId: $data['location_id'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory item transferred.'),
                Action::make('repair')
                    ->label('Mark In Repair')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && (auth()->user()?->can('update', $record) ?? false))
                    ->form([
                        Select::make('ticket_id')
                            ->label('Related Ticket')
                            ->options(fn () => Ticket::query()
                                ->where('department_id', Filament::getTenant()?->id)
                                ->latest()
                                ->limit(50)
                                ->pluck('subject', 'id'))
                            ->searchable(),
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->repair(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            ticketId: $data['ticket_id'] ?? null,
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory item marked in repair.'),
                Action::make('retire')
                    ->icon('heroicon-o-archive-box-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && (auth()->user()?->can('retire', $record) ?? false))
                    ->form([
                        Textarea::make('notes'),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->retire(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory item retired.'),
                Action::make('adjustStock')
                    ->label('Adjust Stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (InventoryItem $record): bool => ! static::hasSerialNumbers($record) && (auth()->user()?->can('adjustStock', $record) ?? false))
                    ->form([
                        TextInput::make('quantity')
                            ->label('New Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('notes')
                            ->required(),
                    ])
                    ->action(function (InventoryItem $record, array $data): void {
                        app(InventoryMovementService::class)->adjust(
                            inventoryItem: $record,
                            actor: auth()->user(),
                            newQuantity: (int) $data['quantity'],
                            notes: $data['notes'] ?? null,
                        );
                    })
                    ->successNotificationTitle('Inventory stock adjusted.'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-trash')
                        ->visible(fn (): bool => auth()->user()?->can('delete_inventory::item') ?? false)
                        ->action(fn (Collection $records): int => $records->toQuery()->update(['is_deleted' => true])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventoryItems::route('/'),
            'create' => Pages\CreateInventoryItem::route('/create'),
            'view' => Pages\ViewInventoryItem::route('/{record}'),
            'edit' => Pages\EditInventoryItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_deleted', false)
            ->with([
                'assignedToUser',
                'category',
                'department',
                'location',
            ])
            ->withExists('serialNumbers');
    }

    public static function hasSerialNumbers(InventoryItem $record): bool
    {
        if (array_key_exists('serial_numbers_exists', $record->getAttributes())) {
            return (bool) $record->serial_numbers_exists;
        }

        return $record->serialNumbers()->exists();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('viewAny', InventoryItem::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', InventoryItem::class) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
