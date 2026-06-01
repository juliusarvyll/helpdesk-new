<?php

namespace App\Filament\Resources\InventoryItemResource\Pages\Concerns;

use App\Filament\Resources\TicketResource;
use App\InventoryMovementService;
use App\InventoryTicketDefaults;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\TicketCreationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

trait HasInventoryItemActions
{
    /**
     * @return array<int, Action>
     */
    protected function inventoryItemActions(): array
    {
        return [
            Action::make('createTicket')
                ->label('Create Ticket')
                ->icon('heroicon-o-ticket')
                ->color('success')
                ->visible(fn (): bool => ($this->record->serialNumbers()->count() === 1) && (auth()->user()?->can('create_ticket') ?? false))
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
                    Select::make('priority')
                        ->options(['low' => 'Low', 'normal' => 'Normal', 'critical' => 'Critical'])
                        ->default('normal')
                        ->required(),
                    Select::make('category')
                        ->options(fn () => IssueCategory::query()
                            ->where('is_deleted', 0)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('issue_id', null))
                        ->required(),
                    Select::make('issue_id')
                        ->label('Issue')
                        ->options(fn (Get $get) => filled($get('category'))
                            ? IssueList::query()
                                ->where('issue_category_id', $get('category'))
                                ->where('is_deleted', 0)
                                ->orderBy('issue')
                                ->pluck('issue', 'id')
                            : [])
                        ->searchable()
                        ->required(),
                    Select::make('inventory_item_serial_number_id')
                        ->label('Serial Number')
                        ->options(fn () => $this->record->serialNumbers()
                            ->orderBy('serial_number')
                            ->pluck('serial_number', 'id'))
                        ->default(fn (): ?int => app(InventoryTicketDefaults::class)->serialNumberId($this->record))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $defaults = app(InventoryTicketDefaults::class);
                            $serialNumberId = filled($state) ? (int) $state : null;

                            if (TicketResource::canSelectTicketClient()) {
                                $set('client_id', $defaults->clientId($this->record, $serialNumberId, auth()->user()));
                            }

                            $set('subject', $defaults->subject($this->record, $serialNumberId));
                            $set('description', $defaults->description($this->record, $serialNumberId));
                        })
                        ->searchable()
                        ->required(),
                    Select::make('client_id')
                        ->label('Client')
                        ->options(function (Get $get) {
                            $defaults = app(InventoryTicketDefaults::class);
                            $serialNumberId = filled($get('inventory_item_serial_number_id'))
                                ? (int) $get('inventory_item_serial_number_id')
                                : $defaults->serialNumberId($this->record);
                            $defaultClientId = $defaults->clientId($this->record, $serialNumberId, auth()->user());

                            if (TicketResource::canSelectTicketClient()) {
                                $clients = User::role(['client'])
                                    ->where('status', 1)
                                    ->where('is_deleted', 0)
                                    ->orderBy('name')
                                    ->pluck('name', 'id');

                                if ($defaultClientId && ! $clients->has($defaultClientId)) {
                                    $defaultClient = User::query()
                                        ->whereKey($defaultClientId)
                                        ->where('status', 1)
                                        ->where('is_deleted', 0)
                                        ->pluck('name', 'id');

                                    return $clients->merge($defaultClient);
                                }

                                return $clients;
                            }

                            return [auth()->id() => auth()->user()->name];
                        })
                        ->default(fn (Get $get): ?int => TicketResource::canSelectTicketClient()
                            ? app(InventoryTicketDefaults::class)->clientId(
                                $this->record,
                                $get('inventory_item_serial_number_id') ?: app(InventoryTicketDefaults::class)->serialNumberId($this->record),
                                auth()->user(),
                            )
                            : auth()->id())
                        ->disabled(fn (): bool => ! TicketResource::canSelectTicketClient())
                        ->searchable()
                        ->required(),
                    Textarea::make('client_comments')
                        ->label(fn (): string => TicketResource::isClient() ? 'Comment' : 'Client Comments')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $ticket = app(TicketCreationService::class)->create([
                        ...$data,
                        'inventory_item_id' => $this->record->id,
                        'asset_id' => $this->record->asset_tag,
                        'asset_name' => $this->record->name,
                    ], auth()->user());

                    $this->redirect(TicketResource::getUrl('view', ['record' => $ticket]));
                }),
            Action::make('assign')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && (auth()->user()?->can('assign', $this->record) ?? false))
                ->form([
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
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && ($this->record->status === 'assigned') && (auth()->user()?->can('assign', $this->record) ?? false))
                ->form([
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
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && (auth()->user()?->can('assign', $this->record) ?? false))
                ->form([
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
                    app(InventoryMovementService::class)->transfer(
                        inventoryItem: $this->record,
                        actor: auth()->user(),
                        locationId: $data['location_id'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $this->refreshInventoryItemRecord();
                })
                ->successNotificationTitle('Inventory item transferred.'),
            Action::make('repair')
                ->label('Mark In Repair')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && (auth()->user()?->can('update', $this->record) ?? false))
                ->form([
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
                ->visible(fn (): bool => $this->record->serialNumbers()->doesntExist() && (auth()->user()?->can('retire', $this->record) ?? false))
                ->form([
                    Textarea::make('notes'),
                ])
                ->action(function (array $data): void {
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
}
