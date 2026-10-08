<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class HeartRewardManager
{
    public const ACTIONS = [
        'watch_ads' => ['name' => 'Watch Ads', 'amount' => 50, 'daily_limit' => 10, 'limit_type' => 'daily', 'reason' => 'ad_reward', 'description' => 'Rewarded ad completion'],
        'invite_friend' => ['name' => '친구 초대 성공', 'amount' => 1000, 'daily_limit' => 0, 'limit_type' => 'daily', 'reason' => 'invite_friend_reward', 'description' => 'Invited friend registration completion'],
        'signup' => ['name' => '가입 완료', 'amount' => 1000, 'daily_limit' => 0, 'limit_type' => 'account', 'reason' => 'signup_reward', 'description' => '회원 가입 완료 시 최초 1회'],
        'profile_complete' => ['name' => '프로필 완성', 'amount' => 100, 'daily_limit' => 0, 'limit_type' => 'account', 'reason' => 'profile_complete', 'description' => '프로필 최초 완성 시 1회'],
        'daily_attendance' => ['name' => '일일 출석', 'amount' => 100, 'daily_limit' => 1, 'limit_type' => 'daily', 'reason' => 'daily_attendance', 'description' => '1일 1회 지급'],
        'fan_post_create' => ['name' => '팬포스트 작성', 'amount' => 30, 'daily_limit' => 20, 'limit_type' => 'daily', 'reason' => 'fan_post_reward', 'description' => '사진 또는 YouTube 영상 포함 시 지급'],
        'comment_create' => ['name' => '댓글 작성', 'amount' => 10, 'daily_limit' => 20, 'limit_type' => 'daily', 'reason' => 'comment_reward', 'description' => '댓글 작성 시 지급'],
        'fan_post_recommend_received' => ['name' => '팬포스트 추천 받음', 'amount' => 1, 'daily_limit' => 100, 'limit_type' => 'daily', 'reason' => 'fan_post_recommend_received', 'description' => '작성자가 추천을 받을 때 지급'],
        'idol_registration_approved' => ['name' => '아이돌/그룹 등록 승인', 'amount' => 3000, 'daily_limit' => 0, 'limit_type' => 'request', 'reason' => 'idol_registration_approved', 'description' => '관리자가 등록 신청을 승인한 경우 지급'],
    ];

    public const REFERRAL_SESSION_KEY = 'referral_member_code';

    public function eligibleInviterIdx(mixed $memberCode): ?int
    {
        if (!is_string($memberCode) || preg_match('/^[A-Za-z0-9]{12}$/', $memberCode) !== 1) {
            return null;
        }

        $idx = DB::table('members')->where('member_code', $memberCode)
            ->where('member_level', '>', 0)->where('is_dummy', 0)
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
                ->where('member_level', '>', 0)->where('is_dummy', 0)
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
            ->first(['heart_amount', 'is_enabled']);

        if ($setting === null || ! (bool) $setting->is_enabled) {
            return 0;
        }

        return max(0, (int) $setting->heart_amount);
    }

    public function award(int $memberIdx, string $actionCode, string $operationKey, array $references = []): int
    {
        $definition = self::ACTIONS[$actionCode] ?? null;
        if ($definition === null) return 0;
        $setting = DB::table('heart_reward_settings')->where('action_code', $actionCode)->first();
        if ($setting === null || ! (bool) $setting->is_enabled) return 0;
        $amount = (int) $setting->heart_amount;
        if ($amount <= 0) return 0;

        $member = DB::table('members')->where('idx', $memberIdx)->where('member_level', '>', 0)
            ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
        if ($member === null || DB::table('heart_histories')->where('member_idx', $memberIdx)->where('operation_key', $operationKey)->exists()) return 0;

        $dailyLimit = max(0, (int) $setting->daily_limit);
        // Zero means unlimited. Account/request rewards are made one-time by their stable operation key.
        if ($definition['limit_type'] === 'daily' && $dailyLimit > 0) {
            $count = DB::table('heart_histories')->where('member_idx', $memberIdx)->where('reason', $definition['reason'])
                ->whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->count();
            if ($count >= $dailyLimit) return 0;
        }

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

    public function creditManually(int $memberIdx, int $amount, string $reason, int $adminIdx): void
    {
        $member = DB::table('members')->where('idx', $memberIdx)->whereNull('deleted_at')->lockForUpdate()->first(['idx', 'heart_balance']);
        abort_if($member === null, 404);
        $balance = (int) $member->heart_balance + $amount;
        DB::table('members')->where('idx', $memberIdx)->update(['heart_balance' => $balance, 'updated_at' => now()]);
        DB::table('heart_histories')->insert([
            'member_idx' => $memberIdx, 'amount' => $amount, 'balance_after' => $balance,
            'reason' => 'admin_adjustment', 'vote_idx' => null, 'comment_idx' => null,
            'reversal_of_history_idx' => null, 'operation_key' => 'admin_credit:'.Str::uuid(),
            'actor_member_idx' => $adminIdx, 'memo' => $reason, 'created_at' => now(),
        ]);
    }
}
