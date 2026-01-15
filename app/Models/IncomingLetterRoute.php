<?php

namespace Modules\Corsec\Models;

use Modules\Corsec\Models\Directorate;
use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingLetterRoute extends Model
{
    protected $table = 'corsec_incoming_letter_routes';

    protected $fillable = [
        'incoming_letter_id',
        'from_directorate_id',
        'to_directorate_id',
        'from_user_id',
        'to_user_id',
        'note',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(IncomingLetter::class, 'incoming_letter_id');
    }

    public function fromDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'from_directorate_id');
    }

    public function toDirectorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class, 'to_directorate_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
