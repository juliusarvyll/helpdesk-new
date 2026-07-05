<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\InventoryItemSerialNumber;
use App\Models\PmsChecklistTemplate;
use App\Models\PreventiveMaintenanceAssetCheck;
use App\Models\PreventiveMaintenanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class PreventiveMaintenanceAssetCheckFactory extends Factory
{
    protected $model = PreventiveMaintenanceAssetCheck::class;

    public function definition(): array
    {
        $item = InventoryItem::factory()->itAsset();

        return ['session_id' => PreventiveMaintenanceSession::factory(), 'inventory_item_id' => $item, 'inventory_item_serial_number_id' => InventoryItemSerialNumber::factory()->for($item), 'checklist_template_id' => PmsChecklistTemplate::factory(), 'status' => 'pending'];
    }
}
