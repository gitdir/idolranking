<?php

namespace App\Http\Controllers;

use App\Support\HeartRewardManager;
use App\Support\Countries;
use App\Support\FanPostLanguages;
use App\Support\ManagedImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GetHeartsController extends Controller
{
    public function __invoke(Request $request)
    {
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('currentMember');
        $definitions = [
            'daily_attendance' => ['name' => __('ui.activity_daily_attendance'), 'description' => __('ui.activity_daily_attendance_desc'), 'icon' => 'calendar'],
            'watch_ads' => ['name' => __('ui.activity_watch_ads'), 'description' => __('ui.activity_watch_ads_desc'), 'icon' => 'play'],
            'fan_post_create' => ['name' => __('ui.activity_fan_post'), 'description' => __('ui.activity_fan_post_desc'), 'icon' => 'post'],
            'comment_create' => ['name' => __('ui.activity_comment'), 'description' => __('ui.activity_comment_desc'), 'icon' => 'comment'],
            'quiz_creator_reward_1' => ['name' => __('ui.activity_quiz_creator_reward_1'), 'description' => __('ui.activity_quiz_creator_reward_1_desc'), 'icon' => 'post'],
            'quiz_creator_reward_2' => ['name' => __('ui.activity_quiz_creator_reward_2'), 'description' => __('ui.activity_quiz_creator_reward_2_desc'), 'icon' => 'post'],
            'quiz_creator_reward_3' => ['name' => __('ui.activity_quiz_creator_reward_3'), 'description' => __('ui.activity_quiz_creator_reward_3_desc'), 'icon' => 'post'],
            'quiz_perfect' => ['name' => __('ui.activity_quiz_perfect'), 'description' => __('ui.activity_quiz_perfect_desc'), 'icon' => 'play'],
            'quiz_weekly_rank_1' => ['name' => __('quiz.weekly_reward_activity_1'), 'description' => __('quiz.weekly_reward_activity_desc'), 'icon' => 'play'],
            'quiz_weekly_rank_2' => ['name' => __('quiz.weekly_reward_activity_2'), 'description' => __('quiz.weekly_reward_activity_desc'), 'icon' => 'play'],
            'quiz_weekly_rank_3' => ['name' => __('quiz.weekly_reward_activity_3'), 'description' => __('quiz.weekly_reward_activity_desc'), 'icon' => 'play'],
            'fan_post_recommend_received' => ['name' => __('ui.activity_recommend'), 'description' => __('ui.activity_recommend_desc'), 'icon' => 'recommend'],
            'invite_friend' => ['name' => __('ui.activity_invite'), 'description' => __('ui.activity_invite_desc'), 'icon' => 'invite'],
            'idol_registration_approved' => ['name' => __('ui.activity_idol_registration'), 'description' => __('ui.activity_idol_registration_desc'), 'icon' => 'crown'],
        ];

        $settings = DB::table('heart_reward_settings')->whereIn('action_code', array_keys($definitions))
            ->get(['action_code', 'heart_amount', 'min_heart_amount', 'max_heart_amount', 'daily_limit', 'is_enabled'])->keyBy('action_code');

        $todayCounts = collect();
        $todayEarned = 0;
        if ($member !== null) {
            $utcNow = now('UTC');
            $today = [$utcNow->copy()->startOfDay(), $utcNow->copy()->endOfDay()];
            $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
                ->where('amount', '>', 0)->whereBetween('created_at', $today)->sum('amount');
            $reasons = collect(array_keys($definitions))->map(
                fn (string $code) => HeartRewardManager::ACTIONS[$code]['reason']
            );
            // One positive history row represents one completed reward activity.
            $todayCounts = DB::table('heart_histories')->where('member_idx', $member->idx)
                ->whereBetween('created_at', $today)->whereIn('reason', $reasons)
                ->select('reason', DB::raw('COUNT(*) as reward_count'))->groupBy('reason')
                ->pluck('reward_count', 'reason');
            // Check-in completion is tracked independently from rewards, including for ineligible accounts.
            $todayCounts->put('daily_attendance', DB::table('daily_check_ins')
                ->where('member_idx', $member->idx)->where('check_in_date', $utcNow->toDateString())->count());
        }

        $activities = collect($definitions)->map(function (array $display, string $code) use ($settings, $todayCounts, $member) {
            $definition = HeartRewardManager::ACTIONS[$code];
            $setting = $settings->get($code);
            $limit = $code === 'daily_attendance' ? 1
                : ($definition['limit_type'] === 'daily' ? max(0, (int) ($setting?->daily_limit ?? 0)) : 0);
            $progress = $member === null ? null : (int) $todayCounts->get($definition['reason'], 0);

            return (object) ($display + [
                'code' => $code,
                'heart_amount' => max(0, (int) ($setting?->heart_amount ?? 0)),
                'min_heart_amount' => max(0, (int) ($setting?->min_heart_amount ?? $setting?->heart_amount ?? 0)),
                'max_heart_amount' => max(0, (int) ($setting?->max_heart_amount ?? $setting?->heart_amount ?? 0)),
                'daily_limit' => $limit,
                'is_enabled' => $code !== 'watch_ads' && (bool) ($setting?->is_enabled ?? false),
                'progress' => $progress,
                'progress_percent' => $limit > 0 && $progress !== null ? min(100, $progress / $limit * 100) : 0,
            ]);
        })->values();

        $creatorRewardActivities = $activities->filter(
            fn (object $activity) => str_starts_with($activity->code, 'quiz_creator_reward_')
        );
        if ($creatorRewardActivities->isNotEmpty()) {
            $rewardLabels = $creatorRewardActivities->map(fn (object $activity) => $activity->is_enabled
                ? '+'.number_format($activity->heart_amount)
                : __('ui.unavailable'))->all();
            $activities = $activities->reject(
                fn (object $activity) => str_starts_with($activity->code, 'quiz_creator_reward_')
            )->push((object) [
                'code' => 'quiz_creator_reward',
                'name' => __('ui.activity_quiz_creator_reward'),
                'description' => __('ui.activity_quiz_creator_reward_desc'),
                'icon' => 'post',
                'heart_amount' => 0,
                'min_heart_amount' => 0,
                'max_heart_amount' => 0,
                'reward_amount_label' => implode(' / ', $rewardLabels),
                'daily_limit' => 0,
                'is_enabled' => $creatorRewardActivities->contains(fn (object $activity) => $activity->is_enabled),
                'progress' => null,
                'progress_percent' => 0,
            ])->values();
        }

        $weeklyQuizRewardActivities = $activities->filter(
            fn (object $activity) => in_array($activity->code, ['quiz_weekly_rank_1', 'quiz_weekly_rank_2', 'quiz_weekly_rank_3'], true)
        );
        if ($weeklyQuizRewardActivities->isNotEmpty()) {
            $weeklyQuizRewardActivities = collect([1, 2, 3])->map(
                fn (int $rank) => $weeklyQuizRewardActivities->firstWhere('code', 'quiz_weekly_rank_'.$rank)
            );
            $weeklyRewardLabels = $weeklyQuizRewardActivities->map(fn (?object $activity) => '+'.number_format((int) ($activity?->heart_amount ?? 0)))->all();
            $activities = $activities->reject(
                fn (object $activity) => in_array($activity->code, ['quiz_weekly_rank_1', 'quiz_weekly_rank_2', 'quiz_weekly_rank_3'], true)
            )->push((object) [
                'code' => 'quiz_weekly_rank_rewards',
                'name' => __('quiz.weekly_creator_rewards_title'),
                'description' => __('quiz.weekly_creator_rewards_description'),
                'icon' => 'play',
                'heart_amount' => 0,
                'min_heart_amount' => 0,
                'max_heart_amount' => 0,
                'reward_amount_label' => implode(' / ', $weeklyRewardLabels),
                'daily_limit' => 0,
                'is_enabled' => $weeklyQuizRewardActivities->contains(fn (?object $activity) => $activity !== null && $activity->is_enabled),
                'progress' => null,
                'progress_percent' => 0,
            ])->values();
        }

        $displayOrder = array_flip([
            'daily_attendance',
            'watch_ads',
            'fan_post_create',
            'comment_create',
            'fan_post_recommend_received',
            'idol_registration_approved',
            'quiz_creator_reward',
            'quiz_weekly_rank_rewards',
            'quiz_perfect',
            'invite_friend',
        ]);
        $activities = $activities->sortBy(
            fn (object $activity, int $index) => $displayOrder[$activity->code] ?? (count($displayOrder) + $index)
        )->values();

        $now = now('UTC');
        $partnerBanners = DB::table('partner_banners')
            ->where('is_active', 1)
            ->whereNotNull('image_path')->where('image_path', 'like', 'storage/partner-banners/%')
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderBy('display_order')->orderByDesc('idx')
            ->get(['idx', 'title', 'image_path', 'target_url', 'heart_reward', 'reward_enabled'])
            ->filter(fn ($banner) => Storage::disk('public')->exists(substr($banner->image_path, strlen('storage/'))))
            ->values();

        $rewardEligibleMember = $member !== null && (int) $member->member_level === 1
            && ((int) $member->is_dummy === 0 || ((bool) $request->attributes->get('activityDummyMode') && (int) $member->is_dummy === 1));

        $claimableBannerKeys = !$rewardEligibleMember ? collect() : $partnerBanners
            ->filter(fn ($banner) => (bool) $banner->reward_enabled && (int) $banner->heart_reward > 0)
            ->map(fn ($banner) => 'partner_banner:'.$banner->idx.':'.$member->idx.':'.$now->format('Ymd'));
        $completedBannerKeys = $claimableBannerKeys->isEmpty() ? [] : DB::table('heart_histories')
            ->where('member_idx', $member->idx)->where('reason', HeartRewardManager::PARTNER_BANNER_REASON)
            ->where('amount', '>', 0)->whereIn('operation_key', $claimableBannerKeys)->pluck('operation_key')->all();

        $partnerBanners = $partnerBanners->map(function ($banner) use ($member, $rewardEligibleMember, $completedBannerKeys, $now) {
            $operationKey = !$rewardEligibleMember ? null : 'partner_banner:'.$banner->idx.':'.$member->idx.':'.$now->format('Ymd');
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
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:200'],
            'language_code' => ['required', 'string', 'max:10', Rule::in(array_keys(FanPostLanguages::all()))],
        ], [
            'content.required' => __('messages.daily_content_required'),
            'content.string' => __('messages.daily_content_required'),
            'content.max' => __('messages.daily_content_max'),
            'language_code.required' => __('messages.daily_language_invalid'),
            'language_code.in' => __('messages.daily_language_invalid'),
        ]);

        $todayStart = now('UTC')->startOfDay();
        $today = $todayStart->toDateString();
        $todayRange = [$todayStart, $todayStart->copy()->endOfDay()];
        $operationKey = 'daily_attendance:'.$member->idx.':'.$today;
        $activityDummyMode = (bool) $request->attributes->get('activityDummyMode', false);

        $result = DB::transaction(function () use ($member, $actor, $heartRewards, $operationKey, $today, $validated, $activityDummyMode) {
            // Serialize same-member submissions before checking either unique daily record.
            $lockedMember = DB::table('members')->where('idx', $member->idx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'member_level', 'is_dummy']);
            if ($lockedMember === null) {
                return ['status' => 'unavailable'];
            }

            if (DB::table('daily_check_ins')->where('member_idx', $member->idx)->where('check_in_date', $today)->exists()
                || DB::table('heart_histories')->where('member_idx', $member->idx)->where('operation_key', $operationKey)->exists()) {
                return ['status' => 'already_claimed'];
            }

            $setting = DB::table('heart_reward_settings')
                ->where('action_code', 'daily_attendance')
                ->lockForUpdate()
                ->first(['heart_amount', 'min_heart_amount', 'max_heart_amount', 'daily_limit', 'is_enabled']);

            if ($setting === null || ! (bool) $setting->is_enabled) {
                return ['status' => 'unavailable'];
            }

            $minimum = (int) ($setting->min_heart_amount ?? $setting->heart_amount);
            $maximum = (int) ($setting->max_heart_amount ?? $setting->heart_amount);
            if ($minimum < 0 || $maximum < $minimum) {
                return ['status' => 'unavailable'];
            }
            $rewardEligibleMember = (int) $lockedMember->member_level === 1
                && ((int) $lockedMember->is_dummy === 0
                    || ($activityDummyMode && (int) $lockedMember->is_dummy === 1));
            $amount = $rewardEligibleMember ? random_int($minimum, $maximum) : 0;
            $rewardAwarded = false;

            if ($rewardEligibleMember) {
                $heartRewards->award(
                    (int) $member->idx,
                    'daily_attendance',
                    $operationKey,
                    ['actor_member_idx' => (int) $actor->idx, 'memo' => 'Daily check-in reward'],
                    $amount
                );

                $rewardHistory = DB::table('heart_histories')
                    ->where('member_idx', $member->idx)->where('reason', 'daily_attendance')
                    ->where('operation_key', $operationKey)->first(['amount']);
                if ($rewardHistory === null) {
                    throw new \RuntimeException('Eligible Daily Check-in reward was not recorded.');
                }
                // A history row, not a positive amount, distinguishes a valid zero reward from no reward.
                $amount = (int) $rewardHistory->amount;
                $rewardAwarded = true;
            }

            DB::table('daily_check_ins')->insert([
                'member_idx' => (int) $member->idx,
                'actor_member_idx' => (int) $actor->idx,
                'language_code' => strtolower($validated['language_code']),
                'content' => $validated['content'],
                'awarded_hearts' => $amount,
                'check_in_date' => $today,
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            return ['status' => 'checked_in', 'amount' => $rewardAwarded ? $amount : null, 'reward_awarded' => $rewardAwarded];
        });

        $dailyLimit = 1;
        $progress = DB::table('daily_check_ins')->where('member_idx', $member->idx)
            ->where('check_in_date', $today)->count();
        $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('amount', '>', 0)->whereBetween('created_at', $todayRange)->sum('amount');
        $heartBalance = (int) DB::table('members')->where('idx', $member->idx)->value('heart_balance');

        if ($result['status'] === 'unavailable') {
            return response()->json([
                'success' => false,
                'message' => __('messages.daily_unavailable'),
            ], 409);
        }

        $alreadyClaimed = $result['status'] === 'already_claimed';
        $savedCheckIn = DB::table('daily_check_ins as check_in')
            ->join('members as member', 'member.idx', '=', 'check_in.member_idx')
            ->where('check_in.member_idx', $member->idx)->where('check_in.check_in_date', $today)
            ->where('member.member_level', '>', 0)->where('member.status', 'active')->whereNull('member.deleted_at')
            ->first([
                'check_in.idx', 'check_in.content', 'check_in.language_code', 'check_in.created_at',
                'member.member_code', 'member.nickname', 'member.country_code', 'member.profile_image_path',
            ]);
        $rewardHistory = DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('reason', HeartRewardManager::ACTIONS['daily_attendance']['reason'])
            ->where('operation_key', $operationKey)->first(['amount']);
        // Use the saved check-in and history as the response source, including for idempotent retries.
        $rewardAwarded = $rewardHistory !== null;
        $amount = $rewardAwarded ? (int) $rewardHistory->amount : null;
        $cardHtml = null;
        if ($savedCheckIn !== null) {
            $cardHtml = view('components.attendance-card', ['checkIn' => [
                'idx' => (int) $savedCheckIn->idx,
                'member_code' => $savedCheckIn->member_code,
                'nickname' => $savedCheckIn->nickname,
                'member_url' => route('member.fan-posts', ['member_code' => $savedCheckIn->member_code]),
                'profile_image_url' => ManagedImageStorage::publicUrl($savedCheckIn->profile_image_path),
                'country_name' => Countries::localizedName($savedCheckIn->country_code, app()->getLocale()),
                'language_code' => FanPostLanguages::isValid($savedCheckIn->language_code)
                    ? strtolower($savedCheckIn->language_code) : null,
                'content' => $savedCheckIn->content,
                'reward_awarded' => $rewardAwarded,
                'awarded_hearts' => $amount,
                'created_at' => $savedCheckIn->created_at,
            ]])->render();
        }

        return response()->json([
            'success' => ! $alreadyClaimed,
            'already_claimed' => $alreadyClaimed,
            'message' => $alreadyClaimed
                ? __('messages.daily_claimed')
                : ($rewardAwarded
                    ? __('messages.daily_awarded', ['amount' => number_format((int) $amount)])
                    : __('messages.daily_checked_in')),
            'data' => [
                'amount' => $amount,
                'reward_awarded' => $rewardAwarded,
                'content' => $savedCheckIn?->content ?? $validated['content'],
                'language_code' => $savedCheckIn?->language_code ?? $validated['language_code'],
                'check_in_id' => $savedCheckIn === null ? null : (int) $savedCheckIn->idx,
                'card_html' => $cardHtml,
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
        $member = $request->attributes->get('activityMember');
        $actor = $request->attributes->get('activityActor');
        $result = $heartRewards->awardPartnerBanner((int) $member->idx, $idx, (int) $actor->idx);

        if ($result['status'] === 'not_found') {
            return response()->json(['success' => false, 'message' => __('messages.partner_banner_unavailable')], 404);
        }
        if ($result['status'] === 'ineligible') {
            return response()->json(['success' => false, 'message' => __('messages.reward_account_ineligible')], 403);
        }
        if ($result['status'] === 'unavailable') {
            return response()->json(['success' => false, 'message' => __('messages.partner_reward_unavailable')], 409);
        }

        $alreadyClaimed = $result['status'] === 'already_claimed';
        $utcNow = now('UTC');
        $todayEarned = (int) DB::table('heart_histories')->where('member_idx', $member->idx)
            ->where('amount', '>', 0)
            ->whereBetween('created_at', [$utcNow->copy()->startOfDay(), $utcNow->copy()->endOfDay()])
            ->sum('amount');

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
