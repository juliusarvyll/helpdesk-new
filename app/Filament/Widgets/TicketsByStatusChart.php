<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\TicketStatus;
use Filament\Widgets\ChartWidget;

class TicketsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Tickets by Status';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Tickets',
                    'data' => [
                        Ticket::where('status', TicketStatus::Active)->count(),
                        Ticket::where('status', TicketStatus::OnProgress)->count(),
                        Ticket::where('status', TicketStatus::Pending)->count(),
                        Ticket::where('status', TicketStatus::Overdue)->count(),
                        Ticket::where('status', TicketStatus::Closed)->count(),
                    ],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#6b7280', '#ef4444', '#3b82f6'],
                ],
            ],
            'labels' => ['Active', 'On Progress', 'Pending', 'Overdue', 'Closed'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
