<?php

namespace App\Providers;

use App\Models\InventoryItem;
use App\Models\JobOrder;
use App\Models\Ticket;
use App\Observers\InventoryItemObserver;
use App\Observers\JobOrderObserver;
use App\Observers\TicketObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        InventoryItem::observe(InventoryItemObserver::class);
        JobOrder::observe(JobOrderObserver::class);
        Ticket::observe(TicketObserver::class);

        Gate::before(fn ($user, string $ability): ?bool => $user->hasRole('super_admin') && ! in_array($ability, [
            'startProgress',
            'markPending',
            'close',
        ], true) ? true : null);

        Vite::prefetch(concurrency: 3);
    }
}
