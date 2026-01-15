<?php

namespace Modules\Corsec\Models\Concerns;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuditUsers
{
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
