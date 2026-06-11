<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TicketStatsOverview extends BaseWidget
{
    protected ?string $heading = 'Helpdesk Analytics';

    protected ?string $description = 'Operational health across the current department workspace.';

    protected function getStats(): array
    {
        $query = static::ticketQuery();

        $totalTickets = $query->clone()->count();
        $openTickets = $query->clone()->whereIn('status', static::openStatuses())->count();
        $assignedOpenTickets = $query->clone()
            ->whereIn('status', static::openStatuses())
            ->whereHas('technicalSupportUsers')
            ->count();
        $unassignedOpenTickets = $query->clone()
            ->whereIn('status', static::openStatuses())
            ->whereDoesntHave('technicalSupportUsers')
            ->count();
        $criticalOpenTickets = $query->clone()
            ->whereIn('status', static::openStatuses())
            ->where('priority', 'critical')
            ->count();
        $resolvedToday = $query->clone()
            ->where('status', TicketStatus::Closed)
            ->whereBetween('end_time', [today()->startOfDay(), today()->endOfDay()])
            ->count();
        $createdThisWeek = $query->clone()
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
        $createdLastWeek = $query->clone()
            ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
            ->count();
        $agingBacklog = $query->clone()
            ->whereIn('status', static::openStatuses())
            ->where('created_at', '<=', now()->subDays(3))
            ->count();

        $assignmentCoverage = static::percentage($assignedOpenTickets, $openTickets);
        $backlogShare = static::percentage($openTickets, $totalTickets);
        $weeklyChange = $createdThisWeek - $createdLastWeek;
        $averageResolutionHours = static::averageResolutionHours($query->clone());

        return [
            Stat::make('Open Backlog', $openTickets)
                ->description("{$backlogShare}% of accessible tickets")
                ->descriptionIcon('heroicon-m-ticket')
                ->chart(static::dailyCounts($query->clone(), 'created_at'))
                ->color($openTickets > 0 ? 'warning' : 'success'),
            Stat::make('Assignment Coverage', "{$assignmentCoverage}%")
                ->description("{$assignedOpenTickets} of {$openTickets} open tickets assigned")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($assignmentCoverage >= 80 || $openTickets === 0 ? 'success' : 'warning'),
            Stat::make('Unassigned Open', $unassignedOpenTickets)
                ->description('Tickets waiting for ownership')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($unassignedOpenTickets > 0 ? 'danger' : 'success'),
            Stat::make('Critical Open', $criticalOpenTickets)
                ->description('Critical tickets not yet closed')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($criticalOpenTickets > 0 ? 'danger' : 'success'),
            Stat::make('Resolved Today', $resolvedToday)
                ->description('Tickets closed today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->chart(static::dailyCounts($query->clone()->where('status', TicketStatus::Closed), 'end_time'))
                ->color('success'),
            Stat::make('New This Week', $createdThisWeek)
                ->description(static::weeklyChangeDescription($weeklyChange))
                ->descriptionIcon($weeklyChange > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($weeklyChange > 0 ? 'warning' : 'success'),
            Stat::make('Aging Backlog', $agingBacklog)
                ->description('Open for 3+ days')
                ->descriptionIcon('heroicon-m-clock')
                ->color($agingBacklog > 0 ? 'danger' : 'success'),
            Stat::make('Avg Resolution', static::formatHours($averageResolutionHours))
                ->description('Closed tickets with start/end times')
                ->descriptionIcon('heroicon-m-clock')
                ->color($averageResolutionHours <= 24 ? 'success' : 'warning'),
        ];
    }

    public static function ticketQuery(?User $user = null): Builder
    {
        $query = Ticket::query();
        $tenant = Filament::getTenant();

        if ($tenant) {
            $query->where('department_id', $tenant->id);
        }

        $user ??= auth()->user();

        if ($user) {
            $query->visibleTo($user);
        }

        return $query;
    }

    /**
     * @return array<int, TicketStatus>
     */
    public static function openStatuses(): array
    {
        return [
            TicketStatus::Active,
            TicketStatus::OnProgress,
            TicketStatus::Pending,
            TicketStatus::Overdue,
        ];
    }

    public static function percentage(int $value, int $total): int
    {
        if ($total === 0) {
            return 0;
        }

        return (int) round(($value / $total) * 100);
    }

    public static function averageResolutionHours(Builder $query): float
    {
        $durations = $query
            ->where('status', TicketStatus::Closed)
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, start_time, end_time)) as average_minutes')
            ->value('average_minutes');

        if ($durations === null) {
            return 0.0;
        }

        return round(((float) $durations) / 60, 1);
    }

    public static function formatHours(float $hours): string
    {
        if ($hours <= 0.0) {
            return 'n/a';
        }

        if ($hours < 1.0) {
            return (int) round($hours * 60).'m';
        }

        return $hours.'h';
    }

    /**
     * @return array<int, int>
     */
    public static function dailyCounts(Builder $query, string $dateColumn): array
    {
        return collect(range(6, 0))
            ->map(function (int $daysAgo) use ($query, $dateColumn): int {
                $date = today()->subDays($daysAgo);

                return $query->clone()
                    ->whereBetween($dateColumn, [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->count();
            })
            ->all();
    }

    public static function weeklyChangeDescription(int $change): string
    {
        if ($change === 0) {
            return 'No change vs last week';
        }

        $direction = $change > 0 ? 'more' : 'fewer';

        return abs($change)." {$direction} than last week";
    }
}
