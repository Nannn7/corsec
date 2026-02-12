<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class LetterType extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers, HasAuthorizedUsers;

    public const SCOPE_IN = 'in';
    public const SCOPE_OUT = 'out';

    protected $table = 'corsec_letter_types';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'scope',
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
        return $this->hasMany(IncomingLetter::class, 'letter_type_id');
    }

    public function outgoingLetters()
    {
        return $this->hasMany(OutgoingLetter::class, 'letter_type_id');
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        if ($scope === self::SCOPE_IN) {
            return $query->where(function (Builder $inner) {
                $inner->where('scope', self::SCOPE_IN)->orWhereNull('scope');
            });
        }

        return $query->where('scope', $scope);
    }

    public function scopeIncoming(Builder $query): Builder
    {
        return $query->forScope(self::SCOPE_IN);
    }

    public function scopeOutgoing(Builder $query): Builder
    {
        return $query->forScope(self::SCOPE_OUT);
    }
}
