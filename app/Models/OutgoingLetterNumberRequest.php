<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingLetterNumberRequest extends Model
{
    protected $table = 'corsec_outgoing_letter_number_requests';

    protected $fillable = [
        'outgoing_letter_id',
        'requested_at',
        'requested_by',
        'note',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
    ];

    public function outgoingLetter(): BelongsTo
    {
        return $this->belongsTo(OutgoingLetter::class, 'outgoing_letter_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
