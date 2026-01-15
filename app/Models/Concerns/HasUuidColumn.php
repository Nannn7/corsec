<?php

namespace Modules\Corsec\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuidColumn
{
    protected static function bootHasUuidColumn(): void
    {
        static::creating(function ($model) {
            if ($model->getAttribute('uuid') === null) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
    }
}
