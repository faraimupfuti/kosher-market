<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Intl\Countries;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $rows = [];

        foreach (Countries::getAlpha2Codes() as $code) {
            $rows[] = [
                'code' => strtoupper($code),
                'name' => Countries::getName($code, 'en'),
                'enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('countries')->upsert($chunk, ['code'], ['name', 'updated_at']);
        }
    }
}
