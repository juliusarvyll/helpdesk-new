<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\AssetWorkOrderCreationService;
use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\TicketResource;
use App\InventoryTicketDefaults;
use App\Models\InventoryItemSerialNumber;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\JobOrder;
use App\Models\Location;
use App\Models\User;
use App\TicketStatus;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SerialNumbersRelationManager extends RelationManager
{
    use HasCompactTableColumns;

    protected static string $relationship = 'serialNumbers';

    protected static ?string $title = 'Serial Numbers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('serial_number')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with([
                    'assignedToUser',
                    'location',
                    'latestPreventiveMaintenanceAssetCheck.inspector',
                ])
                ->withCount([
                    'openTickets',
                    'preventiveMaintenanceAssetChecks',
                    'preventiveMaintenanceAssetChecks as open_pms_repair_tickets_count' => fn (Builder $query): Builder => $query->whereHas('ticket', fn (Builder $query): Builder => $query->where('status', '!=', TicketStatus::Closed->value)),
                ]))
            ->columns([
                static::compactTextColumn(TextColumn::make('serial_number'), 32)
                    ->label('Serial Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'available' => 'success',
                        'assigned' => 'info',
                        'in_repair' => 'warning',
                        'retired', 'lost', 'disposed' => 'danger',
                    })
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('location.name'), 28)
                    ->label('Location')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('assignedToUser.name'), 28)
                    ->label('Assigned To')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('open_tickets')
                    ->label('Open Tickets')
                    ->state(fn (InventoryItemSerialNumber $record): int => static::openTicketsCount($record))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('latestPreventiveMaintenanceAssetCheck.completed_at')
                    ->label('Last PMS Date')
                    ->dateTime(),
                TextColumn::make('latestPreventiveMaintenanceAssetCheck.inspector.name')
                    ->label('Last Inspector'),
                TextColumn::make('latestPreventiveMaintenanceAssetCheck.status')
                    ->label('Last Result')
                    ->badge(),
                TextColumn::make('preventive_maintenance_asset_checks_count')
                    ->label('Inspection Count'),
                TextColumn::make('open_pms_repair_tickets_count')
                    ->label('Open Repair Tickets')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->form([
                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
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
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => Location::query()
                                ->where('is_deleted', false)
                                ->where('department_id', Filament::getTenant()?->id)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable(),
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
                            ->searchable(),
                    ]),
            ])
            ->actions([
                Action::make('viewPmsHistory')
                    ->label('PMS History')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (): bool => (bool) $this->getOwnerRecord()->is_it_asset)
                    ->modalHeading(fn (InventoryItemSerialNumber $record): string => "PMS History: {$record->serial_number}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (InventoryItemSerialNumber $record) => view('filament.inventory.pms-history', [
                        'checks' => $record->preventiveMaintenanceAssetChecks()
                            ->with(['inspector', 'ticket'])
                            ->latest('completed_at')
                            ->get(),
                    ])),
                Action::make('createWorkRequest')
                    ->label('Create Work Request')
                    ->icon('heroicon-o-ticket')
                    ->color('success')
                    ->visible(function (InventoryItemSerialNumber $record): bool {
                        $ownerRecord = $this->getOwnerRecord();
                        $permission = $ownerRecord->is_it_asset ? 'create_ticket' : 'create_job_order';
                        $hasOpenWork = $ownerRecord->is_it_asset ? static::hasOpenTickets($record) : $record->hasOpenJobOrder();

                        return (auth()->user()?->can($permission) ?? false) && ! $hasOpenWork;
                    })
                    ->form([
                        TextInput::make('subject')
                            ->required()
                            ->maxLength(191)
                            ->default(fn (InventoryItemSerialNumber $record): string => app(InventoryTicketDefaults::class)->subject(
                                $this->getOwnerRecord(),
                                $record->id,
                            )),
                        Textarea::make('description')
                            ->default(fn (InventoryItemSerialNumber $record): string => app(InventoryTicketDefaults::class)->description(
                                $this->getOwnerRecord(),
                                $record->id,
                            ))
                            ->required()
                            ->columnSpanFull(),
                        Select::make('category')
                            ->options(fn () => IssueCategory::query()
                                ->where('is_deleted', 0)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (callable $set) => $set('issue_id', null))
                            ->visible(fn (): bool => TicketResource::shouldCollectTicketClassification())
                            ->required(fn (): bool => TicketResource::shouldCollectTicketClassification()),
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
                            ->visible(fn (): bool => TicketResource::shouldCollectTicketClassification())
                            ->required(fn (): bool => TicketResource::shouldCollectTicketClassification()),
                        Textarea::make('client_comments')
                            ->label(fn (): string => TicketResource::isClient() ? 'Comment' : 'Client Comments')
                            ->columnSpanFull(),
                    ])
                    ->action(function (InventoryItemSerialNumber $record, array $data): void {
                        $ownerRecord = $this->getOwnerRecord();
                        $work = app(AssetWorkOrderCreationService::class)->create($ownerRecord, [
                            ...$data,
                            'priority' => 'normal',
                            'client_id' => auth()->id(),
                            'inventory_item_serial_number_id' => $record->id,
                        ], auth()->user());

                        $this->redirect($work instanceof JobOrder
                            ? JobOrderResource::getUrl('view', ['record' => $work])
                            : TicketResource::getUrl('view', ['record' => $work]));
                    }),
                EditAction::make()
                    ->form([
                        TextInput::make('serial_number')
                            ->label('Serial Number')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('status')
                            ->options([
                                'available' => 'Available',
                                'assigned' => 'Assigned',
                                'in_repair' => 'In Repair',
                                'retired' => 'Retired',
                                'lost' => 'Lost',
                                'disposed' => 'Disposed',
                            ])
                            ->required(),
                        Select::make('location_id')
                            ->label('Location')
                            ->options(fn () => Location::query()
                                ->where('is_deleted', false)
                                ->where('department_id', Filament::getTenant()?->id)
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable(),
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
                            ->searchable(),
                    ]),
            ])
            ->defaultSort('serial_number');
    }

    /**
     * @return array<int, string>
     */
    private function clientOptions(InventoryItemSerialNumber $record): array
    {
        if (! TicketResource::canSelectTicketClient()) {
            return [auth()->id() => auth()->user()->name];
        }

        $tenant = Filament::getTenant();
        $clients = User::role(['client'])
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->whereHas('departments', fn ($query) => $query->where('department.id', $tenant?->id))
            ->orderBy('name')
            ->pluck('name', 'id');

        $defaultClientId = $this->defaultClientId($record);

        if ($defaultClientId && ! $clients->has($defaultClientId)) {
            $defaultClient = User::query()
                ->whereKey($defaultClientId)
                ->where('status', 1)
                ->where('is_deleted', 0)
                ->pluck('name', 'id');

            return $clients->merge($defaultClient)->all();
        }

        return $clients->all();
    }

    private function defaultClientId(InventoryItemSerialNumber $record): ?int
    {
        return app(InventoryTicketDefaults::class)->clientId(
            $this->getOwnerRecord(),
            $record->id,
            auth()->user(),
        );
    }

    private static function openTicketsCount(InventoryItemSerialNumber $record): int
    {
        if (array_key_exists('open_tickets_count', $record->getAttributes())) {
            return (int) $record->open_tickets_count;
        }

        return $record->openTickets()->count();
    }

    private static function hasOpenTickets(InventoryItemSerialNumber $record): bool
    {
        if (array_key_exists('open_tickets_count', $record->getAttributes())) {
            return (int) $record->open_tickets_count > 0;
        }

        return $record->hasOpenTicket();
    }
}
