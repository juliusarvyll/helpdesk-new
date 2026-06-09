<?php

namespace App\Http\Middleware;

use App\Filament\Resources\DatabaseNotificationResource;
use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AlertUnreadDatabaseNotifications
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->sendUnreadNotificationAlert($request);
        }

        return $next($request);
    }

    private function sendUnreadNotificationAlert(Request $request): void
    {
        $unreadQuery = $request->user()->unreadNotifications();
        $unreadCount = $unreadQuery->count();

        if ($unreadCount === 0) {
            $request->session()->forget('database_notifications.unread_alert_signature');

            return;
        }

        $latestUnreadId = (string) $unreadQuery->latest()->value('id');
        $signature = $unreadCount.'|'.$latestUnreadId;

        if ($request->session()->get('database_notifications.unread_alert_signature') === $signature) {
            return;
        }

        $request->session()->put('database_notifications.unread_alert_signature', $signature);

        $tenant = $this->resolveTenant($request);
        $notification = Notification::make()
            ->title($unreadCount === 1 ? 'You have 1 unread notification.' : "You have {$unreadCount} unread notifications.")
            ->body('Open your notifications to review and mark them as read.')
            ->warning()
            ->persistent();

        if ($tenant) {
            $notification->actions([
                Action::make('view_notifications')
                    ->label('View Notifications')
                    ->url(DatabaseNotificationResource::getUrl('index', [
                        'tenant' => $tenant,
                    ])),
            ]);
        }

        $notification->send();
    }

    private function resolveTenant(Request $request): ?Model
    {
        $routeTenant = $request->route('tenant');

        if ($routeTenant instanceof Model) {
            return $routeTenant;
        }

        $currentTenant = Filament::getTenant();

        if ($currentTenant instanceof Model) {
            return $currentTenant;
        }

        $user = $request->user();

        if (! $user instanceof HasTenants) {
            return null;
        }

        return $user->getTenants(Filament::getCurrentPanel())->first();
    }
}
