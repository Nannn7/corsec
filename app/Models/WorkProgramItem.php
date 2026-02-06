<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class WorkProgramItem extends Model
{
    protected $table = 'corsec_work_program_items';

    protected $fillable = [
        'work_program_id',
        'title',
        'description',
        'target_date',
        'weight',
        'status',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'weight' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class, 'work_program_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkProgramUpdate::class, 'work_program_item_id');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function attachables(): MorphMany
    {
        return $this->morphMany(Attachable::class, 'attachable');
    }
}
