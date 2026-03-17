<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Usermanagement\Models\User;

class MeetingAgenda extends Model
{
    protected $table = 'corsec_meeting_agendas';

    protected $fillable = [
        'meeting_id',
        'order_no',
        'title',
        'description',
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
}
