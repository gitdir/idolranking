<?php

namespace App\Console\Commands;

use App\Support\HeartRewardManager;
use App\Support\QuizWeeklyParticipationRanking;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SettleQuizWeeklyRanking extends Command
{
    protected $signature = 'quizzes:settle-weekly-ranking {--week= : Monday date (Asia/Seoul) to settle; defaults to the previous week}';

    protected $description = 'Settle rewards for the previous week\'s top popular Quizzes';

    public function handle(QuizWeeklyParticipationRanking $ranking, HeartRewardManager $rewards): int
    {
        $weekOption = $this->option('week');
        $weekStart = is_string($weekOption) && $weekOption !== ''
            ? Carbon::parse($weekOption, QuizWeeklyParticipationRanking::TIMEZONE)->startOfDay()
            : QuizWeeklyParticipationRanking::previousWeekStart();

        if ($weekStart->dayOfWeekIso !== 1) {
            $this->error('The settlement week must start on Monday in Asia/Seoul.');
            return self::FAILURE;
        }
        if ($weekStart->greaterThanOrEqualTo(QuizWeeklyParticipationRanking::currentWeekStart())) {
            $this->error('The selected week has not ended yet.');
            return self::FAILURE;
        }

        $weekEnd = $weekStart->copy()->addWeek();
        $settled = DB::transaction(function () use ($weekStart, $weekEnd, $ranking, $rewards): bool {
            $weekDate = $weekStart->toDateString();
            $now = now('UTC');

            DB::table('quiz_weekly_settlements')->insertOrIgnore([
                'week_start_date' => $weekDate,
                'week_end_date' => $weekEnd->toDateString(),
                'created_at' => $now,
            ]);

            $settlement = DB::table('quiz_weekly_settlements')->where('week_start_date', $weekDate)
                ->lockForUpdate()->first();
            if ($settlement === null) {
                throw new RuntimeException('Could not reserve the weekly Quiz settlement.');
            }
            if ($settlement->settled_at !== null) {
                return false;
            }

            foreach ($ranking->rankQuizzes($weekStart, $weekEnd)->take(3) as $winner) {
                $actionCode = 'quiz_weekly_rank_'.$winner->rank;
                $creatorIdx = (int) $winner->creator_member_idx;
                $operationKey = 'quiz_weekly:'.$weekDate.':'.$creatorIdx.':quiz:'.$winner->quiz_idx.':rank:'.$winner->rank;
                $configuredAmount = $rewards->enabledAmount($actionCode);
                $awardedAmount = 0;
                if ($configuredAmount > 0) {
                    $awardedAmount = $rewards->award($creatorIdx, $actionCode, $operationKey, [
                        'memo' => 'Weekly Popular Quiz rank '.$winner->rank.' reward for quiz '.$winner->quiz_idx.' (week starting '.$weekDate.')',
                    ]);
                    if ($awardedAmount !== $configuredAmount) {
                        throw new RuntimeException("Could not award the configured weekly Quiz reward for rank {$winner->rank}.");
                    }
                }

                DB::table('quiz_weekly_settlement_rewards')->insert([
                    'settlement_idx' => (int) $settlement->idx,
                    'rank_position' => (int) $winner->rank,
                    'member_idx' => $creatorIdx,
                    'quiz_idx' => (int) $winner->quiz_idx,
                    'participation_count' => (int) $winner->completion_count,
                    'reached_at' => $winner->reached_at->copy()->setTimezone('UTC'),
                    'reward_amount' => $awardedAmount,
                    'operation_key' => $operationKey,
                    'created_at' => $now,
                ]);
            }

            DB::table('quiz_weekly_settlements')->where('idx', $settlement->idx)->update([
                'settled_at' => $now,
            ]);

            return true;
        }, 3);

        if ($settled) {
            $this->info('Settled Quiz Participation Ranking for week starting '.$weekStart->toDateString().'.');
        } else {
            $this->info('Quiz Participation Ranking for week starting '.$weekStart->toDateString().' was already settled.');
        }

        return self::SUCCESS;
    }
}
