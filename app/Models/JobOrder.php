<?php

namespace App\Models;

use App\JobOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id', 'client_id', 'created_by', 'inventory_item_id', 'inventory_item_serial_number_id',
        'subject', 'description', 'priority', 'status', 'assigned_to_user_id', 'source',
        'requested_by_name', 'started_at', 'completed_at', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobOrderStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function requestor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function preventiveMaintenanceLogs(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceLog::class);
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [JobOrderStatus::Closed->value, JobOrderStatus::Cancelled->value]);
    }

    public function canTransitionTo(JobOrderStatus $newStatus): bool
    {
        if (! $this->status instanceof JobOrderStatus) {
            return false;
        }

        return match ($this->status) {
            JobOrderStatus::Active => in_array($newStatus, [JobOrderStatus::OnProgress, JobOrderStatus::Pending, JobOrderStatus::Cancelled], true),
            JobOrderStatus::OnProgress => in_array($newStatus, [JobOrderStatus::Pending, JobOrderStatus::Overdue, JobOrderStatus::Closed, JobOrderStatus::Cancelled], true),
            JobOrderStatus::Pending => in_array($newStatus, [JobOrderStatus::OnProgress, JobOrderStatus::Overdue, JobOrderStatus::Closed, JobOrderStatus::Cancelled], true),
            JobOrderStatus::Overdue => in_array($newStatus, [JobOrderStatus::OnProgress, JobOrderStatus::Closed, JobOrderStatus::Cancelled], true),
            JobOrderStatus::Closed => $newStatus === JobOrderStatus::Active,
            JobOrderStatus::Cancelled => false,
        };
    }

    public function transitionTo(JobOrderStatus $newStatus): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            return false;
        }

        $this->status = $newStatus;

        if ($newStatus === JobOrderStatus::OnProgress && ! $this->started_at) {
            $this->started_at = now();
        }

        if ($newStatus === JobOrderStatus::Closed && ! $this->completed_at) {
            $this->completed_at = now();
        }

        if ($newStatus === JobOrderStatus::Active) {
            $this->started_at = null;
            $this->completed_at = null;
        }

        return $this->save();
    }
}
