<?php

namespace App\Console\Commands;

use App\Models\PreventiveMaintenanceSchedule;
use App\PreventiveMaintenanceGenerationService;
use Illuminate\Console\Command;

class GeneratePreventiveMaintenanceWork extends Command
{
    protected $signature = 'maintenance:generate-preventive-work';

    protected $description = 'Generate due preventive-maintenance tickets and job orders';

    public function handle(PreventiveMaintenanceGenerationService $generator): int
    {
        $generated = 0;

        PreventiveMaintenanceSchedule::query()
            ->where('is_active', true)
            ->where('next_due_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($schedules) use ($generator, &$generated): void {
                foreach ($schedules as $schedule) {
                    $generator->generate($schedule);
                    $generated++;
                }
            });

        $this->info("Processed {$generated} due preventive-maintenance schedules.");

        return self::SUCCESS;
    }
}
