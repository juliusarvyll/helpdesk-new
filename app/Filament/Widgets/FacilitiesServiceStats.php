<?php

namespace App\Filament\Widgets;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Location;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceSchedule;
use App\PreventiveMaintenanceAssetCheckStatus;
use App\TicketStatus;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FacilitiesServiceStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Facilities, Asset, and IT Service Management';

    protected function getStats(): array
    {
        $departmentId = Filament::getTenant()?->id;
        $scheduleQuery = PreventiveMaintenanceSchedule::query()->where('department_id', $departmentId)->where('is_active', true);
        $checkQuery = PreventiveMaintenanceAssetCheck::query()->whereHas('session', fn ($query) => $query->where('department_id', $departmentId));
        $totalChecks = $checkQuery->clone()->count();
        $completedChecks = $checkQuery->clone()->whereNotIn('status', ['pending', 'in_progress'])->count();
        $completionRate = $totalChecks === 0 ? 0 : (int) round(($completedChecks / $totalChecks) * 100);

        return [
            Stat::make('IT Assets Due For PMS', $scheduleQuery->clone()->whereHas('inventoryItem', fn ($query) => $query->where('is_it_asset', true))->whereBetween('next_due_at', [now(), now()->addDays(7)])->count())->color('warning'),
            Stat::make('Overdue PMS', $scheduleQuery->clone()->where('next_due_at', '<', now())->count())->color('danger'),
            Stat::make('Open Job Orders', JobOrder::query()->where('department_id', $departmentId)->open()->count())->color('warning'),
            Stat::make('Open PM Repair Tickets', PreventiveMaintenanceAssetCheck::query()->whereHas('session', fn ($query) => $query->where('department_id', $departmentId))->where('status', PreventiveMaintenanceAssetCheckStatus::NeedsRepair)->whereHas('ticket', fn ($query) => $query->where('status', '!=', TicketStatus::Closed->value))->count())->color('danger'),
            Stat::make('Locations Pending Inspection', Location::query()->where('department_id', $departmentId)->whereHas('itAssetSerialNumbers', fn ($query) => $query->whereDoesntHave('preventiveMaintenanceAssetChecks', fn ($query) => $query->whereNotNull('completed_at')))->count())->color('warning'),
            Stat::make('PMS Completion Rate', "{$completionRate}%")->color($completionRate >= 80 ? 'success' : 'warning'),
            Stat::make('Assets With Repeated Failures', InventoryItem::query()->where('department_id', $departmentId)->whereHas('preventiveMaintenanceAssetChecks', fn ($query) => $query->whereIn('status', ['failed', 'needs_repair']), '>=', 2)->count())->color('danger'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin', 'technical_support', 'job_order_manager', 'maintenance_staff']) ?? false;
    }
}
