<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PreventiveMaintenanceSessions\PreventiveMaintenanceSessionResource;
use App\Models\Location;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceSchedule;
use App\PmsInspectionService;
use App\PreventiveMaintenanceAssetCheckStatus;
use App\TicketStatus;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationPmsDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'PMS Dashboard';

    protected string $view = 'filament.pages.location-pms-dashboard';

    public function table(Table $table): Table
    {
        return $table
            ->query(Location::query()->where('department_id', Filament::getTenant()?->id)->where('is_deleted', false))
            ->columns([
                TextColumn::make('name')->label('Location')->searchable()->sortable(),
                TextColumn::make('it_asset_count')->label('IT Asset Count')->state(fn (Location $record): int => $record->itAssetSerialNumbers()->count()),
                TextColumn::make('last_pms')->label('Last PMS')->state(fn (Location $record) => PreventiveMaintenanceAssetCheck::query()->whereHas('session', fn (Builder $query): Builder => $query->where('location_id', $record->id))->max('completed_at'))->dateTime(),
                TextColumn::make('pending_pms')->label('Pending PMS')->state(fn (Location $record): int => $record->itAssetSerialNumbers()->whereDoesntHave('preventiveMaintenanceAssetChecks', fn (Builder $query): Builder => $query->whereNotNull('completed_at'))->count()),
                TextColumn::make('overdue_pms')->label('Overdue PMS')->state(fn (Location $record): int => PreventiveMaintenanceSchedule::query()->where('department_id', $record->department_id)->where('is_active', true)->where('next_due_at', '<', now())->whereHas('inventoryItemSerialNumber', fn (Builder $query): Builder => $query->where('location_id', $record->id))->count()),
                TextColumn::make('open_repair_tickets')->label('Open Repair Tickets')->state(fn (Location $record): int => PreventiveMaintenanceAssetCheck::query()->whereHas('session', fn (Builder $query): Builder => $query->where('location_id', $record->id))->where('status', PreventiveMaintenanceAssetCheckStatus::NeedsRepair->value)->whereHas('ticket', fn (Builder $query): Builder => $query->where('status', '!=', TicketStatus::Closed->value))->count()),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('startPmsSession')->label('Start PMS Session')->icon('heroicon-o-play')->color('success')
                    ->form([
                        Select::make('checklist_template_id')->label('Checklist Template')->options(fn (): array => PmsChecklistTemplate::query()->where('is_active', true)->whereHas('items')->orderBy('name')->pluck('name', 'id')->all())->required()->searchable(),
                    ])
                    ->action(function (Location $record, array $data): void {
                        $session = app(PmsInspectionService::class)->startSession($record, auth()->user(), PmsChecklistTemplate::query()->findOrFail($data['checklist_template_id']));
                        $this->redirect(PreventiveMaintenanceSessionResource::getUrl('view', ['record' => $session]));
                    }),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support']) ?? false;
    }
}
