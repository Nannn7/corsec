<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Usermanagement\Models\User;

class MeetingParticipant extends Model
{
    protected $table = 'corsec_meeting_participants';

    protected $fillable = [
        'meeting_id',
        'directorate_id',
        'note',
        'created_by',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'directorate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}