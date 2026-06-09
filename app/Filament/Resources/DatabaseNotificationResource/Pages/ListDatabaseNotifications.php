<?php

namespace App\Filament\Resources\DatabaseNotificationResource\Pages;

use App\Filament\Resources\DatabaseNotificationResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseNotifications extends ListRecords
{
    protected static string $resource = DatabaseNotificationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markAllAsRead')
                ->label('Mark all as read')
                ->icon('heroicon-o-check-circle')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->unreadNotifications()->exists() ?? false)
                ->action(function (): void {
                    $updated = DatabaseNotificationResource::markAllAsRead();

                    Notification::make()
                        ->title($updated === 1 ? '1 notification marked as read.' : "{$updated} notifications marked as read.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
