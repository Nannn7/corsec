<?php

namespace Modules\Corsec\Models;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Corsec\Models\Concerns\HasAuthorizedUsers;

class WorkProgramUpdate extends Model
{
    use HasAuthorizedUsers;

    protected $table = 'corsec_work_program_updates';

    protected $fillable = [
        'work_program_item_id',
        'progress_percent',
        'status',
        'note',
        'updated_by',
        'authorized_at',
        'authorized_status',
        'authorized_by',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'authorized_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkProgramItem::class, 'work_program_item_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
