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
use Modules\Basicdata\Models\Branch;
use Modules\Usermanagement\Models\User;

class IncomingLetter extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    protected $table = 'corsec_incoming_letters';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ON_APPROVAL = 'on_approval';      // nunggu approval EO corp affair
    public const STATUS_DISPATCHED = 'dispatched';        // sudah disirkulasi ke direktorat
    public const STATUS_IN_PROGRESS = 'in_progress';      // direktorat sedang kerjain
    public const STATUS_WAITING_DIR_APPROVAL = 'waiting_dir_approval'; // nunggu approval EO+DD direktorat
    public const STATUS_WAITING_VERIFICATION = 'waiting_verification'; // nunggu verifikasi EO corp affair
    public const STATUS_VERIFIED = 'verified';            // verified, close
    public const STATUS_RETURNED = 'returned';            // balik ke staff + comment
    public const STATUS_REJECTED = 'rejected';            // ditolak EO corp affair

    protected $fillable = [
        'uuid',
        'registration_no',
        'external_letter_no',
        'letter_date',
        'subject',
        'summary',
        'sender',
        'sender_id',
        'sender_other',
        'counterparty_bank_id',
        'customer_branch_id',
        'letter_type_id',
        'letter_type_other',
        'received_date',
        'target_directorate_id',
        'last_routed_from_directorate_id',
        'last_routed_to_directorate_id',
        'last_routed_from_user_id',
        'last_routed_to_user_id',
        'last_routed_at',
        'last_route_note',
        'priority',
        'target_date',
        'followup_action',
        'followup_detail',
        'followup_note',
        'followup_submitted_at',
        'followup_submitted_by',
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
        'received_date' => 'date',
        'letter_date' => 'date',
        'target_date' => 'date',
        'followup_detail' => 'array',
        'followup_submitted_at' => 'datetime',
        'last_routed_at' => 'datetime',
        'authorized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function targetDirectorate()
    {
        return $this->belongsTo(Directorate::class, 'target_directorate_id');
    }

    public function lastRoutedFromDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'last_routed_from_directorate_id');
    }

    public function lastRoutedToDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'last_routed_to_directorate_id');
    }

    public function lastRoutedFromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_routed_from_user_id');
    }

    public function lastRoutedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_routed_to_user_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class, 'sender_id');
    }

    public function counterpartyBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'counterparty_bank_id');
    }

    public function customerBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'customer_branch_id');
    }

    public function letterType(): BelongsTo
    {
        return $this->belongsTo(LetterType::class, 'letter_type_id');
    }

    public function circulationDirectorates()
    {
        return $this->belongsToMany(
            Directorate::class,
            'corsec_incoming_letter_directorates',
            'incoming_letter_id',
            'directorate_id'
        )->withTimestamps();
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
