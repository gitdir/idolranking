<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoVoteForDummyMembers extends Command
{
    protected $signature = 'dummy-members:auto-vote';

    protected $description = 'Cast one scheduled vote for a dummy member and an active favorite idol';

    public function handle(): int
    {
        DB::transaction(function (): void {
            // Lock the enabled setting so duplicate scheduler invocations cannot vote in the same interval.
            $enabled = DB::table('site_settings')->where('setting_key', 'dummy_auto_vote_enabled')
                ->lockForUpdate()->value('setting_value');
            if ((string) $enabled !== '1') {
                return;
            }

            $settings = DB::table('site_settings')->whereIn('setting_key', [
                'dummy_auto_vote_interval_minutes', 'dummy_auto_vote_min_hearts',
                'dummy_auto_vote_max_hearts', 'dummy_auto_vote_last_minute',
            ])->pluck('setting_value', 'setting_key');

            $interval = filter_var($settings->get('dummy_auto_vote_interval_minutes'), FILTER_VALIDATE_INT);
            $minimum = filter_var($settings->get('dummy_auto_vote_min_hearts'), FILTER_VALIDATE_INT);
            $maximum = filter_var($settings->get('dummy_auto_vote_max_hearts'), FILTER_VALIDATE_INT);
            if ($interval === false || $interval < 1 || $interval > 1440
                || $minimum === false || $minimum < 1 || $minimum > 100000
                || $maximum === false || $maximum < $minimum || $maximum > 100000) {
                return;
            }

            $now = now('UTC');
            $minute = intdiv($now->timestamp, 60);
            $lastMinute = filter_var($settings->get('dummy_auto_vote_last_minute'), FILTER_VALIDATE_INT);
            if ($lastMinute !== false && $minute - $lastMinute < $interval) {
                return;
            }

            $candidate = DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                ->where('status', 'active')->whereNull('deleted_at')
                ->inRandomOrder()->first(['idx']);
            if ($candidate === null) {
                return;
            }

            $member = DB::table('members')->where('idx', $candidate->idx)->where('member_level', 1)
                ->where('is_dummy', 1)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'heart_balance', 'country_code']);
            if ($member === null) {
                return;
            }

            $idolIdx = DB::table('idols')->where('status', 'active')
                ->whereIn('type', ['individual', 'group'])->inRandomOrder()->value('idx');
            if ($idolIdx === null) {
                return;
            }

            $idolIdx = (int) $idolIdx;
            $amount = random_int($minimum, $maximum);
            $balance = (int) $member->heart_balance;
            $shortage = max(0, $amount - $balance);

            if ($shortage > 0) {
                $balance += $shortage;
                DB::table('members')->where('idx', $member->idx)->update(['heart_balance' => $balance, 'updated_at' => $now]);
                DB::table('heart_histories')->insert([
                    'member_idx' => $member->idx, 'amount' => $shortage, 'balance_after' => $balance,
                    'reason' => 'random_vote_topup', 'vote_idx' => null, 'comment_idx' => null,
                    'reversal_of_history_idx' => null, 'operation_key' => 'auto_dummy_vote_topup:'.$minute,
                    'actor_member_idx' => null, 'memo' => 'Exact Heart shortage for scheduled dummy vote',
                    'created_at' => $now,
                ]);
            }

            $voteIdx = DB::table('idol_votes')->insertGetId([
                'member_idx' => $member->idx, 'actor_member_idx' => null,
                'idol_idx' => $idolIdx, 'heart_amount' => $amount,
                'country_code_at_vote' => $member->country_code,
                'request_key' => 'auto-dummy-vote-'.$minute, 'created_at' => $now,
            ]);
            $balanceAfter = $balance - $amount;
            DB::table('members')->where('idx', $member->idx)->update([
                'heart_balance' => $balanceAfter, 'updated_at' => $now,
            ]);
            DB::table('heart_histories')->insert([
                'member_idx' => $member->idx, 'amount' => -$amount, 'balance_after' => $balanceAfter,
                'reason' => 'vote', 'vote_idx' => $voteIdx, 'comment_idx' => null,
                'reversal_of_history_idx' => null, 'operation_key' => 'vote:'.$voteIdx,
                'actor_member_idx' => null, 'memo' => 'Scheduled dummy vote', 'created_at' => $now,
            ]);
            DB::table('site_settings')->updateOrInsert(['setting_key' => 'dummy_auto_vote_last_minute'], [
                'setting_value' => (string) $minute, 'updated_by_member_idx' => null, 'updated_at' => $now,
            ]);
        }, 3);

        return self::SUCCESS;
    }
}
