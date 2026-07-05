<?php

namespace App\Models;

use App\PreventiveMaintenanceAssetCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveMaintenanceAssetCheck extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id', 'inventory_item_id', 'inventory_item_serial_number_id', 'checked_by',
        'checklist_template_id', 'status', 'started_at', 'completed_at', 'remarks', 'ticket_id',
    ];

    protected function casts(): array
    {
        return ['status' => PreventiveMaintenanceAssetCheckStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenanceSession::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function serialNumber(): BelongsTo
    {
        return $this->belongsTo(InventoryItemSerialNumber::class, 'inventory_item_serial_number_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(PmsChecklistTemplate::class, 'checklist_template_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceCheckResult::class, 'asset_check_id');
    }
}
