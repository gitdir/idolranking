<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('change-password', function (Request $request) {
            return Limit::perMinute(6)->by('password:'.$request->session()->get('member_idx', 'guest').':'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false, 'message' => 'Too many attempts. Please wait a minute and try again.',
                ], 429, $headers));
        });
    }
}
