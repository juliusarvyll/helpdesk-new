<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveMaintenanceCheckResult extends Model
{
    use HasFactory;

    protected $fillable = ['asset_check_id', 'checklist_item_id', 'value', 'remarks'];

    public function assetCheck(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenanceAssetCheck::class, 'asset_check_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(PmsChecklistItem::class, 'checklist_item_id');
    }
}
