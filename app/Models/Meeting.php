<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class Meeting extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    protected $table = 'corsec_meetings';

    protected $fillable = [
        'uuid',
        'title',
        'meeting_at',
        'location',
        'meeting_type',
        'status',
        'description',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'meeting_at' => 'datetime',
        'authorized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function agendas(): HasMany
    {
        return $this->hasMany(MeetingAgenda::class, 'meeting_id')->orderBy('order_no');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(MeetingMaterial::class, 'meeting_id');
    }

    public function minutes(): HasOne
    {
        return $this->hasOne(MeetingMinutes::class, 'meeting_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class, 'meeting_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class, 'meeting_id');
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
