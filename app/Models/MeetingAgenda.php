<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Usermanagement\Models\User;

class MeetingAgenda extends Model
{
    protected $table = 'corsec_meeting_agendas';

    protected $fillable = [
        'meeting_id',
        'order_no',
        'title',
        'description',
        'minutes_discussion',
        'owner_directorate_id',
        'pic_user_id',
        'source_decision_id',
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

    public function sourceDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'source_decision_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(MeetingMaterial::class, 'agenda_id');
    }

    public function minutesDecision(): HasOne
    {
        return $this->hasOne(MeetingDecision::class, 'agenda_id');
    }

    public function attachables(): MorphMany
    {
        return $this->morphMany(Attachable::class, 'attachable');
    }
}
