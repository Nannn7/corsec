<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Modules\Usermanagement\Models\User;

class Directorate extends Model
{
    use SoftDeletes;

    protected $table = 'corsec_directorates';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'status',
        'is_meeting_operational',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_meeting_operational' => 'boolean',
        'authorized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    // ===== Relations
    public function users()
    {
        return $this->hasMany(User::class, 'directorate_id');
    }

    public function incomingLetters()
    {
        return $this->hasMany(IncomingLetter::class, 'target_directorate_id');
    }

    public function incomingLetterCirculations()
    {
        return $this->belongsToMany(
            IncomingLetter::class,
            'corsec_incoming_letter_directorates',
            'directorate_id',
            'incoming_letter_id'
        );
    }

    public function outgoingLetters()
    {
        return $this->hasMany(OutgoingLetter::class, 'requester_directorate_id');
    }

    public function meetingAgendas()
    {
        return $this->hasMany(MeetingAgenda::class, 'owner_directorate_id');
    }

    public function meetingDecisions()
    {
        return $this->hasMany(MeetingDecision::class, 'owner_directorate_id');
    }

    public function meetingParticipants()
    {
        return $this->hasMany(MeetingParticipant::class, 'directorate_id');
    }

    public function workPrograms()
    {
        return $this->hasMany(WorkProgram::class, 'directorate_id');
    }
}
