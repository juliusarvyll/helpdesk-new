<?php

namespace App;

enum JobOrderStatus: string
{
    case Active = 'active';
    case OnProgress = 'on progress';
    case Pending = 'pending';
    case Overdue = 'overdue';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnProgress => 'On Progress',
            self::Pending => 'Pending',
            self::Overdue => 'Overdue',
            self::Closed => 'Closed',
            self::Cancelled => 'Cancelled',
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
            self::Cancelled => 'danger',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])->all();
    }
}
