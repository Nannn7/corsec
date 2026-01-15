<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LetterNumber extends Model
{
    protected $table = 'corsec_letter_numbers';

    protected $fillable = [
        'year',
        'sequence',
        'code',
        'number',
        'issued_at',
        'issued_by',
        'is_used',
        'used_at',
        'created_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'used_at' => 'datetime',
        'is_used' => 'boolean',
    ];

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outgoingLetter(): HasOne
    {
        return $this->hasOne(OutgoingLetter::class, 'letter_number_id');
    }
}
