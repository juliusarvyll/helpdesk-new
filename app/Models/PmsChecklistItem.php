<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = ['template_id', 'label', 'input_type', 'options', 'is_required', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'sort_order' => 'integer'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PmsChecklistTemplate::class, 'template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceCheckResult::class, 'checklist_item_id');
    }
}
