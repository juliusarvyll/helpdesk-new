<?php

namespace App\Models;

use App\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItemSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_item_id',
        'serial_number',
        'status',
        'assigned_to_user_id',
        'location_id',
    ];

    protected static function booted(): void
    {
        static::saved(function (InventoryItemSerialNumber $serialNumber): void {
            $serialNumber->syncInventoryItemQuantity($serialNumber->getOriginal('inventory_item_id'));
        });

        static::deleted(function (InventoryItemSerialNumber $serialNumber): void {
            $serialNumber->syncInventoryItemQuantity($serialNumber->inventory_item_id);
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'inventory_item_serial_number_id');
    }

    public function openTickets(): HasMany
    {
        return $this->tickets()->where('status', '!=', TicketStatus::Closed->value);
    }

    public function hasOpenTicket(): bool
    {
        return $this->openTickets()->exists();
    }

    private function syncInventoryItemQuantity(?int $originalInventoryItemId): void
    {
        if ($originalInventoryItemId) {
            InventoryItem::query()->find($originalInventoryItemId)?->syncQuantityFromSerialNumbers();
        }

        if ($this->inventory_item_id && $this->inventory_item_id !== $originalInventoryItemId) {
            InventoryItem::query()->find($this->inventory_item_id)?->syncQuantityFromSerialNumbers();
        }
    }
}
