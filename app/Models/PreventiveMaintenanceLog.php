<?php

namespace App\Models;

use App\PreventiveMaintenanceLogStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveMaintenanceLog extends Model
{
    use HasFactory;

    protected $fillable = ['schedule_id', 'ticket_id', 'job_order_id', 'generated_at', 'completed_at', 'status', 'remarks'];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime', 'completed_at' => 'datetime', 'status' => PreventiveMaintenanceLogStatus::class];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenanceSchedule::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
