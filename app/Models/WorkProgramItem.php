<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Modules\Usermanagement\Models\User;

class WorkProgramItem extends Model
{
    protected $table = 'corsec_work_program_items';

    public const STATUS_PROCESS_ON_TARGET = 'process_on_target';
    public const STATUS_DONE_ON_TARGET = 'done_on_target';
    public const STATUS_DONE_OVER_TARGET = 'done_over_target';
    public const STATUS_UNDONE = 'undone';

    protected $fillable = [
        'work_program_id',
        'title',
        'description',
        'initial_target_date',
        'target_date',
        'weight',
        'status',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'initial_target_date' => 'date',
        'target_date' => 'date',
        'weight' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function program(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
