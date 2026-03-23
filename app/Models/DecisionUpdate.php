<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;

class DecisionUpdate extends Model
{
    use HasAuthorizedUsers;

    public const TYPE_PROGRESS = 'progress';
    public const TYPE_DONE = 'done';
    public const TYPE_CONTINUOUS = 'continuous';
    public const TYPE_DROP = 'drop';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CONTINUOUS = 'continuous';
    public const STATUS_DONE = 'done';
    public const STATUS_DROPPED = 'dropped';

    protected $table = 'corsec_decision_updates';

    protected $fillable = [
        'meeting_decision_id',
        'progress_percent',
        'update_type',
        'status',
        'note',
        'happened_at',
        'is_on_target',
        'reason',
        'updated_by',
        'authorized_at',
        'authorized_status',
        'authorized_by',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'happened_at' => 'date',
        'is_on_target' => 'boolean',
        'authorized_at' => 'datetime',
    ];

    public function decision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'meeting_decision_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachables(): MorphMany
    {
        return $this->morphMany(Attachable::class, 'attachable');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
