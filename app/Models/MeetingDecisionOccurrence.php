<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingDecisionOccurrence extends Model
{
    protected $table = 'corsec_meeting_decision_occurrences';

    protected $fillable = [
        'root_decision_id',
        'meeting_decision_id',
        'meeting_id',
        'source_decision_id',
        'occurred_at',
        'status_snapshot',
        'progress_snapshot',
        'note_snapshot',
        'created_by',
    ];

    protected $casts = [
        'root_decision_id' => 'integer',
        'meeting_decision_id' => 'integer',
        'meeting_id' => 'integer',
        'source_decision_id' => 'integer',
        'occurred_at' => 'date',
        'progress_snapshot' => 'integer',
    ];

    public function rootDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'root_decision_id');
    }

    public function meetingDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'meeting_decision_id');
    }

    public function sourceDecision(): BelongsTo
    {
        return $this->belongsTo(MeetingDecision::class, 'source_decision_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Modules\Usermanagement\Models\User::class, 'created_by');
    }
}
