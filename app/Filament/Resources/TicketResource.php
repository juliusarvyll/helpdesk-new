<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\TicketResource\Pages;
use App\Models\InventoryItemSerialNumber;
use App\Models\IssueCategory;
use App\Models\IssueList;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssigned;
use App\TicketStatus;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TicketResource extends Resource
{
    use HasCompactTableColumns;

    protected const TECHNICAL_SUPPORT_ASSIGNMENT_ROLES = ['super_admin', 'admin', 'technical_support'];

    protected const UNASSIGNED_TICKET_ASSIGNMENT_ROLES = ['super_admin', 'technical_support'];

    protected static ?string $model = Ticket::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Helpdesk';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenant = Filament::getTenant();

        if ($tenant) {
            $query->where('department_id', $tenant->id);
        }

        if ($user = auth()->user()) {
            $query->visibleTo($user);
        }

        return $query->with(['issue', 'technicalSupportUsers']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('subject')
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit' && static::isClient())
                ->columnSpanFull(),
            Textarea::make('description')
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit' && static::isClient())
                ->columnSpanFull(),
            Select::make('priority')
                ->options(['low' => 'Low', 'normal' => 'Normal', 'critical' => 'Critical'])
                ->default('normal')
                ->disabled(fn (string $operation): bool => $operation === 'edit' && static::isClient())
                ->hiddenOn('create')
                ->required(),
            Select::make('status')
                ->options(TicketStatus::options())
                ->default(TicketStatus::Active->value)->required()
                ->visible(fn (): bool => static::canManageTechnicalSupportAssignments())
                ->hiddenOn('create')
                ->disabled(),
            Select::make('category')
                ->options(fn () => IssueCategory::where('is_deleted', 0)->pluck('name', 'id'))
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('issue_id', null))
                ->disabled(fn (string $operation): bool => $operation === 'edit' && static::isClient())
                ->required(),
            Select::make('issue_id')
                ->label('Issue')
                ->options(fn (callable $get) => $get('category')
                    ? IssueList::where('issue_category_id', $get('category'))
                        ->where('is_deleted', 0)
                        ->pluck('issue', 'id')
                    : []
                )
                ->searchable()
                ->disabled(fn (callable $get, string $operation): bool => ! $get('category') || ($operation === 'edit' && static::isClient()))
                ->required(),
            Select::make('inventory_item_id')
                ->label('Affected Inventory Item')
                ->relationship('inventoryItem', 'name', fn ($query) => $query->where('is_deleted', false))
                ->searchable()
                ->live()
                ->preload()
                ->afterStateUpdated(fn (callable $set) => $set('inventory_item_serial_number_id', null))
                ->hiddenOn('create')
                ->disabled(fn (string $operation): bool => $operation === 'edit' && static::isClient()),
            Select::make('inventory_item_serial_number_id')
                ->label('Affected Serial Number')
                ->options(fn (Get $get) => filled($get('inventory_item_id'))
                    ? InventoryItemSerialNumber::query()
                        ->where('inventory_item_id', $get('inventory_item_id'))
                        ->orderBy('serial_number')
                        ->pluck('serial_number', 'id')
                    : [])
                ->searchable()
                ->required(fn (Get $get): bool => filled($get('inventory_item_id')))
                ->hiddenOn('create')
                ->disabled(fn (Get $get, string $operation): bool => blank($get('inventory_item_id')) || ($operation === 'edit' && static::isClient())),
            Select::make('client_id')
                ->label('Client')
                ->options(function () {
                    $user = auth()->user();
                    if (static::canSelectTicketClient()) {
                        $tenant = Filament::getTenant();

                        return User::role(['client'])
                            ->whereHas('departments', fn ($q) => $q->where('department.id', $tenant?->id))
                            ->where('status', 1)
                            ->where('is_deleted', 0)
                            ->pluck('name', 'id');
                    }

                    return [$user->id => $user->name];
                })
                ->default(fn () => static::canSelectTicketClient() ? null : auth()->id())
                ->disabled(fn () => ! static::canSelectTicketClient())
                ->searchable()
                ->hiddenOn('create')
                ->required(),
            Placeholder::make('department_name')
                ->label('Department')
                ->content(fn (): string => auth()->user()?->department?->name ?? 'No department assigned')
                ->visible(fn (string $operation): bool => $operation !== 'create' && ! static::canSelectTicketClient()),
            Select::make('technicalSupportUsers')
                ->label('Technical Support')
                ->multiple()
                ->relationship('technicalSupportUsers', 'name')
                ->options(fn () => User::role(static::TECHNICAL_SUPPORT_ASSIGNMENT_ROLES)
                    ->where('status', 1)
                    ->where('is_deleted', 0)
                    ->pluck('name', 'id'))
                ->visible(fn (string $operation): bool => $operation !== 'create' && static::canManageTechnicalSupportAssignments())
                ->searchable()
                ->preload(),
            DateTimePicker::make('assigned_at')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create' && static::canManageTechnicalSupportAssignments()),
            DateTimePicker::make('start_time')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create' && static::canManageTechnicalSupportAssignments()),
            DateTimePicker::make('end_time')
                ->disabled()
                ->visible(fn (string $operation): bool => $operation !== 'create' && static::canManageTechnicalSupportAssignments()),
            Textarea::make('technical_support_remarks')
                ->visible(fn (string $operation): bool => $operation !== 'create' && static::canManageTechnicalSupportAssignments()),
            Textarea::make('client_comments')
                ->label(fn (): string => static::isClient() ? 'Comment' : 'Client Comments')
                ->hiddenOn('create')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('subject'), 40)
                    ->label('Subject / Issue')
                    ->description(fn (TextColumn $column): ?string => static::ticketIssueDescription($column))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        fn (Builder $query): Builder => $query
                            ->where('subject', 'like', "%{$search}%")
                            ->orWhereHas('issue', fn (Builder $query): Builder => $query->where('issue', 'like', "%{$search}%"))
                    ))
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('client.name'), 28)
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                static::compactTextColumn(TextColumn::make('creator.name'), 28)
                    ->label('Created By')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('client.department.name'), 28)
                    ->label('Department')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('priority')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'normal' => 'info',
                        'critical' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (TicketStatus $state): string => $state->label())
                    ->color(fn (TicketStatus $state): string => $state->color()),
                static::compactTextColumn(
                    TextColumn::make('technical_support_names')
                        ->label('Technical Support')
                        ->getStateUsing(fn (Ticket $record): string => static::technicalSupportNames($record)),
                    32
                )
                    ->visible(fn (): bool => static::canManageTechnicalSupportAssignments())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(TicketStatus::options())
                    ->multiple(),
                SelectFilter::make('priority')
                    ->options(['low' => 'Low', 'normal' => 'Normal', 'critical' => 'Critical'])
                    ->multiple(),
                Filter::make('assigned')
                    ->label('Assigned')
                    ->query(fn (Builder $query): Builder => $query->whereHas('technicalSupportUsers'))
                    ->visible(fn (): bool => static::canManageTechnicalSupportAssignments()),
                Filter::make('unassigned')
                    ->label('Unassigned')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('technicalSupportUsers'))
                    ->visible(fn (): bool => static::canManageTechnicalSupportAssignments()),
                Filter::make('my_assigned')
                    ->label('My Assigned Tickets')
                    ->query(fn (Builder $query): Builder => $query->assignedTo(auth()->user()))
                    ->visible(fn (): bool => auth()->user()?->hasRole('technical_support') ?? false),
            ])
            ->actions([
                Action::make('assign_technical_support')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->color('info')
                    ->visible(fn (Ticket $record): bool => static::canShowAssignTicketAction($record))
                    ->form([
                        Select::make('technicalSupportUsers')
                            ->label('Technical Support')
                            ->multiple()
                            ->options(fn (): array => static::technicalSupportAssignmentOptions())
                            ->default(fn (): array => static::defaultTechnicalSupportAssignmentSelection())
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->action(fn (Ticket $record, array $data): Ticket => static::assignTechnicalSupportUsers(
                        $record,
                        $data['technicalSupportUsers'] ?? []
                    ))
                    ->successNotificationTitle('Ticket assigned.'),
                Action::make('start_progress')
                    ->label('Start Progress')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (Ticket $record): bool => static::canShowStatusTransitionAction($record, 'startProgress'))
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => $record->transitionTo(TicketStatus::OnProgress)),
                Action::make('mark_pending')
                    ->label('Mark Pending')
                    ->icon('heroicon-o-pause')
                    ->color('gray')
                    ->visible(fn (Ticket $record): bool => static::canShowStatusTransitionAction($record, 'markPending'))
                    ->requiresConfirmation()
                    ->action(fn (Ticket $record) => $record->transitionTo(TicketStatus::Pending)),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Ticket $record): bool => static::canShowStatusTransitionAction($record, 'close'))
                    ->form([
                        Textarea::make('technical_support_remarks')
                            ->label('Technical Support Remarks')
                            ->default(fn (Ticket $record): ?string => $record->technical_support_remarks)
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Ticket $record, array $data): void {
                        $record->forceFill([
                            'technical_support_remarks' => $data['technical_support_remarks'],
                        ]);

                        $record->transitionTo(TicketStatus::Closed);
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }

    public static function canManageTechnicalSupportAssignments(): bool
    {
        return auth()->user()?->hasAnyRole(static::TECHNICAL_SUPPORT_ASSIGNMENT_ROLES) ?? false;
    }

    public static function isClient(): bool
    {
        return auth()->user()?->hasRole('client') ?? false;
    }

    public static function canSelectTicketClient(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }

    public static function canAssignUnassignedTickets(): bool
    {
        return auth()->user()?->hasAnyRole(static::UNASSIGNED_TICKET_ASSIGNMENT_ROLES) ?? false;
    }

    public static function canShowAssignTicketAction(Ticket $ticket): bool
    {
        return $ticket->status !== TicketStatus::Closed
            && static::canAssignUnassignedTickets()
            && (auth()->user()?->can('update', $ticket) ?? false)
            && ! static::ticketHasTechnicalSupportAssignment($ticket);
    }

    public static function canShowStatusTransitionAction(Ticket $ticket, string $ability): bool
    {
        return $ticket->status !== TicketStatus::Closed
            && static::ticketHasTechnicalSupportAssignment($ticket)
            && static::currentUserIsAssignedTechnicalSupport($ticket)
            && (auth()->user()?->can($ability, $ticket) ?? false);
    }

    public static function technicalSupportNames(Ticket $ticket): string
    {
        return $ticket->technicalSupportUsers->pluck('name')->join(', ') ?: 'Unassigned';
    }

    public static function ticketHasTechnicalSupportAssignment(Ticket $ticket): bool
    {
        if ($ticket->relationLoaded('technicalSupportUsers')) {
            return $ticket->technicalSupportUsers->isNotEmpty();
        }

        return $ticket->technicalSupportUsers()->exists();
    }

    public static function currentUserIsAssignedTechnicalSupport(Ticket $ticket): bool
    {
        $user = auth()->user();

        if (! $user?->hasRole('technical_support')) {
            return false;
        }

        if ($ticket->relationLoaded('technicalSupportUsers')) {
            return $ticket->technicalSupportUsers->contains($user);
        }

        return $ticket->technicalSupportUsers()->whereKey($user->id)->exists();
    }

    /**
     * @return array<int, string>
     */
    public static function technicalSupportAssignmentOptions(): array
    {
        return User::role(static::TECHNICAL_SUPPORT_ASSIGNMENT_ROLES)
            ->where('status', 1)
            ->where('is_deleted', 0)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int>
     */
    public static function defaultTechnicalSupportAssignmentSelection(): array
    {
        $user = auth()->user();

        if (! $user?->hasRole('technical_support')) {
            return [];
        }

        return [$user->id];
    }

    /**
     * @param  array<int|string>  $technicalSupportUserIds
     */
    public static function assignTechnicalSupportUsers(Ticket $ticket, array $technicalSupportUserIds): Ticket
    {
        $ticket->technicalSupportUsers()->sync($technicalSupportUserIds);
        $ticket->load('technicalSupportUsers');
        $ticket->syncAssignmentState();
        $ticket->refresh()->load('technicalSupportUsers');

        foreach ($ticket->technicalSupportUsers as $user) {
            $user->notify(new TicketAssigned($ticket));
        }

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareDirectTicketCreateData(array $data): array
    {
        unset(
            $data['inventory_item_id'],
            $data['inventory_item_serial_number_id'],
            $data['asset_id'],
            $data['asset_name'],
            $data['client_comments'],
            $data['technicalSupportUsers'],
            $data['support_assignment_status'],
            $data['assigned_at'],
            $data['start_time'],
            $data['end_time'],
            $data['technical_support_remarks'],
            $data['client_confirmation'],
        );

        $data['client_id'] = auth()->id();
        $data['priority'] = 'normal';
        $data['status'] = TicketStatus::Active->value;

        return $data;
    }

    protected static function ticketIssueDescription(TextColumn $column): ?string
    {
        $record = $column->getRecord();

        if (! $record instanceof Ticket) {
            return null;
        }

        $issue = $record->relationLoaded('issue')
            ? $record->getRelation('issue')
            : $record->issue()->first();

        if ($issue instanceof IssueList) {
            return $issue->issue;
        }

        $legacyIssue = $record->getAttribute('issue');

        return is_string($legacyIssue) ? $legacyIssue : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeTechnicalSupportAssignmentData(array $data, bool $allowAssignment = true): array
    {
        if (! $allowAssignment || ! static::canManageTechnicalSupportAssignments()) {
            unset($data['technicalSupportUsers']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function assignCreatorClientAndDepartmentData(array $data): array
    {
        $client = static::canSelectTicketClient() && filled($data['client_id'] ?? null)
            ? User::find($data['client_id'])
            : auth()->user();

        if (! $client) {
            return $data;
        }

        $data['client_id'] = $client->id;
        $data['department_id'] = $client->department_id;

        if (! $data['department_id']) {
            throw ValidationException::withMessages([
                'department_name' => 'Your account must be assigned to a department before creating a ticket.',
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeClientTicketData(array $data, bool $preserveOwnership = false): array
    {
        if (! static::isClient()) {
            return $data;
        }

        if (! $preserveOwnership) {
            unset($data['client_id'], $data['department_id']);
        }

        unset(
            $data['technicalSupportUsers'],
            $data['support_assignment_status'],
            $data['assigned_at'],
            $data['start_time'],
            $data['end_time'],
            $data['status'],
            $data['rate'],
            $data['technical_support_remarks'],
            $data['client_confirmation'],
        );

        return $data;
    }
}
