<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TicketsByPriorityChart extends ChartWidget
{
    protected static ?string $heading = 'Tickets by Priority';

    protected function getData(): array
    {
        $priorities = Ticket::select('priority', DB::raw('count(*) as count'))
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return [
            'datasets' => [
                [
                    'label' => 'Tickets',
                    'data' => [
                        $priorities['low'] ?? 0,
                        $priorities['normal'] ?? 0,
                        $priorities['high'] ?? 0,
                        $priorities['critical'] ?? 0,
                    ],
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => ['Low', 'Normal', 'High', 'Critical'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
