<?php

namespace App\Models;

use App\PreventiveMaintenanceFrequency;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveMaintenanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id', 'inventory_item_id', 'inventory_item_serial_number_id', 'title', 'description',
        'frequency', 'interval_value', 'starts_at', 'next_due_at', 'last_generated_at',
        'assigned_to_user_id', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => PreventiveMaintenanceFrequency::class,
            'starts_at' => 'datetime',
            'next_due_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
            'interval_value' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function inventoryItemSerialNumber(): BelongsTo
    {
        return $this->belongsTo(InventoryItemSerialNumber::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceLog::class, 'schedule_id');
    }

    public function nextDueAfterGeneration(): CarbonInterface
    {
        return $this->frequency->nextDueDate($this->next_due_at, $this->interval_value);
    }
}
