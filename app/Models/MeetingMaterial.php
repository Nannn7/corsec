<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;

class MeetingMaterial extends Model
{
    use HasAuthorizedUsers;

    protected $table = 'corsec_meeting_materials';

    protected $fillable = [
        'meeting_id',
        'agenda_id',
        'attachment_id',
        'uploaded_by',
        'uploaded_at',
        'authorized_at',
        'authorized_status',
        'authorized_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'authorized_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    public function agenda(): BelongsTo
    {
        return $this->belongsTo(MeetingAgenda::class, 'agenda_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
