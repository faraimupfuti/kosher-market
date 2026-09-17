<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Only safe, deterministic configuration is seeded by default.
        // Demo orders, payments and refunds are intentionally excluded.
        $this->call([
            AdminSeeder::class,
            LanguageSeeder::class,
        ]);
    }
}
