<?php

namespace Modules\Corsec\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Usermanagement\Models\PermissionGroup;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $value) {
            $group = PermissionGroup::withTrashed()->updateOrCreate(
                ['name' => $value['name']],
                ['slug' => $value['slug']]
            );

            if ($group->trashed()) {
                $group->restore();
            }
        }
    }

    public function data(): array
    {
        return [
            ['name' => 'corsec', 'slug' => 'corsec'],
            ['name' => 'directorate', 'slug' => 'directorate'],
            ['name' => 'letter-type', 'slug' => 'letter-type'],
            ['name' => 'sender', 'slug' => 'sender'],
            ['name' => 'meeting-type', 'slug' => 'meeting-type'],
        ];
    }
}
