<?php

namespace App;

enum PreventiveMaintenanceAssetCheckStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Passed = 'passed';
    case Failed = 'failed';
    case NeedsRepair = 'needs_repair';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Passed => 'Passed',
            self::Failed => 'Failed',
            self::NeedsRepair => 'Needs Repair',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::InProgress => 'warning',
            self::Passed => 'success',
            self::Failed, self::NeedsRepair => 'danger',
        };
    }

    public static function resultOptions(): array
    {
        return [self::Passed->value => self::Passed->label(), self::Failed->value => self::Failed->label(), self::NeedsRepair->value => self::NeedsRepair->label()];
    }
}
