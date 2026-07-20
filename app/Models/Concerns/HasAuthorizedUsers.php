<?php

namespace Modules\Corsec\Models\Concerns;

use Modules\Usermanagement\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasAuthorizedUsers
{
    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }
}
