<?php

namespace App\Filament\Resources\JobOrders;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Filament\Resources\JobOrders\Pages\ListJobOrders;
use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\JobOrderStatus;
use App\Models\InventoryItemSerialNumber;
use App\Models\JobOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class JobOrderResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = JobOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Service Management';

    protected static ?string $navigationLabel = 'Job Orders';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request Information')->schema([
                Grid::make(2)->schema([
                    TextInput::make('subject')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('description')->required()->columnSpanFull(),
                    Select::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'])->default('normal')->required(),
                    Select::make('client_id')
                        ->label('Requester')
                        ->relationship('requestor', 'name', function (Builder $query): Builder {
                            $departmentId = Filament::getTenant()?->getKey();

                            return $query
                                ->when($departmentId, fn (Builder $query): Builder => $query->canAccessDepartment($departmentId))
                                ->where('status', 1)
                                ->where('is_deleted', 0);
                        })
                        ->searchable()
                        ->preload(),
                    TextInput::make('requested_by_name')->label('Requested By Name')->maxLength(255),
                    Select::make('source')->options(['manual' => 'Manual', 'inventory' => 'Inventory', 'preventive_maintenance' => 'Preventive Maintenance'])->default('manual')->required(),
                ]),
            ])->columnSpanFull(),
            Section::make('Asset Information')->schema([
                Grid::make(2)->schema([
                    Select::make('inventory_item_id')->label('Inventory Item')->relationship('inventoryItem', 'name', fn (Builder $query): Builder => $query->where('department_id', Filament::getTenant()?->id)->where('is_it_asset', false)->where('is_deleted', false))->searchable()->preload()->live()->afterStateUpdated(fn (Set $set): mixed => $set('inventory_item_serial_number_id', null)),
                    Select::make('inventory_item_serial_number_id')->label('Serial Number')->options(fn (Get $get): array => filled($get('inventory_item_id')) ? InventoryItemSerialNumber::query()->where('inventory_item_id', $get('inventory_item_id'))->orderBy('serial_number')->pluck('serial_number', 'id')->all() : [])->searchable(),
                    Placeholder::make('location')->content(fn (Get $get): string => InventoryItemSerialNumber::query()->with('location')->find($get('inventory_item_serial_number_id'))?->location?->name ?? 'No location assigned'),
                    Placeholder::make('classification')->label('Asset Classification')->content('Non-IT Asset'),
                ]),
            ])->columnSpanFull(),
            Section::make('Assignment')->schema([
                Grid::make(2)->schema([
                    Select::make('assigned_to_user_id')->label('Assigned User')->options(fn (): array => static::assignmentOptions())->searchable()->preload(),
                    Select::make('status')->options(JobOrderStatus::options())->default(JobOrderStatus::Active->value)->disabled()->required(),
                    DateTimePicker::make('started_at')->disabled(),
                    DateTimePicker::make('completed_at')->disabled(),
                    Textarea::make('remarks')->columnSpanFull(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Job Order')->schema([
                TextEntry::make('subject')->columnSpanFull(),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('status')->badge()->formatStateUsing(fn (JobOrderStatus $state): string => $state->label())->color(fn (JobOrderStatus $state): string => $state->color()),
                TextEntry::make('priority')->badge(),
                TextEntry::make('requestor.name')->label('Requestor'),
                TextEntry::make('creator.name')->label('Created By'),
                TextEntry::make('assignedUser.name')->label('Assigned To'),
                TextEntry::make('inventoryItem.name')->label('Asset'),
                TextEntry::make('inventoryItemSerialNumber.serial_number')->label('Serial Number'),
                TextEntry::make('started_at')->dateTime(),
                TextEntry::make('completed_at')->dateTime(),
                TextEntry::make('remarks')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                static::compactTextColumn(TextColumn::make('subject'), 40)->searchable()->sortable(),
                static::compactTextColumn(TextColumn::make('requestor.name'), 28)->label('Requestor')->searchable(),
                TextColumn::make('priority')->badge()->color(fn (string $state): string => match ($state) {
                    'low' => 'success', 'normal' => 'info', 'high' => 'warning', 'critical' => 'danger', default => 'gray'
                }),
                TextColumn::make('status')->badge()->formatStateUsing(fn (JobOrderStatus $state): string => $state->label())->color(fn (JobOrderStatus $state): string => $state->color()),
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 28)->label('Asset'),
                static::compactTextColumn(TextColumn::make('inventoryItemSerialNumber.location.name'), 26)->label('Location'),
                static::compactTextColumn(TextColumn::make('assignedUser.name'), 26)->label('Assigned User'),
                static::compactTextColumn(TextColumn::make('department.name'), 26)->label('Department')->toggleable(isToggledHiddenByDefault: true),
                static::compactTextColumn(TextColumn::make('creator.name'), 26)->label('Created By')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(JobOrderStatus::options())->multiple(),
                SelectFilter::make('priority')->options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'])->multiple(),
                SelectFilter::make('assigned_to_user_id')->relationship('assignedUser', 'name')->searchable()->preload(),
                SelectFilter::make('department_id')->relationship('department', 'name')->searchable()->preload(),
                SelectFilter::make('inventory_item_id')->label('Asset')->relationship('inventoryItem', 'name')->searchable()->preload(),
                SelectFilter::make('location_id')->label('Location')->relationship('inventoryItemSerialNumber.location', 'name')->searchable()->preload(),
                Filter::make('created_date')->form([DatePicker::make('from'), DatePicker::make('until')])->query(fn (Builder $query, array $data): Builder => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('assign')
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (JobOrder $record): bool => auth()->user()?->can('assign', $record) ?? false)
                    ->form([Select::make('assigned_to_user_id')->options(fn (): array => static::assignmentOptions())->required()->searchable()])
                    ->action(fn (JobOrder $record, array $data): bool => $record->update(['assigned_to_user_id' => $data['assigned_to_user_id']])),
                Action::make('start')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn (JobOrder $record): bool => static::canWork($record) && $record->canTransitionTo(JobOrderStatus::OnProgress))
                    ->action(fn (JobOrder $record): bool => $record->transitionTo(JobOrderStatus::OnProgress)),
                Action::make('pending')
                    ->icon('heroicon-o-pause')
                    ->visible(fn (JobOrder $record): bool => static::canWork($record) && $record->canTransitionTo(JobOrderStatus::Pending))
                    ->action(fn (JobOrder $record): bool => $record->transitionTo(JobOrderStatus::Pending)),
                Action::make('close')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (JobOrder $record): bool => (auth()->user()?->can('close', $record) ?? false) && $record->canTransitionTo(JobOrderStatus::Closed))
                    ->form([Textarea::make('remarks')->required()])
                    ->action(function (JobOrder $record, array $data): bool {
                        $record->remarks = $data['remarks'];

                        return $record->transitionTo(JobOrderStatus::Closed);
                    }),
                Action::make('reopen')->icon('heroicon-o-arrow-path')->color('info')->visible(fn (JobOrder $record): bool => (auth()->user()?->can('update', $record) ?? false) && $record->canTransitionTo(JobOrderStatus::Active))->requiresConfirmation()->action(fn (JobOrder $record): bool => $record->transitionTo(JobOrderStatus::Active)),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('assign')->icon('heroicon-o-user-plus')->form([Select::make('assigned_to_user_id')->options(fn (): array => static::assignmentOptions())->required()->searchable()])->action(fn (Collection $records, array $data) => $records->toQuery()->update(['assigned_to_user_id' => $data['assigned_to_user_id']])),
                BulkAction::make('close')->icon('heroicon-o-check-circle')->color('success')->requiresConfirmation()->action(fn (Collection $records) => $records->each(fn (JobOrder $record) => $record->canTransitionTo(JobOrderStatus::Closed) ? $record->transitionTo(JobOrderStatus::Closed) : false)),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['requestor', 'creator', 'assignedUser', 'inventoryItem', 'inventoryItemSerialNumber.location', 'department']);
    }

    public static function getPages(): array
    {
        return ['index' => ListJobOrders::route('/'), 'create' => CreateJobOrder::route('/create'), 'view' => ViewJobOrder::route('/{record}'), 'edit' => EditJobOrder::route('/{record}/edit')];
    }

    public static function assignmentOptions(): array
    {
        $departmentId = Filament::getTenant()?->id;

        return User::role(['job_order_manager', 'maintenance_staff', 'admin', 'super_admin'])
            ->when($departmentId, fn (Builder $query): Builder => $query->canAccessDepartment($departmentId))
            ->where('status', 1)->where('is_deleted', 0)->orderBy('name')->pluck('name', 'id')->all();
    }

    private static function canWork(JobOrder $jobOrder): bool
    {
        $user = auth()->user();

        return ($user?->can('update', $jobOrder) ?? false)
            && ($jobOrder->assigned_to_user_id === $user?->id || $user?->hasAnyRole(['super_admin', 'admin', 'job_order_manager']));
    }
}
