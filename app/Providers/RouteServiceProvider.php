<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) { return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()); });
        $this->routes(function () {
            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
            foreach (['web.php','store.php','vendor.php','notifications.php','chat-moderation.php','admin-audit.php','marketplace.php','search.php','vendor-analytics.php'] as $routeFile) {
                Route::middleware('web')->group(base_path('routes/'.$routeFile));
            }
        });
    }
}
