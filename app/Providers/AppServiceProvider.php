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

            $seoulNow = CarbonImmutable::now('Asia/Seoul');
            $today = $seoulNow->toDateString();
            $yesterday = $seoulNow->subDay()->toDateString();
            $dailyStats = collect();

            try {
                if (DatabaseAvailability::isAvailable() && Schema::hasTable('member_access_logs')) {
                    $startUtc = $seoulNow->subDay()->startOfDay()->utc()->toDateTimeString();
                    $endUtc = $seoulNow->endOfDay()->utc()->toDateTimeString();

                    $dailyStats = DB::table('member_access_logs as log')
                        ->leftJoin('members as member', 'member.idx', '=', 'log.member_idx')
                        ->whereBetween('log.created_at', [$startUtc, $endUtc])
                        ->where('log.is_bot', 0)
                        ->where(function ($query): void {
                            $query->whereNull('log.member_idx')
                                ->orWhere(function ($memberQuery): void {
                                    $memberQuery->where('member.member_level', '<', 10)
                                        ->where('member.is_dummy', 0);
                                });
                        })
                        ->selectRaw('DATE(DATE_ADD(log.created_at, INTERVAL 9 HOUR)) AS stat_date, COUNT(*) AS page_views, COUNT(DISTINCT log.visitor_id) AS unique_visitors')
                        ->groupByRaw('DATE(DATE_ADD(log.created_at, INTERVAL 9 HOUR))')
                        ->get()
                        ->keyBy('stat_date');
                }
            } catch (Throwable $exception) {
                report($exception);
            }

            $view->with('footerVisitorStats', [
                'yesterdayUv' => (int) ($dailyStats[$yesterday]->unique_visitors ?? 0),
                'yesterdayPv' => (int) ($dailyStats[$yesterday]->page_views ?? 0),
                'todayUv' => (int) ($dailyStats[$today]->unique_visitors ?? 0),
                'todayPv' => (int) ($dailyStats[$today]->page_views ?? 0),
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
