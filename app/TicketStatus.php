<?php

namespace App;

enum TicketStatus: string
{
    case Active = 'active';
    case OnProgress = 'on progress';
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Closed = 'closed';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::Active => in_array($newStatus, [self::OnProgress, self::Pending]),
            self::OnProgress => in_array($newStatus, [self::Pending, self::Closed, self::Overdue]),
            self::Pending => in_array($newStatus, [self::OnProgress, self::Closed, self::Overdue]),
            self::Overdue => in_array($newStatus, [self::OnProgress, self::Closed]),
            self::Closed => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnProgress => 'On Progress',
            self::Pending => 'Pending',
            self::Overdue => 'Overdue',
            self::Closed => 'Closed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::OnProgress => 'warning',
            self::Pending => 'gray',
            self::Overdue => 'danger',
            self::Closed => 'info',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->label()])
            ->toArray();
    }
}
