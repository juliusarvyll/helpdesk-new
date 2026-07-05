<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\JobOrderStatus;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListJobOrders extends ListRecords
{
    protected static string $resource = JobOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'open' => Tab::make('Open')->modifyQueryUsing(fn (Builder $query): Builder => $query->open()),
            'pending' => Tab::make('Pending')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', JobOrderStatus::Pending->value)),
            'closed' => Tab::make('Closed')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', JobOrderStatus::Closed->value)),
            'assigned' => Tab::make('Assigned')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNotNull('assigned_to_user_id')),
            'unassigned' => Tab::make('Unassigned')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereNull('assigned_to_user_id')),
            'my_assigned' => Tab::make('My Assigned')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('assigned_to_user_id', auth()->id())),
        ];
    }
}
