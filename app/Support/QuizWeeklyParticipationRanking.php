<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class QuizWeeklyParticipationRanking
{
    public const TIMEZONE = 'Asia/Seoul';

    public static function currentWeekStart(): Carbon
    {
        return Carbon::now(self::TIMEZONE)->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    public static function previousWeekStart(): Carbon
    {
        return self::currentWeekStart()->subWeek();
    }

    /**
     * Rank completed member attempts in a half-open Asia/Seoul week interval.
     * Duplicate completions of the same quiz by a member on the same Seoul date count once.
     */
    public function rank(CarbonInterface $weekStart, CarbonInterface $weekEnd): Collection
    {
        $attempts = $this->eligibleDailyAttempts($weekStart, $weekEnd);
        $members = [];

        foreach ($attempts as $attempt) {
            $completedAt = $attempt->completed_at;
            $memberIdx = (int) $attempt->member_idx;
            $members[$memberIdx] ??= (object) [
                'member_idx' => $memberIdx,
                'nickname' => $attempt->nickname,
                'participation_count' => 0,
                'reached_at' => null,
            ];
            $members[$memberIdx]->participation_count++;
            $members[$memberIdx]->reached_at = $completedAt->copy();
        }

        return collect($members)->sort(function (object $left, object $right): int {
            $countComparison = $right->participation_count <=> $left->participation_count;
            if ($countComparison !== 0) {
                return $countComparison;
            }

            $timeComparison = $left->reached_at <=> $right->reached_at;
            if ($timeComparison !== 0) {
                return $timeComparison;
            }

            return $left->member_idx <=> $right->member_idx;
        })->values()->map(function (object $row, int $index): object {
            $row->rank = $index + 1;
            return $row;
        });
    }

    /** Rank quizzes from the same eligible, member-and-quiz daily-deduplicated attempts. */
    public function rankQuizzes(CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        $quizzes = [];

        foreach ($this->eligibleDailyAttempts($periodStart, $periodEnd) as $attempt) {
            $quizIdx = (int) $attempt->quiz_idx;
            $quizzes[$quizIdx] ??= (object) [
                'quiz_idx' => $quizIdx,
                'title' => $attempt->title,
                'creator_member_idx' => (int) $attempt->creator_member_idx,
                'completion_count' => 0,
                'reached_at' => null,
            ];
            $quizzes[$quizIdx]->completion_count++;
            $quizzes[$quizIdx]->reached_at = $attempt->completed_at->copy();
        }

        return collect($quizzes)->sort(function (object $left, object $right): int {
            $countComparison = $right->completion_count <=> $left->completion_count;
            if ($countComparison !== 0) {
                return $countComparison;
            }

            $timeComparison = $left->reached_at <=> $right->reached_at;
            return $timeComparison !== 0 ? $timeComparison : $left->quiz_idx <=> $right->quiz_idx;
        })->values()->take(3)->map(function (object $quiz, int $index): object {
            $quiz->rank = $index + 1;
            return $quiz;
        });
    }

    /** Return eligible attempts in completion order, keeping one per member, quiz and Seoul date. */
    private function eligibleDailyAttempts(CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        $startUtc = Carbon::instance($periodStart)->setTimezone('UTC');
        $endUtc = Carbon::instance($periodEnd)->setTimezone('UTC');
        $attempts = DB::table('quiz_attempts as attempt')
            ->join('quizzes as quiz', 'quiz.idx', '=', 'attempt.quiz_idx')
            ->whereNull('quiz.deleted_at')
            ->join('members as member', 'member.idx', '=', 'attempt.member_idx')
            ->where('attempt.status', 'completed')
            ->where('attempt.is_test_only', 0)
            ->whereColumn('attempt.member_idx', '<>', 'quiz.member_idx')
            ->whereNotNull('attempt.completed_at')
            ->where('attempt.completed_at', '>=', $startUtc->format('Y-m-d H:i:s.u'))
            ->where('attempt.completed_at', '<', $endUtc->format('Y-m-d H:i:s.u'))
            ->orderBy('attempt.completed_at')->orderBy('attempt.idx')
            ->get([
                'attempt.idx', 'attempt.quiz_idx', 'attempt.member_idx', 'attempt.completed_at',
                'member.nickname', 'quiz.title', 'quiz.member_idx as creator_member_idx',
            ]);

        $dailyQuizzes = [];
        return $attempts->filter(function (object $attempt) use (&$dailyQuizzes): bool {
            $completedAt = Carbon::parse($attempt->completed_at, 'UTC')->setTimezone(self::TIMEZONE);
            $dailyKey = $attempt->member_idx.':'.$attempt->quiz_idx.':'.$completedAt->toDateString();
            if (isset($dailyQuizzes[$dailyKey])) {
                return false;
            }

            $dailyQuizzes[$dailyKey] = true;
            $attempt->completed_at = $completedAt;
            return true;
        })->values();
    }
}
