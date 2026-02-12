<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
        'conducted_at',
        'finished_at',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'meeting_at' => 'datetime',
        'schedule_sent_at' => 'datetime',
        'conducted_at' => 'datetime',
        'finished_at' => 'datetime',
        'authorized_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public static function typeOptions(): array
    {
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
            self::STATUS_WAITING_CORSEC_APPROVAL => 'Menunggu Approval EO + Kepala Corsec',
            self::STATUS_RETURNED_BY_CORSEC => 'Returned EO + Kepala Corsec',
            self::STATUS_JADWAL_TERKIRIM => 'Jadwal Terkirim',
            self::STATUS_PENDING_DIREKTORAT => 'Pending Direktorat',
            self::STATUS_WAITING_DIREKTORAT_APPROVAL => 'Menunggu Approval EO + DD Direktorat',
            self::STATUS_RETURNED_BY_DIREKTORAT => 'Returned EO + DD Direktorat',
            self::STATUS_DATA_TERKIRIM => 'Data Terkirim',
            self::STATUS_PROSES_PEMBUATAN_NOTULEN => 'Proses Pembuatan Notulen',
            self::STATUS_PROSES_SIRKULASI_TANDATANGAN => 'Proses Sirkulasi Tandatangan',
            self::STATUS_NOTULEN_FINAL => 'Notulen Final',
            self::STATUS_PROSES_TINDAKLANJUT_HASIL_RAPAT => 'Proses Tindaklanjut Hasil Rapat',
            self::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT => 'Done Tindaklanjut Hasil Rapat',
        ];
    }

    public static function finalStatuses(): array
    {
        return [
            self::STATUS_DONE_TINDAKLANJUT_HASIL_RAPAT,
        ];
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
}
