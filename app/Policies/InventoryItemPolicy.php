<?php

namespace App\Policies;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InventoryItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_inventory::item');
    }

    public function view(User $user, InventoryItem $inventoryItem): bool
    {
        if ($user->hasRole('client')) {
            return $user->can('view_inventory::item') && $inventoryItem->assigned_to_user_id === $user->id;
        }

        return $user->can('view_inventory::item');
    }

    public function create(User $user): bool
    {
        return $user->can('create_inventory::item');
    }

    public function update(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('update_inventory::item');
    }

    public function delete(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('delete_inventory::item');
    }

    public function assign(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('assign_inventory_item');
    }

    public function adjustStock(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('adjust_stock_inventory_item');
    }

    public function retire(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('retire_inventory_item');
    }

    public function viewTransactions(User $user, InventoryItem $inventoryItem): bool
    {
        return $user->can('view_any_inventory::transaction');
    }
}
