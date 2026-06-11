<?php

namespace App\Filament\Resources\QueueJobResource\Pages;

use App\Filament\Resources\QueueJobResource;
use App\Models\FailedJob;
use App\Models\QueueJob;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;
use Symfony\Component\Process\Process;

class ListQueueJobs extends ListRecords
{
    protected static string $resource = QueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('workerStatus')
                ->label('Worker Status')
                ->icon('heroicon-o-command-line')
                ->modalHeading('Queue Worker Status')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalWidth(MaxWidth::ThreeExtraLarge)
                ->modalContent(fn (): HtmlString => new HtmlString($this->workerStatusHtml())),
        ];
    }

    private function workerStatusHtml(): string
    {
        $pending = QueueJob::query()->count();
        $processing = QueueJob::query()->whereNotNull('reserved_at')->count();
        $failed = FailedJob::query()->count();
        $workers = $this->runningWorkers();

        $rows = collect($workers)
            ->map(fn (string $worker): string => '<tr><td class="whitespace-pre-wrap px-3 py-2 font-mono text-xs text-gray-950 dark:text-gray-100">'.e($worker).'</td></tr>')
            ->implode('');

        if ($rows === '') {
            $rows = '<tr><td class="px-3 py-3 text-sm text-danger-600 dark:text-danger-400">No running queue workers were detected on this server.</td></tr>';
        }

        return <<<HTML
            <div class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Pending Jobs</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-gray-100">{$pending}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Processing Jobs</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-gray-100">{$processing}</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Failed Jobs</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-gray-100">{$failed}</div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Running Workers</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">{$rows}</tbody>
                    </table>
                </div>
            </div>
        HTML;
    }

    /**
     * @return array<int, string>
     */
    private function runningWorkers(): array
    {
        $process = Process::fromShellCommandline("ps -eo pid,etime,cmd | grep '[q]ueue:work'");
        $process->run();

        if (! $process->isSuccessful()) {
            return [];
        }

        return collect(explode("\n", trim($process->getOutput())))
            ->filter()
            ->values()
            ->all();
    }
}
