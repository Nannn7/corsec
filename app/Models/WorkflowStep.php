<?php

namespace Modules\Corsec\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class WorkflowStep extends Model
{
    protected $table = 'corsec_workflow_steps';

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name',
        'role_id',
        'can_return',
        'sla_days',
    ];

    protected $casts = [
        'can_return' => 'boolean',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
