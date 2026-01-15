<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachable extends Model
{
    protected $table = 'corsec_attachables';

    protected $fillable = [
        'attachment_id',
        'attachable_type',
        'attachable_id',
        'category',
        'note',
        'created_by',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
