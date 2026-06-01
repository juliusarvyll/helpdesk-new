<?php

namespace App\Filament\Resources\TicketResource\Widgets;

use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\TicketStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStats extends BaseWidget
{
    protected function getStats(): array
    {
        $query = Ticket::query();

        if (method_exists($this, 'getTableQuery')) {
            $query = $this->getTableQuery();
        }

        $stats = [
            Stat::make('Open Tickets', $query->clone()->whereIn('status', [TicketStatus::Active, TicketStatus::OnProgress])->count())
                ->description('Active and in progress')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Pending', $query->clone()->where('status', TicketStatus::Pending)->count())
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Closed', $query->clone()->where('status', TicketStatus::Closed)->count())
                ->description('Resolved tickets')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('gray'),
        ];

        if (TicketResource::canManageTechnicalSupportAssignments()) {
            $stats[] = Stat::make('Assigned', $query->clone()->whereHas('technicalSupportUsers')->count())
                ->description('With technical support')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('info');
        }

        return $stats;
    }
}
