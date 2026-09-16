<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            OrderSeeder::class,
            LanguageSeeder::class,
            PaymentGatewaySeeder::class,
            PaymentSeeder::class,
            RefundSeeder::class,
        ]);
    }
}
