<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'role';

    protected $fillable = ['name', 'is_deleted'];

    protected function casts(): array
    {
        return [
            'is_deleted' => 'integer',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
