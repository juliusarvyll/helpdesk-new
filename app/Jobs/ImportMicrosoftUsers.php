<?php

namespace App\Jobs;

use App\MicrosoftGraphService;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImportMicrosoftUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    /**
     * @param  array<int, string>  $columns
     */
    public function __construct(
        public array $columns,
        public int $actorId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    public function handle(MicrosoftGraphService $graph): void
    {
        $actor = User::query()->find($this->actorId);

        Log::info('Microsoft users import job started.', [
            'actor_id' => $this->actorId,
            'columns' => $this->columns,
        ]);

        if ($actor) {
            $actor->notifyNow(Notification::make()
                ->title('Microsoft users import started')
                ->body('The queue worker is importing Microsoft users now.')
                ->info()
                ->toDatabase());
        }

        $result = $graph->importUsers($this->columns);

        Log::info('Microsoft users import completed.', [
            'actor_id' => $this->actorId,
            'columns' => $this->columns,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        if ($actor) {
            $actor->notifyNow(Notification::make()
                ->title('Microsoft users import completed')
                ->body("Imported {$result['imported']} users. Skipped {$result['skipped']} users without supported A3 licenses.")
                ->success()
                ->toDatabase());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Microsoft users import failed.', [
            'actor_id' => $this->actorId,
            'columns' => $this->columns,
            'exception' => $exception,
        ]);

        $actor = User::query()->find($this->actorId);

        if ($actor) {
            $actor->notifyNow(Notification::make()
                ->title('Microsoft users import failed')
                ->body($exception->getMessage())
                ->danger()
                ->toDatabase());
        }
    }
}
