<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TicketResource\Widgets\TicketStats;
use App\TicketStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [TicketStats::class];
    }

    public function getTabs(): array
    {
        $baseQuery = TicketResource::getEloquentQuery();

        $tabs = [
            'all' => Tab::make('All'),
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [TicketStatus::Active, TicketStatus::OnProgress]))
                ->badge(fn () => (clone $baseQuery)->whereIn('status', [TicketStatus::Active, TicketStatus::OnProgress])->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Pending))
                ->badge(fn () => (clone $baseQuery)->where('status', TicketStatus::Pending)->count()),
            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Closed))
                ->badge(fn () => (clone $baseQuery)->where('status', TicketStatus::Closed)->count()),
        ];

        if (TicketResource::canManageTechnicalSupportAssignments()) {
            $tabs['assigned'] = Tab::make('Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('technicalSupportUsers'))
                ->badge(fn () => (clone $baseQuery)->whereHas('technicalSupportUsers')->count());

            $tabs['unassigned'] = Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('technicalSupportUsers'))
                ->badge(fn () => (clone $baseQuery)->whereDoesntHave('technicalSupportUsers')->count());
        }

        if (auth()->user()?->hasAnyRole(['admin', 'technical_support'])) {
            $tabs['my_assigned'] = Tab::make('My Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->assignedTo(auth()->user()))
                ->badge(fn () => (clone $baseQuery)->assignedTo(auth()->user())->count());
        }

        return $tabs;
    }
}
