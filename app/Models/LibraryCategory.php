<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Corsec\Models\Concerns\HasAuditUsers;

class LibraryCategory extends Model
{
    use SoftDeletes, HasAuditUsers;

    protected $table = 'corsec_library_categories';

    protected $fillable = [
        'type',
        'name',
        'slug',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'status' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(LibraryItem::class, 'category_id');
    }
}
