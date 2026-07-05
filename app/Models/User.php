<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants
{
    use HasFactory, HasPanelShield, HasRoles, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'address', 'contact',
        'photo', 'department_id', 'position_id', 'role_id', 'status', 'is_deleted',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'integer',
            'is_deleted' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status === 1
            && $this->is_deleted === 0
            && $this->hasAnyRole(['super_admin', 'admin', 'technical_support', 'client', 'panel_user', 'job_order_manager', 'maintenance_staff']);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'department_user')
            ->wherePivot('is_deleted', 0);
    }

    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_technical_support', 'user_id', 'ticket_id')
            ->withTimestamps();
    }

    public function assignedJobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'assigned_to_user_id');
    }

    public function createdJobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class, 'created_by');
    }

    public function preventiveMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceSchedule::class, 'assigned_to_user_id');
    }

    public function preventiveMaintenanceSessions(): HasMany
    {
        return $this->hasMany(PreventiveMaintenanceSession::class, 'started_by');
    }

    public function assignedInventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'assigned_to_user_id');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function roleRelation(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function getTenants(Panel $panel): Collection
    {
        if ($this->hasRole('super_admin')) {
            return Department::where('is_deleted', 0)->get();
        }

        return $this->departments()->where('department.is_deleted', 0)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->departments()->whereKey($tenant)->exists();
    }

    public function scopeCanAccessDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where(function (Builder $query) use ($departmentId): void {
            $query
                ->where('department_id', $departmentId)
                ->orWhereHas('roles', fn (Builder $query): Builder => $query->where('name', 'super_admin'))
                ->orWhereHas('departments', fn (Builder $query): Builder => $query->whereKey($departmentId));
        });
    }
}
