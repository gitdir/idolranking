<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class GetHeartsController extends Controller
{
    public function __invoke(Request $request)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('currentMember');
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

        $now = now();
        $partnerBanners = DB::table('partner_banners')
            ->where('is_active', 1)
            ->whereNotNull('image_path')->where('image_path', 'like', 'storage/partner-banners/%')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('display_order')->orderByDesc('idx')
            ->get(['idx', 'title', 'image_path', 'target_url', 'heart_reward', 'reward_enabled'])
            ->filter(fn ($banner) => Storage::disk('public')->exists(substr($banner->image_path, strlen('storage/'))))
            ->values();

        // Banner rewards remain tied to the authenticated account, never the delegated activity member.
        $rewardEligibleMember = $actor !== null && DB::table('members')->where('idx', $actor->idx)
            ->where('member_level', 1)->where('is_dummy', 0)->where('status', 'active')->whereNull('deleted_at')->exists();

        $claimableBannerKeys = !$rewardEligibleMember ? collect() : $partnerBanners
            ->filter(fn ($banner) => (bool) $banner->reward_enabled && (int) $banner->heart_reward > 0)
            ->map(fn ($banner) => 'partner_banner:'.$banner->idx.':'.$actor->idx.':'.$now->format('Ymd'));
        $completedBannerKeys = $claimableBannerKeys->isEmpty() ? [] : DB::table('heart_histories')
            ->where('member_idx', $actor->idx)->where('reason', HeartRewardManager::PARTNER_BANNER_REASON)
            ->where('amount', '>', 0)->whereIn('operation_key', $claimableBannerKeys)->pluck('operation_key')->all();

        $partnerBanners = $partnerBanners->map(function ($banner) use ($actor, $rewardEligibleMember, $completedBannerKeys, $now) {
            $operationKey = !$rewardEligibleMember ? null : 'partner_banner:'.$banner->idx.':'.$actor->idx.':'.$now->format('Ymd');
            $banner->image_url = \App\Support\ManagedImageStorage::publicUrl($banner->image_path);
            $banner->completed_today = $operationKey !== null && in_array($operationKey, $completedBannerKeys, true);
            $banner->show_reward_badge = (bool) $banner->reward_enabled && (int) $banner->heart_reward > 0;
            $banner->claim_url = $rewardEligibleMember && (bool) $banner->reward_enabled && (int) $banner->heart_reward > 0
                && !$banner->completed_today ? route('get-hearts.partner-banners.claim', $banner->idx) : null;
            $banner->click_url = route('partner-banners.click', $banner->idx);

            return $banner;
        });

        return view('get-hearts', [
            'member' => $member,
            'todayEarned' => $todayEarned,
            'activities' => $activities,
            'partnerBanners' => $partnerBanners,
            'seo' => [
                'title' => 'Get Hearts | IdolRanking',
                'description' => 'Earn Hearts through daily activities, fan posts, comments, recommendations, invitations, and idol registrations.',
                'url' => route('get-hearts'),
            ],
        ]);
    }

    public function claimDailyCheckIn(Request $request, HeartRewardManager $heartRewards)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $today = now()->toDateString();
        $operationKey = 'daily_attendance:'.$member->idx.':'.$today;

        $result = DB::transaction(function () use ($member, $actor, $heartRewards, $operationKey) {
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
                ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Daily check-in reward']
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

    public function claimPartnerBanner(Request $request, int $idx, HeartRewardManager $heartRewards)
    {
        $member = $request->attributes->get('currentMember');
        $result = $heartRewards->awardPartnerBanner((int) $member->idx, $idx);

        if ($result['status'] === 'not_found') {
            return response()->json(['success' => false, 'message' => 'This partner banner is no longer available.'], 404);
        }
        if ($result['status'] === 'ineligible') {
            return response()->json(['success' => false, 'message' => 'Your account cannot receive this reward.'], 403);
        }
        if ($result['status'] === 'unavailable') {
            return response()->json(['success' => false, 'message' => 'This partner banner reward is unavailable.'], 409);
        }

        $alreadyClaimed = $result['status'] === 'already_claimed';
        $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('amount', '>', 0)->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->sum('amount');

        return response()->json([
            'success' => !$alreadyClaimed,
            'already_claimed' => $alreadyClaimed,
            'message' => $alreadyClaimed
                ? 'You have already received this banner reward today.'
                : 'Partner banner reward received! +'.number_format((int) $result['amount']).' Hearts',
            'data' => [
                'amount' => (int) ($result['amount'] ?? 0),
                'heart_balance' => (int) $result['heart_balance'],
                'today_earned' => $todayEarned,
                'completed_today' => true,
            ],
        ], $alreadyClaimed ? 409 : 200);
    }

    public function clickPartnerBanner(Request $request, int $idx)
    {
        $now = now();
        $banner = DB::table('partner_banners')->where('idx', $idx)->where('is_active', 1)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->first(['target_url']);
        abort_if($banner === null || !$this->isSafePartnerUrl($banner->target_url), 404);

        $clickMemberIdx = null;
        $shouldCountClick = false;
        if (!$request->attributes->get('invalidMemberSession', false)) {
            $member = $request->attributes->get('currentMember');
            if ($member === null) {
                $shouldCountClick = true;
            } else {
                $eligibleMemberIdx = DB::table('members')->where('idx', $member->idx)
                    ->where('member_level', 1)->where('is_dummy', 0)->where('status', 'active')->whereNull('deleted_at')
                    ->value('idx');
                if ($eligibleMemberIdx !== null) {
                    $clickMemberIdx = (int) $eligibleMemberIdx;
                    $shouldCountClick = true;
                }
            }
        }

        if ($shouldCountClick) {
            $subject = $clickMemberIdx === null
                ? 'guest:'.hash('sha256', $request->session()->getId())
                : 'member:'.$clickMemberIdx;
            $rateLimitKey = 'partner-banner-click:'.$idx.':'.hash('sha256', $subject);

            // Ignore only same-banner repeats from this member/session within two seconds; there is no daily click cap.
            RateLimiter::attempt($rateLimitKey, 1, function () use ($idx, $clickMemberIdx, $now): bool {
                DB::table('partner_banner_clicks')->insert([
                    'banner_idx' => $idx,
                    'member_idx' => $clickMemberIdx,
                    'clicked_at' => $now,
                ]);

                return true;
            }, 2);
        }

        return redirect()->away($banner->target_url);
    }

    private function isSafePartnerUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) $scheme), ['http', 'https'], true);
    }
}
