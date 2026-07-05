<?php

namespace App\Models;

use App\PreventiveMaintenanceSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreventiveMaintenanceSession extends Model
{
    use HasFactory;

    protected $fillable = ['department_id', 'location_id', 'started_by', 'started_at', 'completed_at', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'status' => PreventiveMaintenanceSessionStatus::class];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function assetChecks(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceAssetCheck::class, 'session_id');
    }

    public function refreshCompletionState(): void
    {
        if ($this->status !== PreventiveMaintenanceSessionStatus::Active || $this->assetChecks()->whereIn('status', ['pending', 'in_progress'])->exists()) {
            return;
        }

        $this->forceFill(['status' => PreventiveMaintenanceSessionStatus::Completed, 'completed_at' => now()])->save();
    }
}
