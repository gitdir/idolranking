<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$start, $end] = $this->resolveDateRange($request);
        $between = [$start, $end];

        $trafficCounts = Schema::hasTable('visitor_daily_stats')
            ? DB::table('visitor_daily_stats')
                ->whereBetween('stat_date', [$start->toDateString(), $end->toDateString()])
                ->get(['stat_date', 'page_views'])
                ->keyBy('stat_date')
            : collect();
        $dailyUniqueCounts = Schema::hasTable('visitor_daily_uniques')
            ? DB::table('visitor_daily_uniques')
                ->whereBetween('stat_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('stat_date, COUNT(*) AS unique_visitors')
                ->groupBy('stat_date')
                ->pluck('unique_visitors', 'stat_date')
            : collect();
        $trafficDaily = $this->fillTrafficDates($start, $end, $trafficCounts->all(), $dailyUniqueCounts->all());
        // Period UV is a distinct visitor count across the whole range, not a sum of daily UV.
        $periodUniqueVisitors = Schema::hasTable('visitor_daily_uniques')
            ? DB::table('visitor_daily_uniques')
                ->whereBetween('stat_date', [$start->toDateString(), $end->toDateString()])
                ->distinct()
                ->count('visitor_id')
            : 0;
        $metrics = [
            'page_views' => array_sum(array_column($trafficDaily, 'pv')),
            'unique_visitors' => $periodUniqueVisitors,
            'new_members' => DB::table('members')->where('is_dummy', 0)->whereBetween('created_at', $between)->count(),
            'fan_posts' => DB::table('fan_posts')->where('status', 'published')->whereNull('deleted_at')->whereBetween('created_at', $between)->count(),
            'comments' => DB::table('fan_post_comments')->where('status', 'published')->whereNull('deleted_at')->whereBetween('created_at', $between)->count(),
            'votes' => DB::table('idol_votes')->whereBetween('created_at', $between)->count(),
            'hearts_earned' => (int) DB::table('heart_histories')->where('amount', '>', 0)->whereBetween('created_at', $between)->sum('amount'),
            'hearts_used' => abs((int) DB::table('heart_histories')->where('amount', '<', 0)->whereBetween('created_at', $between)->sum('amount')),
        ];

        $heartSummary = [
            'current_balance' => (int) DB::table('members')->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')->sum('heart_balance'),
            'members_earned' => DB::table('heart_histories')->where('amount', '>', 0)->whereBetween('created_at', $between)->distinct()->count('member_idx'),
            'members_used' => DB::table('heart_histories')->where('amount', '<', 0)->whereBetween('created_at', $between)->distinct()->count('member_idx'),
        ];

        $attention = [
            'idol_requests' => DB::table('idol_registration_requests')->where('status', 'pending')->count(),
            'fan_post_reports' => DB::table('fan_post_reports')->where('status', 'pending')->count(),
            'rights_reports' => DB::table('fan_post_reports')->where('status', 'pending')->where('reason', 'copyright')->count(),
            'fan_post_comment_reports' => DB::table('fan_post_comment_reports')->where('status', 'pending')->count(),
            'suspended_members' => DB::table('members')->where('status', 'suspended')->whereNull('deleted_at')->count(),
            'blocked_ips' => Schema::hasTable('blocked_ips') ? DB::table('blocked_ips')->where('is_active', 1)->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count() : 0,
        ];

        return view('admin.dashboard', [
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'periodLabel' => $start->format('Y.m.d').' ~ '.$end->format('Y.m.d'),
            'metrics' => $metrics,
            'trafficDaily' => $trafficDaily,
            'heartSummary' => $heartSummary,
            'attention' => $attention,
            'activities' => $this->recentActivity(),
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $now = CarbonImmutable::now('UTC');
        $defaultStart = $now->subDays(6)->startOfDay();
        $defaultEnd = $now->endOfDay();
        $start = $this->parseDate($request->query('start_date'))?->startOfDay();
        $end = $this->parseDate($request->query('end_date'))?->endOfDay();

        if ($start === null || $end === null || $start > $end) {
            return [$defaultStart, $defaultEnd];
        }

        return [$start, $end];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function fillTrafficDates(CarbonImmutable $start, CarbonImmutable $end, array $trafficCounts, array $dailyUniqueCounts): array
    {
        $daily = [];

        for ($date = $start->startOfDay(); $date <= $end; $date = $date->addDay()) {
            $dateKey = $date->toDateString();
            $daily[] = [
                'date' => $dateKey,
                'pv' => (int) ($trafficCounts[$dateKey]->page_views ?? 0),
                'uv' => (int) ($dailyUniqueCounts[$dateKey] ?? 0),
            ];
        }

        return $daily;
    }

    private function recentActivity(): array
    {
        $activities = collect();

        DB::table('members')->where('is_dummy', 0)->latest('created_at')->limit(3)
            ->get(['nickname', 'created_at'])->each(fn ($row) => $activities->push([
                'type' => 'member', 'title' => '신규 회원 가입',
                'description' => $row->nickname ?: '회원', 'created_at' => $row->created_at,
            ]));
        DB::table('idol_registration_requests as request')->leftJoin('members as member', 'member.idx', '=', 'request.member_idx')
            ->latest('request.created_at')->limit(3)->get(['request.name', 'request.status', 'request.created_at', 'member.nickname'])
            ->each(fn ($row) => $activities->push([
                'type' => 'request', 'title' => '아이돌 등록 신청',
                'description' => $row->name.' · '.$this->statusLabel($row->status).' · '.($row->nickname ?: '회원'), 'created_at' => $row->created_at,
            ]));
        DB::table('fan_post_reports')->latest('created_at')->limit(3)->get(['reason', 'status', 'created_at'])
            ->each(fn ($row) => $activities->push([
                'type' => 'report', 'title' => '팬포스트 신고',
                'description' => $this->reportReasonLabel($row->reason).' · '.$this->statusLabel($row->status), 'created_at' => $row->created_at,
            ]));
        if (Schema::hasTable('admin_activity_logs')) DB::table('admin_activity_logs as log')->join('members as member', 'member.idx', '=', 'log.actor_member_idx')
            ->latest('log.created_at')->limit(3)->get(['log.action', 'log.summary', 'log.created_at', 'member.nickname'])
            ->each(fn ($row) => $activities->push([
                'type' => 'admin', 'title' => '관리자 조치',
                'description' => ($row->nickname ?: '관리자').' · '.($row->summary ?: $row->action), 'created_at' => $row->created_at,
            ]));
        DB::table('heart_histories')->orderByDesc('created_at')->limit(3)->get(['amount', 'reason', 'created_at'])
            ->each(fn ($row) => $activities->push([
                'type' => 'heart', 'title' => '하트 활동',
                'description' => ($row->amount > 0 ? '+' : '').number_format($row->amount).' · '.$this->heartReasonLabel($row->reason), 'created_at' => $row->created_at,
            ]));

        return $activities->sortByDesc('created_at')->take(8)->values()->all();
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => '대기', 'approved' => '승인', 'rejected' => '반려',
            'reviewed' => '검토 완료', default => $status,
        };
    }

    private function reportReasonLabel(string $reason): string
    {
        return match ($reason) {
            'spam' => '스팸', 'harassment_hate' => '괴롭힘 또는 혐오',
            'inappropriate_content' => '부적절한 콘텐츠', 'copyright' => '저작권',
            'misinformation' => '허위 정보', 'other' => '기타', default => $reason,
        };
    }

    private function heartReasonLabel(string $reason): string
    {
        return match ($reason) {
            'signup_reward' => '가입 보상', 'ad_reward' => '광고 보상', 'event_reward' => '이벤트 보상',
            'comment_reward' => '댓글 보상', 'vote' => '투표', 'admin_adjustment' => '관리자 조정',
            'comment_reward_reversal' => '댓글 보상 회수', default => $reason,
        };
    }
}
