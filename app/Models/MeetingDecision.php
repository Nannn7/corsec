<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Schema;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Usermanagement\Models\User;

class MeetingDecision extends Model
{
    use SoftDeletes, HasAuditUsers;

    protected static ?bool $supportsSupportUsersTableCache = null;

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_CONTINUOUS = 'continuous';
    public const STATUS_DONE = 'done';
    public const STATUS_DROPPED = 'dropped';

    protected $table = 'corsec_meeting_decisions';

    protected $fillable = [
        'meeting_id',
        'decision_key',
        'issue_key',
        'root_decision_id',
        'source_decision_id',
        'decision_text',
        'owner_directorate_id',
        'pic_user_id',
        'target_date',
        'first_discussed_at',
        'last_discussed_at',
        'discussion_count',
        'latest_update_at',
        'latest_update_note',
        'latest_progress_percent',
        'aging_days',
        'aging_bucket',
        'status',
        'closed_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'first_discussed_at' => 'date',
        'last_discussed_at' => 'date',
        'discussion_count' => 'integer',
        'latest_update_at' => 'date',
        'latest_progress_percent' => 'integer',
        'aging_days' => 'integer',
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

    public function occurrences(): HasMany
    {
        return $this->hasMany(MeetingDecisionOccurrence::class, 'root_decision_id', 'root_decision_id');
    }

    public function ownOccurrence(): HasOne
    {
        return $this->hasOne(MeetingDecisionOccurrence::class, 'meeting_decision_id');
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

    public function supportDirectorates(): BelongsToMany
    {
        return $this->belongsToMany(
            Directorate::class,
            'corsec_meeting_decision_support_directorates',
            'meeting_decision_id',
            'directorate_id'
        )->withTimestamps();
    }

    public function supportUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'corsec_meeting_decision_support_users',
            'meeting_decision_id',
            'user_id'
        )->withTimestamps();
    }

    public static function supportsSupportUsersTable(): bool
    {
        if (self::$supportsSupportUsersTableCache !== null) {
            return self::$supportsSupportUsersTableCache;
        }

        return self::$supportsSupportUsersTableCache = Schema::hasTable('corsec_meeting_decision_support_users');
    }

    public function isOpenStatus(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_CONTINUOUS,
        ], true);
    }
}
