<?php

namespace App\Observers;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;

class InventoryItemObserver
{
    public function creating(InventoryItem $inventoryItem): void
    {
        if ($inventoryItem->isDirty('is_it_asset')) {
            return;
        }

        $inventoryItem->is_it_asset = (bool) InventoryCategory::query()->whereKey($inventoryItem->inventory_category_id)->value('is_it_asset');
    }
}
