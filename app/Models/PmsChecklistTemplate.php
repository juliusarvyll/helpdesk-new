<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsChecklistTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PmsChecklistItem::class, 'template_id')->orderBy('sort_order');
    }

    public function assetChecks(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceAssetCheck::class, 'checklist_template_id');
    }
}
