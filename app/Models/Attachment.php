<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;
use Modules\Corsec\Models\Concerns\HasUuidColumn;

class Attachment extends Model
{
    use SoftDeletes, HasUuidColumn, HasAuditUsers;

    protected $table = 'corsec_attachments';

    protected $fillable = [
        'uuid',
        'disk',
        'path',
        'original_name',
        'file_name',
        'mime',
        'size',
        'hash',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function attachables(): HasMany
    {
        return $this->hasMany(Attachable::class, 'attachment_id');
    }
}
