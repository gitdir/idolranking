<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

final class HeartRewardManager
{
    public const ACTIONS = [
        'watch_ads' => ['name' => '광고 시청', 'amount' => 50, 'daily_limit' => 10, 'limit_type' => 'daily', 'reward_type' => 'fixed', 'reason' => 'ad_reward', 'description' => '광고 시청 완료에 지급합니다.'],
        'invite_friend' => ['name' => '친구 초대 성공', 'amount' => 1000, 'daily_limit' => 0, 'limit_type' => 'daily', 'reward_type' => 'fixed', 'reason' => 'invite_friend_reward', 'description' => '초대한 친구의 가입이 완료되면 초대한 회원에게 1회 지급합니다.'],
        'signup' => ['name' => '회원 가입 완료', 'amount' => 1000, 'daily_limit' => 0, 'limit_type' => 'account', 'reward_type' => 'fixed', 'reason' => 'signup_reward', 'description' => '회원 가입 완료 후 해당 회원에게 1회 지급합니다.'],
        'profile_complete' => ['name' => '프로필 완성', 'amount' => 100, 'daily_limit' => 0, 'limit_type' => 'account', 'reward_type' => 'fixed', 'reason' => 'profile_complete', 'description' => '회원이 프로필을 처음 완성하면 1회 지급합니다.'],
        'daily_attendance' => ['name' => '일일 출석', 'amount' => 100, 'daily_limit' => 1, 'limit_type' => 'daily', 'reward_type' => 'range', 'reason' => 'daily_attendance', 'description' => '출석 글을 작성하면 설정한 범위에서 하루 1회 지급합니다.'],
        'fan_post_create' => ['name' => '팬포스트 작성', 'amount' => 30, 'daily_limit' => 20, 'limit_type' => 'daily', 'reward_type' => 'fixed', 'reason' => 'fan_post_reward', 'description' => '사진 또는 YouTube 영상이 포함된 팬포스트 작성에 지급합니다.'],
        'comment_create' => ['name' => '댓글 작성', 'amount' => 10, 'daily_limit' => 20, 'limit_type' => 'daily', 'reward_type' => 'range', 'reason' => 'comment_reward', 'description' => '보상 기준에 맞는 댓글 작성에 설정한 범위에서 지급합니다.'],
        'quiz_creator_reward_1' => ['name' => '퀴즈 제작자 보상 (1문항)', 'amount' => 3, 'daily_limit' => 0, 'limit_type' => 'request', 'reward_type' => 'fixed', 'reason' => 'quiz_creator_reward_1', 'description' => '다른 회원이 1문항 퀴즈 전체를 처음 완료하면 정답과 관계없이 제작자에게 1회 지급합니다.'],
        'quiz_creator_reward_2' => ['name' => '퀴즈 제작자 보상 (2문항)', 'amount' => 5, 'daily_limit' => 0, 'limit_type' => 'request', 'reward_type' => 'fixed', 'reason' => 'quiz_creator_reward_2', 'description' => '다른 회원이 2문항 퀴즈 전체를 처음 완료하면 정답과 관계없이 제작자에게 1회 지급합니다.'],
        'quiz_creator_reward_3' => ['name' => '퀴즈 제작자 보상 (3문항)', 'amount' => 10, 'daily_limit' => 0, 'limit_type' => 'request', 'reward_type' => 'fixed', 'reason' => 'quiz_creator_reward_3', 'description' => '다른 회원이 3문항 퀴즈 전체를 처음 완료하면 정답과 관계없이 제작자에게 1회 지급합니다.'],
        'quiz_perfect' => ['name' => '퀴즈 만점 참여자 보상', 'amount' => 20, 'min_amount' => 10, 'max_amount' => 30, 'daily_limit' => 0, 'limit_type' => 'request', 'reward_type' => 'range', 'minimum_amount' => 0, 'reason' => 'quiz_perfect', 'description' => '다른 회원이 퀴즈를 만점으로 완료하면 참여자에게 설정한 범위에서 지급합니다.'],
        'quiz_weekly_rank_1' => ['name' => '주간 인기 퀴즈 제작자 보상 (1위)', 'amount' => 1000, 'daily_limit' => 0, 'limit_type' => 'none', 'reward_type' => 'fixed', 'reason' => 'quiz_weekly_rank_1', 'description' => '주간 인기 퀴즈 1위의 제작자에게 월요일 정산 시 지급합니다.'],
        'quiz_weekly_rank_2' => ['name' => '주간 인기 퀴즈 제작자 보상 (2위)', 'amount' => 500, 'daily_limit' => 0, 'limit_type' => 'none', 'reward_type' => 'fixed', 'reason' => 'quiz_weekly_rank_2', 'description' => '주간 인기 퀴즈 2위의 제작자에게 월요일 정산 시 지급합니다.'],
        'quiz_weekly_rank_3' => ['name' => '주간 인기 퀴즈 제작자 보상 (3위)', 'amount' => 100, 'daily_limit' => 0, 'limit_type' => 'none', 'reward_type' => 'fixed', 'reason' => 'quiz_weekly_rank_3', 'description' => '주간 인기 퀴즈 3위의 제작자에게 월요일 정산 시 지급합니다.'],
        'fan_post_recommend_received' => ['name' => '팬포스트 추천 받음', 'amount' => 1, 'daily_limit' => 100, 'limit_type' => 'daily', 'reward_type' => 'fixed', 'reason' => 'fan_post_recommend_received', 'description' => '내 팬포스트가 추천을 받을 때 작성자에게 지급합니다.'],
        'idol_registration_approved' => ['name' => '아이돌/그룹 등록 승인', 'amount' => 3000, 'daily_limit' => 0, 'limit_type' => 'request', 'reward_type' => 'fixed', 'reason' => 'idol_registration_approved', 'description' => '관리자가 아이돌 또는 그룹 등록 요청을 승인하면 신청자에게 지급합니다.'],
    ];

    public const REFERRAL_SESSION_KEY = 'referral_member_code';
    public const PARTNER_BANNER_REASON = 'partner_banner_reward';

    public function eligibleInviterIdx(mixed $memberCode): ?int
    {
        if (!is_string($memberCode) || preg_match('/^[A-Za-z0-9]{12}$/', $memberCode) !== 1) {
            return null;
        }

        $idx = DB::table('members')->where('member_code', $memberCode)
            ->where('member_level', 1)->where('is_dummy', 0)
            ->where('status', 'active')->whereNull('deleted_at')->value('idx');

        return $idx === null ? null : (int) $idx;
    }

    public function configuredAmount(string $actionCode): int
    {
        if (!isset(self::ACTIONS[$actionCode]) || !Schema::hasTable('heart_reward_settings')) {
            return 0;
        }

        return max(0, (int) (DB::table('heart_reward_settings')
            ->where('action_code', $actionCode)->value('heart_amount') ?? 0));
    }

    public function awardReferral(int $newMemberIdx): int
    {
        return DB::transaction(function () use ($newMemberIdx): int {
            $newMember = DB::table('members')->where('idx', $newMemberIdx)->lockForUpdate()
                ->first(['idx', 'referred_by_member_idx']);

            if ($newMember === null || $newMember->referred_by_member_idx === null
                || (int) $newMember->referred_by_member_idx === $newMemberIdx) {
                return 0;
            }

            $inviterIdx = (int) $newMember->referred_by_member_idx;
            $inviterIsEligible = DB::table('members')->where('idx', $inviterIdx)
                ->where('member_level', 1)->where('is_dummy', 0)
                ->where('status', 'active')->whereNull('deleted_at')->exists();

            if (!$inviterIsEligible) {
                return 0;
            }

            return $this->award($inviterIdx, 'invite_friend', 'referral_reward:'.$newMemberIdx, [
                'actor_member_idx' => $newMemberIdx,
                'memo' => 'Reward for referring a newly registered member',
            ]);
        });
    }

    public function enabledAmount(string $actionCode): int
    {
        if (! isset(self::ACTIONS[$actionCode])) {
            return 0;
        }

        $setting = DB::table('heart_reward_settings')
            ->where('action_code', $actionCode)
            ->lockForUpdate()
            ->first(['heart_amount', 'is_enabled']);

        if ($setting === null || ! (bool) $setting->is_enabled) {
            return 0;
        }

        return max(0, (int) $setting->heart_amount);
    }

    public function award(int $memberIdx, string $actionCode, string $operationKey, array $references = [], ?int $amountOverride = null): int
    {
        // Ad rewards require verified completion from an ad provider; no such integration exists yet.
        if ($actionCode === 'watch_ads') return 0;

        $definition = self::ACTIONS[$actionCode] ?? null;
        if ($definition === null) return 0;
        if ($amountOverride !== null && $actionCode !== 'daily_attendance') return 0;
        if ($actionCode === 'daily_attendance' && $amountOverride === null) return 0;
        $setting = DB::table('heart_reward_settings')->where('action_code', $actionCode)->lockForUpdate()->first();
        if ($setting === null || ! (bool) $setting->is_enabled) return 0;
        $hasRandomRange = ($definition['reward_type'] ?? 'fixed') === 'range'
            && $setting->min_heart_amount !== null && $setting->max_heart_amount !== null;
        if ($hasRandomRange) {
            $minimum = (int) $setting->min_heart_amount;
            $maximum = (int) $setting->max_heart_amount;
            $minimumAllowed = 0;
            if ($minimum < $minimumAllowed || $maximum < $minimum) return 0;
            $amount = random_int($minimum, $maximum);
        } else {
            $amount = $amountOverride ?? (int) $setting->heart_amount;
        }
        // Configured ranges may award zero; fixed rewards remain positive-only except Daily Check-in.
        if ($amount < 0 || ($amount === 0 && $actionCode !== 'daily_attendance' && ! $hasRandomRange)) return 0;

        // Selected-member mode is authorized by ResolveActivityMember; legacy random mode uses its reservation proof.
        $actorIdx = (int) ($references['actor_member_idx'] ?? 0);
        $member = $this->lockRewardMember($memberIdx, $actorIdx);
        if ($member === null && isset($references['random_dummy_request_idx'])) {
            $manager = app(RandomDummyActivity::class);
            $eligible = $actionCode === 'fan_post_recommend_received'
                ? $manager->permitsDummyRecommendationReward((int) $references['random_dummy_request_idx'], $memberIdx, $actorIdx)
                : $manager->permitsDummyReward((int) $references['random_dummy_request_idx'], $memberIdx, $actorIdx, $actionCode);
            if ($eligible) {
                $member = DB::table('members')->where('idx', $memberIdx)->where('member_level', 1)->where('is_dummy', 1)
                    ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
            }
        }
        if ($member === null || DB::table('heart_histories')->where('member_idx', $memberIdx)->where('operation_key', $operationKey)->exists()) return 0;

        $dailyLimit = max(0, (int) $setting->daily_limit);
        // Zero means unlimited. Account/request rewards are made one-time by their stable operation key.
        // Daily Check-in uniqueness is enforced by its UTC date key/table row, not a history timestamp window.
        if ($actionCode !== 'daily_attendance' && $definition['limit_type'] === 'daily' && $dailyLimit > 0) {
            $utcNow = now('UTC');
            $count = DB::table('heart_histories')->where('member_idx', $memberIdx)->where('reason', $definition['reason'])
                ->whereBetween('created_at', [$utcNow->copy()->startOfDay(), $utcNow->copy()->endOfDay()])->count();
            if ($count >= $dailyLimit) return 0;
        }
        if ($actionCode === 'comment_create' && ! $this->eligibleCommentReward($memberIdx, $references)) return 0;

        $balance = (int) $member->heart_balance + $amount;
        DB::table('members')->where('idx', $memberIdx)->update(['heart_balance' => $balance, 'updated_at' => now()]);
        DB::table('heart_histories')->insert([
            'member_idx' => $memberIdx, 'amount' => $amount, 'balance_after' => $balance,
            'reason' => $definition['reason'], 'vote_idx' => $references['vote_idx'] ?? null,
            'comment_idx' => $references['comment_idx'] ?? null, 'reversal_of_history_idx' => null,
            'operation_key' => $operationKey, 'actor_member_idx' => $references['actor_member_idx'] ?? null,
            'memo' => $references['memo'] ?? null, 'created_at' => now(),
        ]);
        return $amount;
    }

    private function eligibleCommentReward(int $memberIdx, array $references): bool
    {
        $commentIdx = isset($references['comment_idx']) ? (int) $references['comment_idx'] : 0;
        if ($commentIdx < 1) return false;

        $comment = DB::table('fan_post_comments as comment')
            ->join('fan_posts as post', 'post.idx', '=', 'comment.fan_post_idx')
            ->where('comment.idx', $commentIdx)
            ->where('comment.member_idx', $memberIdx)
            ->first(['comment.content', 'comment.member_idx as comment_member_idx', 'post.member_idx as post_member_idx']);
        if ($comment === null || (int) $comment->comment_member_idx === (int) $comment->post_member_idx
            || ! is_string($comment->content)) {
            return false;
        }

        $content = $comment->content;

        $normalized = $this->normalizeCommentForReward($content);
        if ($normalized === null) return false;
        $compact = preg_replace('/[\p{Z}\s]+/u', '', $normalized);
        if (! is_string($compact) || preg_match_all('/\X/u', $compact) < 5
            || preg_match('/^(\X{1,4})\1+$/u', $compact) === 1) {
            return false;
        }

        $previousComments = DB::table('heart_histories as history')
            ->join('fan_post_comments as previous_comment', 'previous_comment.idx', '=', 'history.comment_idx')
            ->where('history.member_idx', $memberIdx)
            ->where('history.reason', self::ACTIONS['comment_create']['reason'])
            ->whereNotNull('history.comment_idx')
            ->where('history.comment_idx', '<>', $commentIdx)
            ->where('previous_comment.member_idx', $memberIdx)
            ->pluck('previous_comment.content');

        foreach ($previousComments as $previousContent) {
            if (! is_string($previousContent)) continue;
            $previousNormalized = $this->normalizeCommentForReward($previousContent);
            if ($previousNormalized !== null && hash_equals($normalized, $previousNormalized)) return false;
        }

        return true;
    }

    private function normalizeCommentForReward(string $content): ?string
    {
        if (class_exists(\Normalizer::class)) {
            $content = \Normalizer::normalize($content, \Normalizer::FORM_C) ?: $content;
        }

        $content = preg_replace('/[\p{Z}\s]+/u', ' ', trim($content));
        if (! is_string($content)) return null;

        return function_exists('mb_convert_case')
            ? mb_convert_case($content, MB_CASE_FOLD, 'UTF-8')
            : strtolower($content);
    }

    public function awardPartnerBanner(int $memberIdx, int $bannerIdx, int $actorIdx): array
    {
        $now = now('UTC');
        $operationKey = 'partner_banner:'.$bannerIdx.':'.$memberIdx.':'.$now->format('Ymd');

        try {
        return DB::transaction(function () use ($memberIdx, $bannerIdx, $actorIdx, $now, $operationKey): array {
                $member = $this->lockRewardMember($memberIdx, $actorIdx);
                if ($member === null) {
                    return ['status' => 'ineligible'];
                }

                $banner = DB::table('partner_banners')->where('idx', $bannerIdx)->lockForUpdate()
                    ->first(['idx', 'heart_reward', 'reward_enabled', 'is_active', 'starts_at', 'ends_at']);
                if ($banner === null) {
                    return ['status' => 'not_found'];
                }

                if (!(bool) $banner->is_active || !(bool) $banner->reward_enabled || (int) $banner->heart_reward <= 0
                    || ($banner->starts_at !== null && $now->lt($banner->starts_at))
                    || ($banner->ends_at !== null && $now->gt($banner->ends_at))) {
                    return ['status' => 'unavailable'];
                }

                if (DB::table('heart_histories')->where('member_idx', $memberIdx)
                    ->where('operation_key', $operationKey)->exists()) {
                    return ['status' => 'already_claimed', 'heart_balance' => (int) $member->heart_balance];
                }

                $amount = (int) $banner->heart_reward;
                $balance = (int) $member->heart_balance + $amount;
                DB::table('members')->where('idx', $memberIdx)->update([
                    'heart_balance' => $balance,
                    'updated_at' => $now,
                ]);
                DB::table('heart_histories')->insert([
                    'member_idx' => $memberIdx,
                    'amount' => $amount,
                    'balance_after' => $balance,
                    'reason' => self::PARTNER_BANNER_REASON,
                    'vote_idx' => null,
                    'comment_idx' => null,
                    'reversal_of_history_idx' => null,
                    'operation_key' => $operationKey,
                    'actor_member_idx' => $actorIdx,
                    'memo' => 'Partner banner reward',
                    'created_at' => $now,
                ]);

                return ['status' => 'awarded', 'amount' => $amount, 'heart_balance' => $balance];
            });
        } catch (QueryException $exception) {
            // The unique member/operation-key index is the final guard if requests race past the row lock.
            if (!DB::table('heart_histories')->where('member_idx', $memberIdx)->where('operation_key', $operationKey)->exists()) {
                throw $exception;
            }

            return [
                'status' => 'already_claimed',
                'heart_balance' => (int) DB::table('members')->where('idx', $memberIdx)->value('heart_balance'),
            ];
        }
    }

    private function lockRewardMember(int $memberIdx, int $actorIdx): ?object
    {
        $member = DB::table('members')->where('idx', $memberIdx)->where('member_level', 1)->where('is_dummy', 0)
            ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
        if ($member !== null) {
            return $member;
        }

        $request = request();
        $actor = $request->attributes->get('activityActor');
        $activityMember = $request->attributes->get('activityMember');
        $authorizedDummyAction = (bool) $request->attributes->get('activityDummyMode', false)
            && $actor !== null && $activityMember !== null
            && (int) $actor->idx === $actorIdx && (int) $actor->member_level === 10 && (int) $actor->is_dummy === 0
            && (int) $activityMember->idx === $memberIdx;

        if (! $authorizedDummyAction) {
            return null;
        }

        return DB::table('members')->where('idx', $memberIdx)->where('member_level', 1)->where('is_dummy', 1)
            ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
    }

    public function creditManually(int $memberIdx, int $amount, string $reason, int $adminIdx, string $operationKey): bool
    {
        $member = DB::table('members')->where('idx', $memberIdx)->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
        abort_if($member === null, 404);

        $existing = DB::table('heart_histories')->where('member_idx', $memberIdx)
            ->where('operation_key', $operationKey)->first(['amount', 'actor_member_idx', 'memo']);
        if ($existing !== null) {
            abort_unless(
                (int) $existing->amount === $amount
                    && (int) $existing->actor_member_idx === $adminIdx
                    && $existing->memo === $reason,
                409,
                '이미 사용된 하트 지급 요청입니다. 새 지급은 화면을 새로 연 뒤 진행해 주세요.'
            );

            return false;
        }

        $balance = (int) $member->heart_balance + $amount;
        DB::table('members')->where('idx', $memberIdx)->update(['heart_balance' => $balance, 'updated_at' => now()]);
        DB::table('heart_histories')->insert([
            'member_idx' => $memberIdx, 'amount' => $amount, 'balance_after' => $balance,
            'reason' => 'admin_adjustment', 'vote_idx' => null, 'comment_idx' => null,
            'reversal_of_history_idx' => null, 'operation_key' => $operationKey,
            'actor_member_idx' => $adminIdx, 'memo' => $reason, 'created_at' => now(),
        ]);

        return true;
    }
}
