<?php

namespace App\Filament\Resources\InventoryItemResource\Pages\Concerns;

use App\AssetWorkOrderCreationService;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\TicketResource;
use App\InventoryMovementService;
use App\InventoryTicketDefaults;
use App\Models\InventoryItemSerialNumber;
use App\Models\InventoryTransaction;
use App\Models\JobOrder;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;

trait HasInventoryItemActions
{
    /**
     * @return array<int, Action>
     */
    protected function inventoryItemActions(): array
    {
        return [
            Action::make('createWorkRequest')
                ->label('Create Work Request')
                ->icon('heroicon-o-ticket')
                ->color('success')
                ->visible(fn (): bool => $this->record->serialNumbers()->exists() && (auth()->user()?->can($this->record->is_it_asset ? 'create_ticket' : 'create_job_order') ?? false))
                ->form([
                    TextInput::make('subject')
                        ->required()
                        ->maxLength(191)
                        ->default(fn (): string => app(InventoryTicketDefaults::class)->subject(
                            $this->record,
                            app(InventoryTicketDefaults::class)->serialNumberId($this->record),
                        )),
                    Textarea::make('description')
                        ->default(fn (): string => app(InventoryTicketDefaults::class)->description(
                            $this->record,
                            app(InventoryTicketDefaults::class)->serialNumberId($this->record),
                        ))
                        ->required()
                        ->columnSpanFull(),
                    Select::make('inventory_item_serial_number_id')
                        ->options(fn () => $this->record->serialNumbers()
                            ->orderBy('serial_number')
                            ->pluck('serial_number', 'id'))
                        ->default(fn (): ?int => app(InventoryTicketDefaults::class)->serialNumberId($this->record))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $defaults = app(InventoryTicketDefaults::class);
                            $serialNumberId = filled($state) ? (int) $state : null;

                            $set('subject', $defaults->subject($this->record, $serialNumberId));
                            $set('description', $defaults->description($this->record, $serialNumberId));
                        })
                        ->searchable()
                        ->required(),
                    Textarea::make('client_comments')
                        ->label(fn (): string => TicketResource::isClient() ? 'Comment' : 'Client Comments')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $work = app(AssetWorkOrderCreationService::class)->create($this->record, [
                        ...$data,
                        'priority' => 'normal',
                        'client_id' => auth()->id(),
                    ], auth()->user());

                    $this->redirect($work instanceof JobOrder
                        ? JobOrderResource::getUrl('view', ['record' => $work])
                        : TicketResource::getUrl('view', ['record' => $work]));
                }),
            Action::make('assign')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->visible(fn (): bool => auth()->user()?->can('assign', $this->record) ?? false)
                ->form([
                    $this->serialNumberSelect(['available']),
                    Select::make('assigned_to_user_id')
                        ->label('Assigned To')
                        ->options(fn () => User::query()
                            ->where('status', 1)
                            ->where('is_deleted', 0)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->required()
                        ->searchable(),
                    Select::make('ticket_id')
                        ->label('Related Ticket')
                        ->options(fn () => Ticket::query()
                            ->latest()
                            ->limit(50)
                            ->pluck('subject', 'id'))
                        ->searchable(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    if ($this->hasSerialNumbers()) {
                        $serialNumber = $this->serialNumberFromActionData($data);
                        $fromStatus = $this->record->status;

                        $serialNumber->update([
                            'status' => 'assigned',
                            'assigned_to_user_id' => $data['assigned_to_user_id'],
                        ]);

                        $this->record->refresh();
                        $this->recordInventoryTransaction('assigned', $fromStatus, $data['ticket_id'] ?? null, $data['notes'] ?? null, [
                            'inventory_item_serial_number_id' => $serialNumber->id,
                        ]);
                        $this->refreshInventoryItemRecord();

                        return;
                    }

                    app(InventoryMovementService::class)->assign(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        assignedToUser: User::findOrFail($data['assigned_to_user_id']),
                        ticketId: $data['ticket_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item assigned.'),
            Action::make('return')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => ($this->record->status === 'assigned' || $this->record->serialNumbers()->where('status', 'assigned')->exists()) && (auth()->user()?->can('assign', $this->record) ?? false))
                ->form([
                    $this->serialNumberSelect(['assigned']),
                    Select::make('ticket_id')
                        ->label('Related Ticket')
                        ->options(fn () => Ticket::query()
                            ->latest()
                            ->limit(50)
                            ->pluck('subject', 'id'))
                        ->searchable(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    if ($this->hasSerialNumbers()) {
                        $serialNumber = $this->serialNumberFromActionData($data);
                        $fromStatus = $this->record->status;

                        $serialNumber->update([
                            'status' => 'available',
                            'assigned_to_user_id' => null,
                        ]);

                        $this->record->refresh();
                        $this->recordInventoryTransaction('returned', $fromStatus, $data['ticket_id'] ?? null, $data['notes'] ?? null, [
                            'inventory_item_serial_number_id' => $serialNumber->id,
                        ]);
                        $this->refreshInventoryItemRecord();

                        return;
                    }

                    app(InventoryMovementService::class)->return(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        ticketId: $data['ticket_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item returned.'),
            Action::make('consume')
                ->icon('heroicon-o-minus-circle')
                ->color('warning')
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && ($this->record->quantity > 0) && (auth()->user()?->can('adjustStock', $this->record) ?? false))
                ->form([
                    TextInput::make('quantity')
                        ->required()
                        ->numeric()
                        ->minValue(1),
                    Select::make('ticket_id')
                        ->label('Related Ticket')
                        ->options(fn () => Ticket::query()
                            ->latest()
                            ->limit(50)
                            ->pluck('subject', 'id'))
                        ->searchable(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    app(InventoryMovementService::class)->consume(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        quantity: (int) $data['quantity'],
                        ticketId: $data['ticket_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory stock consumed.'),
            Action::make('transfer')
                ->icon('heroicon-o-map-pin')
                ->color('primary')
                ->visible(fn (): bool => $this->hasSerialNumbers() && (auth()->user()?->can('assign', $this->record) ?? false))
                ->form([
                    $this->serialNumberSelect(),
                    Select::make('location_id')
                        ->label('Location')
                        ->options(fn () => Location::query()
                            ->where('is_deleted', false)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    $serialNumber = $this->serialNumberFromActionData($data);
                    $fromStatus = $this->record->status;

                    $serialNumber->update([
                        'location_id' => $data['location_id'] ?? null,
                    ]);

                    $this->record->refresh();
                    $this->recordInventoryTransaction('transferred', $fromStatus, null, $data['notes'] ?? null, [
                        'inventory_item_serial_number_id' => $serialNumber->id,
                        'location_id' => $data['location_id'] ?? null,
                    ]);
                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item transferred.'),
            Action::make('repair')
                ->label('Mark In Repair')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->form([
                    $this->serialNumberSelect(),
                    Select::make('ticket_id')
                        ->label('Related Ticket')
                        ->options(fn () => Ticket::query()
                            ->latest()
                            ->limit(50)
                            ->pluck('subject', 'id'))
                        ->searchable(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    if ($this->hasSerialNumbers()) {
                        $serialNumber = $this->serialNumberFromActionData($data);
                        $fromStatus = $this->record->status;

                        $serialNumber->update(['status' => 'in_repair']);

                        $this->record->refresh();
                        $this->recordInventoryTransaction('repaired', $fromStatus, $data['ticket_id'] ?? null, $data['notes'] ?? null, [
                            'inventory_item_serial_number_id' => $serialNumber->id,
                        ]);
                        $this->refreshInventoryItemRecord();

                        return;
                    }

                    app(InventoryMovementService::class)->repair(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        ticketId: $data['ticket_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item marked in repair.'),
            Action::make('retire')
                ->icon('heroicon-o-archive-box-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => auth()->user()?->can('retire', $this->record) ?? false)
                ->form([
                    $this->serialNumberSelect(),
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
                    if ($this->hasSerialNumbers()) {
                        $serialNumber = $this->serialNumberFromActionData($data);
                        $fromStatus = $this->record->status;

                        $serialNumber->update([
                            'status' => 'retired',
                            'assigned_to_user_id' => null,
                        ]);

                        $this->record->refresh();
                        $this->recordInventoryTransaction('retired', $fromStatus, null, $data['notes'] ?? null, [
                            'inventory_item_serial_number_id' => $serialNumber->id,
                        ]);
                        $this->refreshInventoryItemRecord();

                        return;
                    }

                    app(InventoryMovementService::class)->retire(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item retired.'),
            Action::make('adjustStock')
                ->label('Adjust Stock')
                ->icon('heroicon-o-adjustments-horizontal')
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && (auth()->user()?->can('adjustStock', $this->record) ?? false))
                ->form([
                    TextInput::make('quantity')
                        ->label('New Quantity')
                        ->required()
                        ->numeric()
                        ->minValue(0),
                    Textarea::make('notes')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(InventoryMovementService::class)->adjust(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        newQuantity: (int) $data['quantity'],
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory stock adjusted.'),
        ];
    }

    private function refreshInventoryItemRecord(): void
    {
        $this->record->refresh();
        $this->refreshFormData(array_keys($this->record->getAttributes()));
    }

    /**
     * @param  array<int, string>|null  $statuses
     */
    private function serialNumberSelect(?array $statuses = null): Select
    {
        return Select::make('inventory_item_serial_number_id')
            ->label('Serial Number')
            ->options(fn () => $this->serialNumberOptions($statuses))
            ->default(fn (): ?int => $this->defaultSerialNumberId($statuses))
            ->visible(fn (): bool => $this->hasSerialNumbers())
            ->required(fn (): bool => $this->hasSerialNumbers())
            ->searchable();
    }

    /**
     * @param  array<int, string>|null  $statuses
     * @return array<int, string>
     */
    private function serialNumberOptions(?array $statuses = null): array
    {
        return $this->record->serialNumbers()
            ->when($statuses !== null, fn ($query) => $query->whereIn('status', $statuses))
            ->orderBy('serial_number')
            ->pluck('serial_number', 'id')
            ->all();
    }

    /**
     * @param  array<int, string>|null  $statuses
     */
    private function defaultSerialNumberId(?array $statuses = null): ?int
    {
        return $this->record->serialNumbers()
            ->when($statuses !== null, fn ($query) => $query->whereIn('status', $statuses))
            ->orderBy('serial_number')
            ->value('id');
    }

    private function hasSerialNumbers(): bool
    {
        return $this->record->serialNumbers()->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function serialNumberFromActionData(array $data): InventoryItemSerialNumber
    {
        return $this->record->serialNumbers()->findOrFail($data['inventory_item_serial_number_id']);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordInventoryTransaction(string $type, ?string $fromStatus, ?int $ticketId, ?string $notes, array $metadata = []): void
    {
        InventoryTransaction::create([
            'inventory_item_id' => $this->record->id,
            'ticket_id' => $ticketId,
            'user_id' => auth()->id(),
            'assigned_to_user_id' => $this->record->assigned_to_user_id,
            'type' => $type,
            'quantity' => 1,
            'from_status' => $fromStatus,
            'to_status' => $this->record->status,
            'notes' => $notes,
            'metadata' => $metadata ?: null,
        ]);
    }
}
