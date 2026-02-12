<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class MeetingMinutes extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';

    protected $table = 'corsec_meeting_minutes';

    protected $fillable = [
        'meeting_id',
        'minutes_text',
        'minutes_attachment_id',
        'final_minutes_attachment_id',
        'status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'circulated_at',
        'finalized_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'circulated_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function minutesAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'minutes_attachment_id');
    }

    public function finalMinutesAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'final_minutes_attachment_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
