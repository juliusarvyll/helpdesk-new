<?php

namespace Database\Factories;

use App\Models\PmsChecklistItem;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceCheckResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreventiveMaintenanceCheckResultFactory extends Factory
{
    protected $model = PreventiveMaintenanceCheckResult::class;

    public function definition(): array
    {
        return ['asset_check_id' => PreventiveMaintenanceAssetCheck::factory(), 'checklist_item_id' => PmsChecklistItem::factory(), 'value' => 'pass'];
    }
}
