<?php

namespace App\Jobs;

use App\InventoryItemCsvImporter;
use App\Models\Department;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportInventoryItemsFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function __construct(
        public array $rows,
        public ?int $tenantId,
        public int $actorId,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [1, 5, 10];
    }

    public function handle(InventoryItemCsvImporter $importer): void
    {
        $tenant = $this->tenantId ? Department::query()->find($this->tenantId) : null;
        $actor = User::query()->findOrFail($this->actorId);
        $rows = $this->groupRowsByAssetTag($this->rows);
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $index => $rowData) {
            try {
                $importer->import($rowData, $tenant, $actor);
                $imported++;
            } catch (ValidationException $exception) {
                $skipped++;

                Log::warning('Inventory item CSV row skipped.', [
                    'actor_id' => $this->actorId,
                    'tenant_id' => $this->tenantId,
                    'row_number' => $index + 2,
                    'asset_tag' => $rowData['asset_tag'] ?? null,
                    'name' => $rowData['name'] ?? null,
                    'errors' => $exception->errors(),
                ]);
            }
        }

        Log::info('Inventory item CSV import completed.', [
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'rows' => count($this->rows),
            'grouped_rows' => count($rows),
            'imported' => $imported,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupRowsByAssetTag(array $rows): array
    {
        $groupedRows = [];

        foreach ($rows as $index => $row) {
            $assetTag = trim((string) ($row['asset_tag'] ?? ''));
            $key = $assetTag !== '' ? "asset:{$assetTag}" : "row:{$index}";

            if (! array_key_exists($key, $groupedRows)) {
                $groupedRows[$key] = $row;

                continue;
            }

            $groupedRows[$key]['quantity'] = $this->sumQuantities(
                $groupedRows[$key]['quantity'] ?? null,
                $row['quantity'] ?? null,
            );
            $groupedRows[$key]['serial_number'] = $this->mergeSerialNumbers(
                $groupedRows[$key]['serial_number'] ?? null,
                $row['serial_number'] ?? null,
            );
        }

        return array_values($groupedRows);
    }

    private function sumQuantities(mixed $left, mixed $right): int
    {
        return $this->rowQuantity($left) + $this->rowQuantity($right);
    }

    private function rowQuantity(mixed $quantity): int
    {
        if (blank($quantity)) {
            return 1;
        }

        return max((int) $quantity, 0);
    }

    private function mergeSerialNumbers(mixed $left, mixed $right): string
    {
        return collect([$left, $right])
            ->filter(fn (mixed $serialNumbers): bool => filled($serialNumbers))
            ->implode(',');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Inventory item CSV import failed.', [
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'rows' => count($this->rows),
            'exception' => $exception,
        ]);
    }
}
