<?php

namespace App;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

enum PreventiveMaintenanceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case SemiAnnual = 'semi_annual';
    case Annual = 'annual';
    case Custom = 'custom';

    public function nextDueDate(CarbonInterface $from, ?int $intervalValue = null): CarbonInterface
    {
        return match ($this) {
            self::Daily => $from->copy()->addDay(),
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonthNoOverflow(),
            self::Quarterly => $from->copy()->addMonthsNoOverflow(3),
            self::SemiAnnual => $from->copy()->addMonthsNoOverflow(6),
            self::Annual => $from->copy()->addYearNoOverflow(),
            self::Custom => $from->copy()->addDays($this->validatedInterval($intervalValue)),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly',
            self::SemiAnnual => 'Semi-Annual',
            self::Annual => 'Annual',
            self::Custom => 'Custom',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $frequency): array => [$frequency->value => $frequency->label()])->all();
    }

    private function validatedInterval(?int $intervalValue): int
    {
        if (! $intervalValue || $intervalValue < 1) {
            throw ValidationException::withMessages(['interval_value' => 'A custom maintenance frequency requires an interval of at least one day.']);
        }

        return $intervalValue;
    }
}
