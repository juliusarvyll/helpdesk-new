<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\TicketStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    /**
     * @var array<string, int>|null
     */
    protected ?array $ticketTabCounts = null;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [TicketStatus::Active, TicketStatus::OnProgress]))
                ->badge(fn () => $this->ticketTabCount('open')),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Pending))
                ->badge(fn () => $this->ticketTabCount('pending')),
            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', TicketStatus::Closed))
                ->badge(fn () => $this->ticketTabCount('closed')),
        ];

        if (TicketResource::canManageTechnicalSupportAssignments()) {
            $tabs['assigned'] = Tab::make('Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('technicalSupportUsers'))
                ->badge(fn () => $this->ticketTabCount('assigned'));

            $tabs['unassigned'] = Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('technicalSupportUsers'))
                ->badge(fn () => $this->ticketTabCount('unassigned'));
        }

        if (auth()->user()?->hasAnyRole(['admin', 'technical_support'])) {
            $tabs['my_assigned'] = Tab::make('My Assigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->assignedTo(auth()->user()))
                ->badge(fn () => $this->ticketTabCount('my_assigned'));
        }

        return $tabs;
    }

    protected function ticketTabCount(string $key): int
    {
        return $this->ticketTabCounts()[$key] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    protected function ticketTabCounts(): array
    {
        if ($this->ticketTabCounts !== null) {
            return $this->ticketTabCounts;
        }

        $counts = (clone TicketResource::getEloquentQuery())
            ->toBase()
            ->selectRaw('SUM(status IN (?, ?)) as open_count', [TicketStatus::Active->value, TicketStatus::OnProgress->value])
            ->selectRaw('SUM(status = ?) as pending_count', [TicketStatus::Pending->value])
            ->selectRaw('SUM(status = ?) as closed_count', [TicketStatus::Closed->value])
            ->first();

        $this->ticketTabCounts = [
            'open' => (int) ($counts->open_count ?? 0),
            'pending' => (int) ($counts->pending_count ?? 0),
            'closed' => (int) ($counts->closed_count ?? 0),
        ];

        if (TicketResource::canManageTechnicalSupportAssignments()) {
            $this->ticketTabCounts['assigned'] = (clone TicketResource::getEloquentQuery())
                ->whereHas('technicalSupportUsers')
                ->count();
            $this->ticketTabCounts['unassigned'] = (clone TicketResource::getEloquentQuery())
                ->whereDoesntHave('technicalSupportUsers')
                ->count();
        }

        if (auth()->user()?->hasAnyRole(['admin', 'technical_support'])) {
            $this->ticketTabCounts['my_assigned'] = (clone TicketResource::getEloquentQuery())
                ->assignedTo(auth()->user())
                ->count();
        }

        return $this->ticketTabCounts;
    }
}
