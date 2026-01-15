<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Usermanagement\Models\User;


/**
 * Model untuk mengelola approval requests
 *
 * @property string $id
 * @property string $model
 * @property string $action
 * @property string|null $target_id
 * @property array|null $request_old
 * @property array|null $request_new
 * @property string $status
 * @property string|null $description
 * @property string|null $review_notes
 * @property string|null $checksum
 * @property int|null $version
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 * @property \Carbon\Carbon|null $authorized_at
 * @property string|null $created_by
 * @property string|null $updated_by
 * @property string|null $deleted_by
 * @property string|null $authorized_by
 * @property string|null $reviewer_ip
 * @property string|null $reviewer_agent
 */
class ApprovalRequest extends Base
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'approval_requests';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'model',
        'action',
        'target_id',
        'request_old',
        'request_new',
        'status',
        'description',
        'review_notes',
        'checksum',
        'version',
        'authorized_at',
        'authorized_by',
        'reviewer_ip',
        'reviewer_agent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'request_old'   => 'array',
        'request_new'   => 'array',
        'version'       => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'deleted_at'    => 'datetime',
        'authorized_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'reviewer_ip',
        'reviewer_agent',
    ];

    /**
     * Konstanta untuk action types
     */
    const ACTION_CREATE = 'create';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';

    /**
     * Konstanta untuk status types
     */
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Boot method untuk generate UUID
     */
    protected static function boot()
    {
        parent::boot();
    }

    /**
     * Scope untuk filter berdasarkan status
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan model
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByModel($query, $model)
    {
        return $query->where('model', $model);
    }

    /**
     * Scope untuk filter berdasarkan action
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope untuk approval requests yang pending
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope untuk approval requests yang sudah diapprove
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope untuk approval requests yang ditolak
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Accessor untuk mendapatkan status badge class
     *
     * @return string
     */
    public function getStatusBadgeAttribute()
    {
        return match ($this->status) {
            self::STATUS_PENDING  => 'badge-warning',
            self::STATUS_APPROVED => 'badge-success',
            self::STATUS_REJECTED => 'badge-danger',
            default               => 'badge-secondary'
        };
    }

    /**
     * Accessor untuk mendapatkan action badge class
     *
     * @return string
     */
    public function getActionBadgeAttribute()
    {
        return match ($this->action) {
            self::ACTION_CREATE => 'badge-primary',
            self::ACTION_UPDATE => 'badge-info',
            self::ACTION_DELETE => 'badge-danger',
            default             => 'badge-secondary'
        };
    }
    /**
     * Method untuk generate checksum
     *
     * @return string
     */
    public function generateChecksum()
    {
        $data = [
            'model'       => $this->model,
            'action'      => $this->action,
            'target_id'   => $this->target_id,
            'request_old' => $this->request_old,
            'request_new' => $this->request_new,
        ];

        return hash('sha256', json_encode($data));
    }

    public function authorizer()
    {
        return $this->hasOne(User::class, 'id', 'authorized_by');
    }
}
