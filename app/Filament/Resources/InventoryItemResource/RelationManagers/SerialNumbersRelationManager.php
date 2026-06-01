<?php

namespace App\Filament\Resources\InventoryItemResource\RelationManagers;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\TicketResource;
use App\InventoryTicketDefaults;
use App\Models\InventoryItemSerialNumber;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\TicketCreationService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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
                    ->state(fn (InventoryItemSerialNumber $record): int => $record->openTickets()->count())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
            ])
            ->actions([
                Action::make('createTicket')
                    ->label('Create Ticket')
                    ->icon('heroicon-o-ticket')
                    ->color('success')
                    ->visible(fn (InventoryItemSerialNumber $record): bool => (auth()->user()?->can('create_ticket') ?? false) && ! $record->hasOpenTicket())
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
                        Select::make('client_id')
                            ->label('Client')
                            ->options(fn (InventoryItemSerialNumber $record) => $this->clientOptions($record))
                            ->default(fn (InventoryItemSerialNumber $record): ?int => $this->defaultClientId($record))
                            ->disabled(fn (): bool => ! TicketResource::canSelectTicketClient())
                            ->searchable()
                            ->required(),
                        Textarea::make('client_comments')
                            ->label(fn (): string => TicketResource::isClient() ? 'Comment' : 'Client Comments')
                            ->columnSpanFull(),
                    ])
                    ->action(function (InventoryItemSerialNumber $record, array $data): void {
                        $ownerRecord = $this->getOwnerRecord();
                        $ticket = app(TicketCreationService::class)->create([
                            ...$data,
                            'inventory_item_id' => $ownerRecord->id,
                            'inventory_item_serial_number_id' => $record->id,
                            'asset_id' => $ownerRecord->asset_tag,
                            'asset_name' => $ownerRecord->name,
                        ], auth()->user());

                        $this->redirect(TicketResource::getUrl('view', ['record' => $ticket]));
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
}
