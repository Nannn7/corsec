<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;

class MeetingDecision extends Model
{
    use SoftDeletes, HasAuditUsers;

    protected $table = 'corsec_meeting_decisions';

    protected $fillable = [
        'meeting_id',
        'decision_text',
        'owner_directorate_id',
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

    public function updates(): HasMany
    {
        return $this->hasMany(DecisionUpdate::class, 'meeting_decision_id');
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
