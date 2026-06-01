<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueList extends Model
{
    use HasFactory;

    protected $table = 'issue_list';

    protected $fillable = ['issue_category_id', 'issue', 'is_deleted'];

    protected $casts = [
        'is_deleted' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class, 'issue_category_id');
    }
}
