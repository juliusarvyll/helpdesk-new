<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_category_id',
        'asset_tag',
        'name',
        'description',
        'status',
        'quantity',
        'unit',
        'location_id',
        'assigned_to_user_id',
        'department_id',
        'current_ticket_id',
        'metadata',
        'purchased_at',
        'warranty_expires_at',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'purchased_at' => 'date',
            'warranty_expires_at' => 'date',
            'is_deleted' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function currentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'current_ticket_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(InventoryItemSerialNumber::class);
    }

    public function syncQuantityFromSerialNumbers(): void
    {
        $quantity = $this->serialNumbers()->count();

        if ($this->quantity === $quantity) {
            return;
        }

        $this->forceFill(['quantity' => $quantity])->save();
    }
}
