<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Usermanagement\Models\User;

class MeetingDecision extends Model
{
    use SoftDeletes, HasAuditUsers;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_DROPPED = 'dropped';

    protected $table = 'corsec_meeting_decisions';

    protected $fillable = [
        'meeting_id',
        'decision_key',
        'root_decision_id',
        'source_decision_id',
        'decision_text',
        'owner_directorate_id',
        'pic_user_id',
        'target_date',
        'status',
        'closed_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'closed_at' => 'datetime',
        'root_decision_id' => 'integer',
        'source_decision_id' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function ownerDirectorate()
    {
        return $this->belongsTo(Directorate::class, 'owner_directorate_id');
    }

    public function picUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(DecisionUpdate::class, 'meeting_decision_id');
    }

    public function rootDecision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_decision_id');
    }

    public function sourceDecision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_decision_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachables(): MorphMany
    {
        return $this->morphMany(Attachable::class, 'attachable');
    }
}
