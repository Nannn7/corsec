<?php

namespace Modules\Corsec\Database\Seeders;

use Illuminate\Database\Seeder;

class CorsecDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CorsecMasterDataSeeder::class,
        ]);
    }
}
