<?php

namespace App\Http\Middleware;

use App\Support\BotUserAgentDetector;
use App\Support\DatabaseAvailability;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Schema;

class TrackVisitor
{
    public function __construct(private readonly BotUserAgentDetector $botDetector)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $databaseAvailable = DatabaseAvailability::isAvailable();

        if ($databaseAvailable && $this->shouldTrack($request, $response)) {
            $date = now()->toDateString();
            $isBot = $this->botDetector->isBot($request->userAgent());
            [$visitorId, $isNewVisitor] = $isBot ? [null, false] : $this->visitorId($request);

            try {
                // Bots remain crawlable but never contribute to PV or UV analytics.
                if (!$isBot && Schema::hasTable('visitor_daily_stats') && Schema::hasTable('visitor_daily_uniques')) {
                    DB::transaction(function () use ($date, $visitorId): void {
                        // Every meaningful public HTML request contributes one PV.
                        DB::table('visitor_daily_stats')->upsert(
                            [['stat_date' => $date, 'page_views' => 1, 'unique_visitors' => 0]],
                            ['stat_date'],
                            ['page_views' => DB::raw('page_views + 1')]
                        );

                        // One visitor/day row provides daily UV and supports distinct UV across any period.
                        $isFirstVisitToday = DB::table('visitor_daily_uniques')->insertOrIgnore([
                            'stat_date' => $date,
                            'visitor_id' => $visitorId,
                            'created_at' => now(),
                        ]) === 1;

                        if ($isFirstVisitToday) {
                            DB::table('visitor_daily_stats')->where('stat_date', $date)->increment('unique_visitors');
                        }
                    });
                }

                if (Schema::hasTable('member_access_logs')) {
                    $member = $request->attributes->get('currentMember');
                    DB::table('member_access_logs')->insert([
                        'member_idx' => $member?->idx,
                        'visitor_id' => $visitorId,
                        'is_bot' => (int) $isBot,
                        'ip_address' => $request->ip(),
                        // Query strings are intentionally excluded because they may contain sensitive values.
                        'request_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 1024),
                        'user_agent' => mb_substr((string) $request->userAgent(), 0, 1024) ?: null,
                        'created_at' => now(),
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);
            }

            if ($isNewVisitor) {
                $response->headers->setCookie(Cookie::make(
                    config('tracking.visitor_cookie_name'),
                    $visitorId,
                    (int) config('tracking.visitor_cookie_minutes'),
                    '/',
                    null,
                    $request->isSecure(),
                    true,
                    false,
                    'lax'
                ));
            }
        }

        return $response;
    }

    private function shouldTrack(Request $request, mixed $response): bool
    {
        if (!$request->isMethod('GET') || $request->is('admin*', 'up', 'storage/*') || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if (preg_match('/\.(?:css|js|mjs|map|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|eot)$/i', $request->path())) {
            return false;
        }

        if (!method_exists($response, 'getStatusCode')) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        return $response->getStatusCode() >= 200
            && $response->getStatusCode() < 400
            && str_contains($contentType, 'text/html');
    }

    private function visitorId(Request $request): array
    {
        $cookieName = (string) config('tracking.visitor_cookie_name');
        $visitorId = $request->cookie($cookieName);

        if (is_string($visitorId) && preg_match('/^[a-f0-9]{64}$/', $visitorId)) {
            return [$visitorId, false];
        }

        // The random first-party identifier is independent of IP, member identity, and browser traits.
        return [bin2hex(random_bytes(32)), true];
    }
}
