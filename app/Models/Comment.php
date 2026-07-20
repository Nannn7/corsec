<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Corsec\Models\Concerns\HasAuditUsers;

class Comment extends Model
{
    use SoftDeletes, HasAuditUsers;

    protected $table = 'corsec_comments';

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'body',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}
