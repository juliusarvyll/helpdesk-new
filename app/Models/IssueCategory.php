<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IssueCategory extends Model
{
    use HasFactory;

    protected $table = 'issue_category';

    protected $fillable = ['name', 'is_deleted'];

    protected $casts = [
        'is_deleted' => 'integer',
    ];

    public function issueList(): HasMany
    {
        return $this->hasMany(IssueList::class);
    }
}
