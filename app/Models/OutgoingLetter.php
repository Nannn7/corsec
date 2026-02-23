<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;
use Modules\Usermanagement\Models\User;

class OutgoingLetter extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    protected $table = 'corsec_outgoing_letters';

    protected $fillable = [
        'uuid',
        'registration_no',
        'order_date',
        'subject',
        'letter_type_id',
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
        'final_upload_date',
        'letter_no',
        'number_requested_at',
        'number_requested_by',
        'number_request_note',
        'cancel_previous_status',
        'cancel_reason',
        'cancel_requested_at',
        'cancel_requested_by',
        'cancelled_at',
        'cancelled_by',
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
        'final_upload_date' => 'date',
        'number_requested_at' => 'datetime',
        'cancel_requested_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'authorized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'display_status',
        'display_status_label',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_WAITING_DIR_APPROVAL = 'waiting_dir_approval';
    public const STATUS_COMPLIANCE_REVIEW = 'compliance_review';
    public const STATUS_WAITING_COMPLIANCE_APPROVAL = 'waiting_compliance_approval';
    public const STATUS_WAITING_VERIFICATION = 'waiting_verification';
    public const STATUS_WAITING_FINAL_UPLOAD = 'waiting_final_upload';
    public const STATUS_WAITING_CANCEL_APPROVAL = 'waiting_cancel_approval';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FINAL_UPLOADED = 'final_uploaded'; // legacy alias

    public const DISPLAY_STATUS_DRAFT = 'draft';
    public const DISPLAY_STATUS_WAITING_DIR_APPROVAL = 'waiting_dir_approval';
    public const DISPLAY_STATUS_COMPLIANCE_REVIEW = 'compliance_review';
    public const DISPLAY_STATUS_WAITING_COMPLIANCE_APPROVAL = 'waiting_compliance_approval';
    public const DISPLAY_STATUS_WAITING_VERIFICATION = 'waiting_verification';
    public const DISPLAY_STATUS_WAITING_FINAL_UPLOAD = 'waiting_final_upload';
    public const DISPLAY_STATUS_WAITING_CANCEL_APPROVAL = 'waiting_cancel_approval';
    public const DISPLAY_STATUS_DONE = 'done';
    public const DISPLAY_STATUS_REVISI = 'revisi';
    public const DISPLAY_STATUS_CANCELLED = 'cancelled';

    public function requesterDirectorate()
    {
        return $this->belongsTo(Directorate::class, 'requester_directorate_id');
    }

    public function recipient()
    {
        return $this->belongsTo(Sender::class, 'recipient_id');
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class, 'letter_type_id');
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

    public function numberRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'number_requested_by');
    }

    public function cancelRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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

    public static function toDisplayStatus(?string $status): string
    {
        return match ((string) $status) {
            self::STATUS_DRAFT => self::DISPLAY_STATUS_DRAFT,
            self::STATUS_WAITING_DIR_APPROVAL => self::DISPLAY_STATUS_WAITING_DIR_APPROVAL,
            self::STATUS_COMPLIANCE_REVIEW => self::DISPLAY_STATUS_COMPLIANCE_REVIEW,
            self::STATUS_WAITING_COMPLIANCE_APPROVAL => self::DISPLAY_STATUS_WAITING_COMPLIANCE_APPROVAL,
            self::STATUS_WAITING_VERIFICATION,
            'numbering' => self::DISPLAY_STATUS_WAITING_VERIFICATION, // legacy state mapped to new flow
            self::STATUS_WAITING_CANCEL_APPROVAL => self::DISPLAY_STATUS_WAITING_CANCEL_APPROVAL,
            self::STATUS_WAITING_FINAL_UPLOAD,
            self::STATUS_FINAL_UPLOADED => self::DISPLAY_STATUS_WAITING_FINAL_UPLOAD,
            self::STATUS_VERIFIED => self::DISPLAY_STATUS_DONE,
            self::STATUS_RETURNED => self::DISPLAY_STATUS_REVISI,
            self::STATUS_CANCELLED => self::DISPLAY_STATUS_CANCELLED,
            default => self::DISPLAY_STATUS_DRAFT,
        };
    }

    public static function displayStatusLabel(?string $status): string
    {
        return match (self::toDisplayStatus($status)) {
            self::DISPLAY_STATUS_DRAFT => 'Draft',
            self::DISPLAY_STATUS_WAITING_DIR_APPROVAL => 'Approval EO dan DD Direktorat',
            self::DISPLAY_STATUS_COMPLIANCE_REVIEW => 'Review Direktorat Kepatuhan',
            self::DISPLAY_STATUS_WAITING_COMPLIANCE_APPROVAL => 'Approval EO dan DD Kepatuhan',
            self::DISPLAY_STATUS_WAITING_VERIFICATION => 'Verifikasi EO Corp Affair',
            self::DISPLAY_STATUS_WAITING_FINAL_UPLOAD => 'Final Upload',
            self::DISPLAY_STATUS_WAITING_CANCEL_APPROVAL => 'Approval Pembatalan EO Direktorat',
            self::DISPLAY_STATUS_DONE => 'Done',
            self::DISPLAY_STATUS_REVISI => 'Revisi',
            self::DISPLAY_STATUS_CANCELLED => 'Cancelled',
            default => 'Draft',
        };
    }

    public function getDisplayStatusAttribute(): string
    {
        return self::toDisplayStatus($this->status);
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return self::displayStatusLabel($this->status);
    }
}
