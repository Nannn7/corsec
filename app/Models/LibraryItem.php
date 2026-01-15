<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Corsec\Models\Concerns\HasAuditUsers;

class LibraryItem extends Model
{
    use SoftDeletes, HasAuditUsers;

    protected $table = 'corsec_library_items';

    protected $fillable = [
        'category_id',
        'title',
        'description',
        'item_type',
        'url',
        'attachment_id',
        'published_at',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'category_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'attachment_id');
    }
}
