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

    protected $table = 'corsec_decision_updates';

    protected $fillable = [
        'meeting_decision_id',
        'progress_percent',
        'status',
        'note',
        'updated_by',
        'authorized_at',
        'authorized_status',
        'authorized_by',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
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
