<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = Str::lower(trim((string) env('ADMIN_EMAIL')));
        $password = (string) env('ADMIN_PASSWORD');

        if ($email === '' || $password === '') {
            $this->command?->warn('AdminSeeder skipped: ADMIN_EMAIL and ADMIN_PASSWORD must be set.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('ADMIN_EMAIL must be a valid email address.');
        }

        if (strlen($password) < 16) {
            throw new \InvalidArgumentException('ADMIN_PASSWORD must contain at least 16 characters.');
        }

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->name = $admin->name ?: 'Kosher Market Administrator';
        $admin->password = Hash::make($password);
        $admin->is_admin = true;
        $admin->email_verified_at = $admin->email_verified_at ?: now();
        $admin->save();

        $this->command?->info("Admin account provisioned: {$email}");
    }
}
