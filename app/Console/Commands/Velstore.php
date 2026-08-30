<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class Velstore extends Command
{
    protected $signature = 'install:kosher-market {--locale= : Set the application locale} {--with-import : Import default data}';

    protected $description = 'Install Kosher Market, a Bitcoin-only multi-vendor marketplace.';

    public function handle()
    {
        $this->info('Installing Kosher Market...');
        $this->info('Running composer dump-autoload...');
        exec('composer dump-autoload');
        $this->call('migrate');
        $this->createAdminUser();

        $availableLocales = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German'];
        $locale = $this->option('locale') ?: $this->choice('Please select a locale', array_keys($availableLocales), 'en');
        if (! array_key_exists($locale, $availableLocales)) {
            $this->error("Invalid locale '{$locale}'.");
            return 1;
        }

        if ($this->option('with-import')) {
            $this->call('data:import');
        }

        $this->updateEnvFile('APP_LOCALE', $locale);
        Artisan::call('key:generate');
        Artisan::call('storage:link');
        $this->info('Kosher Market installation completed successfully.');
        return Command::SUCCESS;
    }

    protected function createAdminUser()
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'password' => Hash::make('abc123')]
        );
        $this->info($user->wasRecentlyCreated ? 'Admin user created.' : 'Admin user already exists.');
    }

    protected function updateEnvFile($key, $value)
    {
        $path = base_path('.env');
        if (! file_exists($path)) return;
        $content = file_get_contents($path);
        $pattern = "/^{$key}=.*/m";
        $content = preg_match($pattern, $content) ? preg_replace($pattern, "{$key}={$value}", $content) : $content.PHP_EOL."{$key}={$value}";
        file_put_contents($path, $content);
    }
}
