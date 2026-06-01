<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    protected $fillable = ['user_id', 'name', 'ip_address', 'mac_address', 'message'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
