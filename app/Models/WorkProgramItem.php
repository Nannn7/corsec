<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'created_by',
    ];

    protected $casts = [
        'target_date' => 'date',
        'weight' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(WorkProgram::class, 'work_program_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(WorkProgramUpdate::class, 'work_program_item_id');
    }
}
