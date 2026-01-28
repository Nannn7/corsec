<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class OutgoingLetter extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    protected $table = 'corsec_outgoing_letters';

    protected $fillable = [
        'uuid',
        'registration_no',
        'order_date',
        'subject',
        'requester_directorate_id',
        'recipient_id',
        'recipient_other',
        'summary',
        'perihal_type',
        'perihal_incoming_letter_id',
        'perihal_text',
        'note',
        'draft_attachment_id',
        'compliance_attachment_id',
        'final_attachment_id',
        'letter_number_id',
        'letter_no',
        'need_compliance_review',
        'status',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'need_compliance_review' => 'boolean',
        'order_date' => 'date',
        'authorized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_WAITING_DIR_APPROVAL = 'waiting_dir_approval';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLIANCE_REVIEW = 'compliance_review';
    public const STATUS_WAITING_COMPLIANCE_APPROVAL = 'waiting_compliance_approval';
    public const STATUS_NUMBERING = 'numbering';
    public const STATUS_WAITING_VERIFICATION = 'waiting_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FINAL_UPLOADED = 'final_uploaded';

    public function requesterDirectorate()
    {
        return $this->belongsTo(Directorate::class, 'requester_directorate_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Sender::class, 'recipient_id');
    }

    public function perihalIncomingLetter()
    {
        return $this->belongsTo(IncomingLetter::class, 'perihal_incoming_letter_id');
    }

    public function draftAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'draft_attachment_id');
    }

    public function complianceAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'compliance_attachment_id');
    }

    public function finalAttachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'final_attachment_id');
    }

    public function letterNumber(): BelongsTo
    {
        return $this->belongsTo(LetterNumber::class, 'letter_number_id');
    }

    public function numberRequests(): HasMany
    {
        return $this->hasMany(OutgoingLetterNumberRequest::class, 'outgoing_letter_id');
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
