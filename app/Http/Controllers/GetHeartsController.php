<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GetHeartsController extends Controller
{
    public function __invoke(Request $request)
    {
        $member = $request->attributes->get('currentMember');
        $definitions = [
            'daily_attendance' => ['name' => 'Daily Check-in', 'description' => 'Check in every day and get free Hearts!', 'icon' => 'calendar'],
            'watch_ads' => ['name' => 'Watch Ads', 'description' => 'Watch short ads and get free Hearts!', 'icon' => 'play'],
            'fan_post_create' => ['name' => 'Create Fan Post', 'description' => 'Share your love with photos or videos!', 'icon' => 'post'],
            'comment_create' => ['name' => 'Write Comment', 'description' => 'Leave a comment on fan posts!', 'icon' => 'comment'],
            'fan_post_recommend_received' => ['name' => 'Get Recommended', 'description' => 'Receive recommendations on your fan posts!', 'icon' => 'recommend'],
            'invite_friend' => ['name' => 'Invite Friends', 'description' => 'Invite friends to join IdolRanking!', 'icon' => 'invite'],
            'idol_registration_approved' => ['name' => 'Register Idol / Group', 'description' => 'Earn Hearts when your registration is approved!', 'icon' => 'crown'],
        ];

        $settings = DB::table('heart_reward_settings')->whereIn('action_code', array_keys($definitions))
            ->get(['action_code', 'heart_amount', 'daily_limit', 'is_enabled'])->keyBy('action_code');

        $todayCounts = collect();
        $todayEarned = 0;
        if ($member !== null) {
            $today = [now()->startOfDay(), now()->endOfDay()];
            $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
                ->where('amount', '>', 0)->whereBetween('created_at', $today)->sum('amount');
            $reasons = collect(array_keys($definitions))->map(
                fn (string $code) => HeartRewardManager::ACTIONS[$code]['reason']
            );
            // One positive history row represents one completed reward activity.
            $todayCounts = DB::table('heart_histories')->where('member_idx', $member->idx)
                ->where('amount', '>', 0)->whereBetween('created_at', $today)->whereIn('reason', $reasons)
                ->select('reason', DB::raw('COUNT(*) as reward_count'))->groupBy('reason')
                ->pluck('reward_count', 'reason');
        }

        $activities = collect($definitions)->map(function (array $display, string $code) use ($settings, $todayCounts, $member) {
            $definition = HeartRewardManager::ACTIONS[$code];
            $setting = $settings->get($code);
            $limit = max(0, (int) ($setting?->daily_limit ?? 0));
            $progress = $member === null ? null : (int) $todayCounts->get($definition['reason'], 0);

            return (object) ($display + [
                'code' => $code,
                'heart_amount' => max(0, (int) ($setting?->heart_amount ?? 0)),
                'daily_limit' => $limit,
                'is_enabled' => (bool) ($setting?->is_enabled ?? false),
                'progress' => $progress,
                'progress_percent' => $limit > 0 && $progress !== null ? min(100, $progress / $limit * 100) : 0,
            ]);
        })->values();

        return view('get-hearts', [
            'member' => $member,
            'todayEarned' => $todayEarned,
            'activities' => $activities,
            'seo' => [
                'title' => 'Get Hearts | IdolRanking',
                'description' => 'Earn Hearts through daily activities, fan posts, comments, recommendations, invitations, and idol registrations.',
                'url' => route('get-hearts'),
            ],
        ]);
    }

    public function claimDailyCheckIn(Request $request, HeartRewardManager $heartRewards)
    {
        $member = $request->attributes->get('currentMember');
        $today = now()->toDateString();
        $operationKey = 'daily_attendance:'.$member->idx.':'.$today;

        $result = DB::transaction(function () use ($member, $heartRewards, $operationKey) {
            $setting = DB::table('heart_reward_settings')
                ->where('action_code', 'daily_attendance')
                ->first(['heart_amount', 'daily_limit', 'is_enabled']);

            if ($setting === null || ! (bool) $setting->is_enabled || (int) $setting->heart_amount <= 0) {
                return ['status' => 'unavailable'];
            }

            $amount = $heartRewards->award(
                (int) $member->idx,
                'daily_attendance',
                $operationKey,
                ['memo' => 'Daily check-in reward']
            );

            if ($amount === 0) {
                return ['status' => 'already_claimed'];
            }

            return ['status' => 'awarded', 'amount' => $amount];
        });

        $dailyLimit = max(0, (int) (DB::table('heart_reward_settings')
            ->where('action_code', 'daily_attendance')->value('daily_limit') ?? 0));
        $todayRange = [now()->startOfDay(), now()->endOfDay()];
        $progress = DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('reason', HeartRewardManager::ACTIONS['daily_attendance']['reason'])
            ->whereBetween('created_at', $todayRange)->count();
        $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('amount', '>', 0)->whereBetween('created_at', $todayRange)->sum('amount');
        $heartBalance = (int) DB::table('members')->where('idx', $member->idx)->value('heart_balance');

        if ($result['status'] === 'unavailable') {
            return response()->json([
                'success' => false,
                'message' => 'Daily check-in is currently unavailable.',
            ], 409);
        }

        $alreadyClaimed = $result['status'] === 'already_claimed';
        $amount = (int) ($result['amount'] ?? 0);

        return response()->json([
            'success' => ! $alreadyClaimed,
            'already_claimed' => $alreadyClaimed,
            'message' => $alreadyClaimed
                ? 'Daily check-in has already been completed today.'
                : 'Daily check-in complete! +'.number_format($amount).' Hearts',
            'data' => [
                'amount' => $amount,
                'heart_balance' => $heartBalance,
                'today_earned' => $todayEarned,
                'progress' => $progress,
                'daily_limit' => $dailyLimit,
                'progress_percent' => $dailyLimit > 0 ? min(100, $progress / $dailyLimit * 100) : 0,
            ],
        ], $alreadyClaimed ? 409 : 200);
    }
}
