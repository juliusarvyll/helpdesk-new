<?php

namespace App\Filament\Resources\PreventiveMaintenanceSchedules;

use App\Filament\Concerns\HasCompactTableColumns;
use App\Filament\Resources\PreventiveMaintenanceSchedules\Pages\CreatePreventiveMaintenanceSchedule;
use App\Filament\Resources\PreventiveMaintenanceSchedules\Pages\EditPreventiveMaintenanceSchedule;
use App\Filament\Resources\PreventiveMaintenanceSchedules\Pages\ListPreventiveMaintenanceSchedules;
use App\Filament\Resources\PreventiveMaintenanceSchedules\Pages\ViewPreventiveMaintenanceSchedule;
use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\PreventiveMaintenanceSchedule;
use App\Models\User;
use App\PreventiveMaintenanceFrequency;
use App\PreventiveMaintenanceGenerationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class PreventiveMaintenanceScheduleResource extends Resource
{
    use HasCompactTableColumns;

    protected static ?string $model = PreventiveMaintenanceSchedule::class;

    protected static ?string $tenantOwnershipRelationshipName = 'department';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Preventive Maintenance Schedules';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Schedule Information')->schema([
                Grid::make(2)->schema([
                    TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('description')->required()->columnSpanFull(),
                    Select::make('frequency')->options(PreventiveMaintenanceFrequency::options())->required()->live(),
                    TextInput::make('interval_value')->label('Interval Value (Days)')->numeric()->minValue(1)
                        ->visible(fn (Get $get): bool => $get('frequency') === PreventiveMaintenanceFrequency::Custom->value)
                        ->required(fn (Get $get): bool => $get('frequency') === PreventiveMaintenanceFrequency::Custom->value),
                    DateTimePicker::make('starts_at')->required()->default(now()),
                    DateTimePicker::make('next_due_at')->required()->default(now()),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
            ])->columnSpanFull(),
            Section::make('Asset Information')->schema([
                Grid::make(2)->schema([
                    Select::make('inventory_item_id')
                        ->label('Inventory Item')
                        ->relationship('inventoryItem', 'name', fn (Builder $query): Builder => $query
                            ->where('department_id', Filament::getTenant()?->id)
                            ->where('is_deleted', false)
                            ->whereNotIn('status', ['retired', 'lost', 'disposed']))
                        ->required()->searchable()->preload()->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('inventory_item_serial_number_id', null)),
                    Placeholder::make('asset_classification')
                        ->label('Asset Classification')
                        ->content(function (Get $get): string {
                            $inventoryItemId = $get('inventory_item_id');

                            if (blank($inventoryItemId)) {
                                return 'Select an inventory item.';
                            }

                            return InventoryItem::query()->whereKey($inventoryItemId)->value('is_it_asset')
                                ? 'IT Asset — generates Helpdesk Tickets'
                                : 'Non-IT Asset — generates Job Orders';
                        }),
                    Select::make('inventory_item_serial_number_id')
                        ->label('Serial Number')
                        ->options(fn (Get $get): array => filled($get('inventory_item_id'))
                            ? InventoryItemSerialNumber::query()->where('inventory_item_id', $get('inventory_item_id'))->orderBy('serial_number')->pluck('serial_number', 'id')->all()
                            : [])
                        ->searchable(),
                    Select::make('assigned_to_user_id')->label('Assigned User')->options(fn (): array => static::assignmentOptions())->searchable()->preload(),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Preventive Maintenance Schedule')->schema([
                TextEntry::make('title'),
                TextEntry::make('frequency')->formatStateUsing(fn (PreventiveMaintenanceFrequency $state): string => $state->label())->badge(),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('inventoryItem.name')->label('Asset'),
                TextEntry::make('inventoryItem.is_it_asset')->label('Classification')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'IT Asset' : 'Non-IT Asset')->color(fn (bool $state): string => $state ? 'info' : 'gray'),
                TextEntry::make('inventoryItemSerialNumber.serial_number')->label('Serial Number'),
                TextEntry::make('assignedUser.name')->label('Assigned User'),
                TextEntry::make('starts_at')->dateTime(),
                TextEntry::make('next_due_at')->dateTime(),
                TextEntry::make('last_generated_at')->dateTime(),
                TextEntry::make('is_active')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('creator.name')->label('Created By'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::compactTextColumn(TextColumn::make('title'), 34)->searchable()->sortable(),
                static::compactTextColumn(TextColumn::make('inventoryItem.name'), 30)->label('Asset')->searchable()->sortable(),
                static::compactTextColumn(TextColumn::make('inventoryItemSerialNumber.serial_number'), 24)->label('Serial Number'),
                TextColumn::make('frequency')->badge()->formatStateUsing(fn (PreventiveMaintenanceFrequency $state): string => $state->label()),
                TextColumn::make('next_due_at')->label('Next Due Date')->dateTime()->sortable()->color(fn ($state): string => $state->isPast() ? 'danger' : 'gray'),
                static::compactTextColumn(TextColumn::make('assignedUser.name'), 26)->label('Assigned User'),
                TextColumn::make('due_status')->label('Status')->badge()->state(fn (PreventiveMaintenanceSchedule $record): string => $record->next_due_at->isPast() ? 'Overdue' : ($record->next_due_at->isToday() ? 'Due Today' : 'Scheduled'))->color(fn (string $state): string => match ($state) {
                    'Overdue' => 'danger', 'Due Today' => 'warning', default => 'info'
                }),
                TextColumn::make('is_active')->label('Active')->badge()->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                static::compactTextColumn(TextColumn::make('creator.name'), 26)->label('Created By')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('next_due_at')
            ->filters([
                SelectFilter::make('frequency')->options(PreventiveMaintenanceFrequency::options()),
                SelectFilter::make('is_active')->label('Active')->options([1 => 'Active', 0 => 'Inactive']),
                Filter::make('due_today')->query(fn (Builder $query): Builder => $query->whereDate('next_due_at', today())),
                Filter::make('overdue')->query(fn (Builder $query): Builder => $query->where('next_due_at', '<', now())),
                SelectFilter::make('inventory_item_id')->label('Asset')->relationship('inventoryItem', 'name')->searchable()->preload(),
                SelectFilter::make('department_id')->label('Department')->relationship('department', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('deactivate')->icon('heroicon-o-pause')->color('gray')->visible(fn (PreventiveMaintenanceSchedule $record): bool => $record->is_active)->requiresConfirmation()->action(fn (PreventiveMaintenanceSchedule $record): bool => $record->update(['is_active' => false])),
                Action::make('activate')->icon('heroicon-o-play')->color('success')->visible(fn (PreventiveMaintenanceSchedule $record): bool => ! $record->is_active)->action(fn (PreventiveMaintenanceSchedule $record): bool => $record->update(['is_active' => true])),
                Action::make('generateWork')->label('Generate Work Order')->icon('heroicon-o-wrench-screwdriver')->color('warning')->requiresConfirmation()->action(fn (PreventiveMaintenanceSchedule $record) => app(PreventiveMaintenanceGenerationService::class)->generate($record, force: true))->successNotificationTitle('Preventive maintenance work processed.'),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')->icon('heroicon-o-play')->color('success')->action(fn (Collection $records): int => $records->toQuery()->update(['is_active' => true])),
                    BulkAction::make('deactivate')->icon('heroicon-o-pause')->color('gray')->action(fn (Collection $records): int => $records->toQuery()->update(['is_active' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['inventoryItem', 'inventoryItemSerialNumber', 'assignedUser', 'creator', 'department']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPreventiveMaintenanceSchedules::route('/'),
            'create' => CreatePreventiveMaintenanceSchedule::route('/create'),
            'view' => ViewPreventiveMaintenanceSchedule::route('/{record}'),
            'edit' => EditPreventiveMaintenanceSchedule::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function assignmentOptions(): array
    {
        $departmentId = Filament::getTenant()?->id;

        return User::role(['technical_support', 'maintenance_staff', 'job_order_manager'])
            ->when($departmentId, fn (Builder $query): Builder => $query->canAccessDepartment($departmentId))
            ->where('status', 1)->where('is_deleted', 0)->orderBy('name')->pluck('name', 'id')->all();
    }
}
