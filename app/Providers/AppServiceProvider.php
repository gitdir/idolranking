<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use App\Support\DatabaseAvailability;
use Carbon\CarbonImmutable;
use Throwable;

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
        View::composer('partials.footer', function ($view): void {
            $member = request()->attributes->get('currentMember');
            $isAdmin = (int) ($member?->member_level ?? 0) === 10
                && (int) ($member?->is_dummy ?? 0) !== 1;

            if (! $isAdmin) {
                return;
            }

            $utcNow = CarbonImmutable::now('UTC');
            $today = $utcNow->toDateString();
            $yesterday = $utcNow->subDay()->toDateString();
            $pageViews = collect();
            $dailyUniqueCounts = collect();

            try {
                if (DatabaseAvailability::isAvailable()) {
                    if (Schema::hasTable('visitor_daily_stats')) {
                        $pageViews = DB::table('visitor_daily_stats')
                            ->whereBetween('stat_date', [$yesterday, $today])
                            ->pluck('page_views', 'stat_date');
                    }

                    if (Schema::hasTable('visitor_daily_uniques')) {
                        $dailyUniqueCounts = DB::table('visitor_daily_uniques')
                            ->whereBetween('stat_date', [$yesterday, $today])
                            ->selectRaw('stat_date, COUNT(*) AS unique_visitors')
                            ->groupBy('stat_date')
                            ->pluck('unique_visitors', 'stat_date');
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            $view->with('footerVisitorStats', [
                'yesterdayUv' => (int) ($dailyUniqueCounts[$yesterday] ?? 0),
                'yesterdayPv' => (int) ($pageViews[$yesterday] ?? 0),
                'todayUv' => (int) ($dailyUniqueCounts[$today] ?? 0),
                'todayPv' => (int) ($pageViews[$today] ?? 0),
            ]);
        });

        RateLimiter::for('change-password', function (Request $request) {
            return Limit::perMinute(6)->by('password:'.$request->session()->get('member_idx', 'guest').':'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false, 'message' => 'Too many attempts. Please wait a minute and try again.',
                ], 429, $headers));
        });
    }
}
