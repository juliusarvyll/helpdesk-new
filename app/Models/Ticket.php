<?php

namespace App\Models;

use App\TicketStatus;
use App\Notifications\TicketStatusChanged;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject', 'description', 'priority', 'issue',
        'issue_id', 'client_id', 'department_id',
        'support_assignment_status', 'assigned_at', 'start_time', 'end_time', 'status',
        'rate', 'technical_support_remarks', 'client_comments',
        'client_confirmation', 'created_ticket', 'created_by', 'asset_id', 'asset_name',
        'inventory_item_id', 'inventory_item_serial_number_id',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'assigned_at' => 'datetime',
            'rate' => 'integer',
            'client_confirmation' => 'integer',
            'status' => TicketStatus::class,
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(IssueList::class, 'issue_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
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

    public function technicalSupportUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_technical_support', 'ticket_id', 'user_id')
            ->withTimestamps();
    }

    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole('client')) {
            return $query->where(function (Builder $query) use ($user): void {
                $query->where('client_id', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        return $query;
    }

    public function scopeAssignedTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('technicalSupportUsers', fn (Builder $query) => $query->whereKey($user->id));
    }

    public function canTransitionTo(TicketStatus $newStatus): bool
    {
        if (! $this->status instanceof TicketStatus) {
            return false;
        }

        return $this->status->canTransitionTo($newStatus);
    }

    public function transitionTo(TicketStatus $newStatus): bool
    {
        if (! $this->canTransitionTo($newStatus)) {
            return false;
        }

        if ($newStatus === TicketStatus::Closed && blank($this->technical_support_remarks)) {
            return false;
        }

        $previousStatus = $this->status;

        $this->status = $newStatus;

        if ($newStatus === TicketStatus::OnProgress && ! $this->start_time) {
            $this->start_time = now();
        }

        if ($newStatus === TicketStatus::Closed && ! $this->end_time) {
            $this->end_time = now();
        }

        $saved = $this->save();

        if ($saved && $this->client && $previousStatus instanceof TicketStatus) {
            $this->client->notify(new TicketStatusChanged($this, $previousStatus, $newStatus));
        }

        return $saved;
    }

    public function syncAssignmentState(): bool
    {
        $isAssigned = $this->technicalSupportUsers()->exists();

        $this->support_assignment_status = $isAssigned ? 'Assigned' : 'Not Yet Assigned';

        if (Schema::hasColumn($this->getTable(), 'assigned_at')) {
            if ($isAssigned && ! $this->assigned_at) {
                $this->assigned_at = now();
            }

            if (! $isAssigned) {
                $this->assigned_at = null;
            }
        }

        return $this->save();
    }
}
