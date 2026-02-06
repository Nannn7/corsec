<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class Bank extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    protected $table = 'corsec_banks';

    protected $fillable = [
        'uuid',
        'code',
        'swift_code',
        'name',
        'description',
        'status',
        'authorized_at',
        'authorized_status',
        'authorized_by',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'authorized_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function incomingLetters()
    {
        return $this->hasMany(IncomingLetter::class, 'counterparty_bank_id');
    }
}
