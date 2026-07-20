<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class Meeting extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    public const TYPE_KOMISARIS = 'rapat_komisaris';
    public const TYPE_DIREKSI = 'rapat_direksi';
    public const TYPE_MANCOMM = 'rapat_mancomm';
    public const TYPE_DIREKTORAT = 'rapat_direktorat';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_WAITING_CORSEC_APPROVAL = 'waiting_corsec_approval';
    public const STATUS_RETURNED_BY_CORSEC = 'returned_by_corsec';
    public const STATUS_JADWAL_TERKIRIM = 'jadwal_terkirim';
    public const STATUS_PENDING_DIREKTORAT = 'pending_direktorat';
    public const STATUS_WAITING_DIREKTORAT_APPROVAL = 'waiting_direktorat_approval';
    public const STATUS_RETURNED_BY_DIREKTORAT = 'returned_by_direktorat';
    public const STATUS_DATA_TERKIRIM = 'data_terkirim';
    public const STATUS_PROSES_PEMBUATAN_NOTULEN = 'proses_pembuatan_notulen';
    public const STATUS_PROSES_SIRKULASI_TANDATANGAN = 'proses_sirkulasi_tandatangan';
    public const STATUS_NOTULEN_FINAL = 'notulen_final';
    public const STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT = 'proses_tindaklanjut_hasil_rapat';
    public const STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT = 'done_tindaklanjut_hasil_rapat';
    public const STATUS_CANCELLED_DIREKTORAT = 'cancelled_direktorat';
    public const STATUS_CLOSED_NOT_CONDUCTED = 'closed_not_conducted';

    public const RESPONSE_PENDING = 'pending';
    public const RESPONSE_ON_SCHEDULE = 'on_schedule';
    public const RESPONSE_CANCEL = 'cancel';
    public const RESPONSE_RESCHEDULE = 'reschedule';
    public const RESPONSE_NO_RESPONSE = 'no_response';

    protected $table = 'corsec_meetings';

    protected $fillable = [
        'uuid',
        'title',
        'meeting_at',
        'location',
        'meeting_type',
        'status',
        'description',
        'schedule_sent_at',
        'directorate_reminder_sent_at',
        'conducted_at',
        'finished_at',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'directorate_response_status',
        'directorate_response_note',
        'directorate_responded_at',
        'directorate_responded_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'meeting_at' => 'datetime',
        'schedule_sent_at' => 'datetime',
        'directorate_reminder_sent_at' => 'datetime',
        'conducted_at' => 'datetime',
        'finished_at' => 'datetime',
        'authorized_at' => 'datetime',
        'directorate_responded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function typeOptions(): array
    {
        return self::typeOptionsFromMasterData();
    }

    public static function typeOptionsFromMasterData(bool $activeOnly = false): array
    {
        try {
            $query = MeetingType::query()->orderBy('name');
            if ($activeOnly) {
                $query->where('status', true);
            }

            $options = $query->pluck('name', 'code')
                ->mapWithKeys(function ($name, $code) {
                    return [(string) $code => (string) $name];
                })
                ->all();

            if (!empty($options)) {
                return $options;
            }
        } catch (\Throwable) {
            // Fallback to static defaults while migration/table is not ready.
        }

        return [
            self::TYPE_KOMISARIS => 'Rapat Komisaris',
            self::TYPE_DIREKSI => 'Rapat Direksi',
            self::TYPE_MANCOMM => 'Rapat Management Committee',
            self::TYPE_DIREKTORAT => 'Rapat Direktorat',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_WAITING_CORSEC_APPROVAL => 'Menunggu Corporate Secretary',
            self::STATUS_RETURNED_BY_CORSEC => 'Returned Corporate Secretary',
            self::STATUS_JADWAL_TERKIRIM => 'Jadwal Terkirim',
            self::STATUS_PENDING_DIREKTORAT => 'Pending Direktorat',
            self::STATUS_WAITING_DIREKTORAT_APPROVAL => 'Menunggu Approval Direktorat',
            self::STATUS_RETURNED_BY_DIREKTORAT => 'Returned Direktorat',
            self::STATUS_DATA_TERKIRIM => 'Data Terkirim',
            self::STATUS_PROSES_PEMBUATAN_NOTULEN => 'Proses Pembuatan Notulen',
            self::STATUS_PROSES_SIRKULASI_TANDATANGAN => 'Proses Sirkulasi Tandatangan',
            self::STATUS_NOTULEN_FINAL => 'Notulen Final',
            self::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT => 'Proses Tindaklanjut Hasil Rapat',
            self::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT => 'Done Tindaklanjut Hasil Rapat',
            self::STATUS_CANCELLED_DIREKTORAT => 'Dibatalkan Direktorat',
            self::STATUS_CLOSED_NOT_CONDUCTED => 'Closed - Tidak Dilaksanakan',
        ];
    }

    public static function responseLabels(): array
    {
        return [
            self::RESPONSE_PENDING => 'Menunggu Tanggapan Direktorat',
            self::RESPONSE_ON_SCHEDULE => 'On Schedule',
            self::RESPONSE_CANCEL => 'Cancel',
            self::RESPONSE_RESCHEDULE => 'Reschedule',
            self::RESPONSE_NO_RESPONSE => 'Tidak Ada Tanggapan',
        ];
    }

    public static function finalStatuses(): array
    {
        return [
            self::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
            self::STATUS_CANCELLED_DIREKTORAT,
            self::STATUS_CLOSED_NOT_CONDUCTED,
        ];
    }

    public static function direktoratTypeCodes(): array
    {
        static $resolvedCodes = null;

        if (is_array($resolvedCodes)) {
            return $resolvedCodes;
        }

        $codes = collect([
            self::TYPE_DIREKTORAT,
        ]);

        try {
            $codes = $codes->merge(
                MeetingType::query()
                    ->where(function ($query) {
                        $query->where('code', self::TYPE_DIREKTORAT)
                            ->orWhereRaw('LOWER(name) LIKE ?', ['%rapat direktorat%']);
                    })
                    ->pluck('code')
                    ->all()
            );
        } catch (\Throwable) {
            // Ignore lookup failures and fallback to the constant value.
        }

        $resolvedCodes = $codes
            ->filter(fn($code) => trim((string) $code) !== '')
            ->map(fn($code) => Str::lower(trim((string) $code)))
            ->unique()
            ->values()
            ->all();

        return $resolvedCodes;
    }

    public static function isDirektoratTypeCode(?string $meetingType): bool
    {
        $normalizedMeetingType = Str::lower(trim((string) $meetingType));
        if ($normalizedMeetingType === '') {
            return false;
        }

        return in_array($normalizedMeetingType, self::direktoratTypeCodes(), true);
    }

    public function isDirektoratType(): bool
    {
        return self::isDirektoratTypeCode((string) $this->meeting_type);
    }

    public function directorateResponseDeadlineAt(): ?Carbon
    {
        if (!$this->meeting_at) {
            return null;
        }

        return $this->meeting_at->copy()->startOfDay()->subDay()->endOfDay();
    }

    public function isDirectorateResponseDeadlinePassed(?Carbon $referenceTime = null): bool
    {
        $deadline = $this->directorateResponseDeadlineAt();
        if (!$deadline) {
            return false;
        }

        return ($referenceTime ?? now())->greaterThan($deadline);
    }

    public function directorateResponseDeadlineLabel(string $format = 'd/m/Y H:i'): ?string
    {
        $deadline = $this->directorateResponseDeadlineAt();
        return $deadline ? $deadline->format($format) : null;
    }

    public function isAwaitingDirectorateResponse(): bool
    {
        if (!$this->isDirektoratType()) {
            return false;
        }

        $responseStatus = trim((string) ($this->directorate_response_status ?? ''));

        return in_array($responseStatus, ['', self::RESPONSE_PENDING], true)
            && in_array((string) $this->status, [
                self::STATUS_JADWAL_TERKIRIM,
                self::STATUS_PENDING_DIREKTORAT,
            ], true);
    }

    public function isDirectorateResponseReminderWindow(?Carbon $referenceTime = null): bool
    {
        if (!$this->isAwaitingDirectorateResponse() || !$this->meeting_at) {
            return false;
        }

        $reference = ($referenceTime ?? now())->copy();

        return $reference->isSameDay($this->meeting_at->copy()->subDay());
    }

    public function shouldAutoCloseForMissingDirectorateResponse(?Carbon $referenceTime = null): bool
    {
        if (!$this->isAwaitingDirectorateResponse() || !$this->meeting_at) {
            return false;
        }

        return ($referenceTime ?? now())->greaterThanOrEqualTo($this->meeting_at->copy()->startOfDay());
    }

    public function isDirectorateScheduleNotConducted(): bool
    {
        return (string) $this->status === self::STATUS_CLOSED_NOT_CONDUCTED;
    }

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

    public function directorateRespondedBy(): BelongsTo
    {
        return $this->belongsTo(\Modules\Usermanagement\Models\User::class, 'directorate_responded_by');
    }
}
