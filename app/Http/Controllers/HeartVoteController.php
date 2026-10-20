<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\RandomDummyActivity;

class HeartVoteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idol_idx' => ['required', 'integer', 'min:1'],
            'heart_amount' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'heart_amount.required' => __('messages.vote_amount_required'),
            'heart_amount.integer' => __('messages.vote_amount_integer'),
            'heart_amount.min' => __('messages.vote_amount_min'),
            'request_key.regex' => 'Unable to submit the vote. Please try again.',
        ]);

        $memberIdx = (int) $request->attributes->get('activityMember')->idx;
        $actorMemberIdx = (int) $request->attributes->get('activityActor')->idx;
        $randomActivities = app(RandomDummyActivity::class);
        $adminDummyVoteMode = (bool) $request->attributes->get('activityDummyMode', false);
        $idolIdx = (int) $validated['idol_idx'];
        $heartAmount = (int) $validated['heart_amount'];
        $requestKey = $validated['request_key'];

        $reservation = $randomActivities->reserve($request, 'heart_vote', $requestKey, [
            'idol_idx' => $idolIdx, 'heart_amount' => $heartAmount,
        ], $adminDummyVoteMode);

        $result = DB::transaction(function () use ($request, $memberIdx, $actorMemberIdx, $idolIdx, $heartAmount, $requestKey, $randomActivities, $reservation): array {
            $reservation = $reservation ? $randomActivities->lockReservation($reservation) : null;
            if ($reservation?->replay) {
                $vote = DB::table('idol_votes')->where('idx', $reservation->result_idx)->first();
                abort_if($vote === null, 409, __('messages.vote_reused'));
                $member = DB::table('members')->where('idx', $vote->member_idx)->first(['idx', 'heart_balance']);
                $idol = DB::table('idols')->where('idx', $vote->idol_idx)->first(['idx', 'type', 'name']);
                abort_if($member === null || $idol === null, 409, __('messages.vote_reused'));
                return $this->result($idolIdx, $idol->name, (int) $vote->heart_amount,
                    (int) $member->heart_balance, (int) $vote->idx, true);
            }
            $activityMemberIdx = $reservation?->member_idx ?? $memberIdx;
            // Serialize all balance changes for this member so concurrent votes cannot overspend.
            $memberQuery = DB::table('members')->where('idx', $activityMemberIdx)
                ->where('status', 'active')->whereNull('deleted_at');
            if ($reservation !== null) {
                $memberQuery->where('member_level', 1)->where('is_dummy', 1);
            } else {
                $memberQuery->where('member_level', '>', 0);
            }
            $member = $memberQuery->lockForUpdate()->first(['idx', 'heart_balance']);

            if ($member === null) {
                abort(401, __('messages.vote_sign_in'));
            }

            $idol = DB::table('idols')->where('idx', $idolIdx)->where('status', 'active')
                ->whereIn('type', ['individual', 'group'])->first(['idx', 'type', 'name']);
            abort_if($idol === null, 404, __('messages.vote_target_unavailable'));

            $existingVote = DB::table('idol_votes')
                ->where('member_idx', $activityMemberIdx)->where('request_key', $requestKey)->first();
            if ($existingVote !== null) {
                if ((int) $existingVote->idol_idx !== $idolIdx || (int) $existingVote->heart_amount !== $heartAmount) {
                    abort(409, __('messages.vote_reused'));
                }

                return $this->result(
                    $idolIdx,
                    $idol->name,
                    (int) $existingVote->heart_amount,
                    (int) $member->heart_balance,
                    (int) $existingVote->idx,
                    true,
                );
            }

            $balance = (int) $member->heart_balance;
            if ($reservation !== null && $heartAmount > $balance) {
                // Randomly assigned admin votes receive only the exact shortage, and this history row
                // rolls back together with the vote if any later write fails.
                $shortage = $heartAmount - $balance;
                $balance = $heartAmount;
                $topUpAt = now('UTC');
                DB::table('members')->where('idx', $activityMemberIdx)->update([
                    'heart_balance' => $balance,
                    'updated_at' => $topUpAt,
                ]);
                DB::table('heart_histories')->insert([
                    'member_idx' => $activityMemberIdx,
                    'amount' => $shortage,
                    'balance_after' => $balance,
                    'reason' => 'random_vote_topup',
                    'vote_idx' => null,
                    'comment_idx' => null,
                    'reversal_of_history_idx' => null,
                    'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
                    'actor_member_idx' => $actorMemberIdx,
                    'memo' => 'Exact Heart shortage for random dummy vote',
                    'created_at' => $topUpAt,
                ]);
            } elseif ($heartAmount > $balance) {
                throw ValidationException::withMessages([
                    'heart_amount' => __('messages.vote_amount_max', ['amount' => number_format($balance)]),
                ]);
            }

            $balanceAfter = $balance - $heartAmount;
            $createdAt = now('UTC');
            $voteIdx = DB::table('idol_votes')->insertGetId([
                'member_idx' => $activityMemberIdx,
                'actor_member_idx' => $actorMemberIdx,
                'idol_idx' => $idolIdx,
                'heart_amount' => $heartAmount,
                'request_key' => $requestKey,
                'created_at' => $createdAt,
            ]);

            DB::table('members')->where('idx', $activityMemberIdx)->update([
                'heart_balance' => $balanceAfter,
                'updated_at' => $createdAt,
            ]);

            DB::table('heart_histories')->insert([
                'member_idx' => $activityMemberIdx,
                'amount' => -$heartAmount,
                'balance_after' => $balanceAfter,
                'reason' => 'vote',
                'vote_idx' => $voteIdx,
                'comment_idx' => null,
                'reversal_of_history_idx' => null,
                'operation_key' => 'vote:'.$voteIdx,
                'actor_member_idx' => $actorMemberIdx,
                'memo' => null,
                'created_at' => $createdAt,
            ]);

            if ($reservation) {
                $randomActivities->complete($reservation, 'vote', (int) $voteIdx);
                $randomActivities->log($request, $reservation, 'vote_cast', '랜덤 더미 회원이 하트를 투표했습니다.');
            }

            return $this->result($idolIdx, $idol->name, $heartAmount, $balanceAfter, (int) $voteIdx, false);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => __('messages.vote_success'),
            'data' => $result,
        ], $result['duplicate'] ? 200 : 201);
    }

    private function result(
        int $idolIdx,
        string $targetName,
        int $heartAmount,
        int $heartBalance,
        int $voteIdx,
        bool $duplicate,
    ): array
    {
        $monthStart = now('UTC')->startOfMonth();
        $monthlyTotal = (int) DB::table('idol_votes')->where('idol_idx', $idolIdx)
            ->where('created_at', '>=', $monthStart)
            ->where('created_at', '<', $monthStart->copy()->addMonth())
            ->sum('heart_amount');

        return [
            'vote_idx' => $voteIdx,
            'idol_idx' => $idolIdx,
            'target_name' => $targetName,
            'heart_amount' => $heartAmount,
            'heart_balance' => $heartBalance,
            'monthly_vote_total' => $monthlyTotal,
            'vote_month' => $monthStart->format('Y-m'),
            'duplicate' => $duplicate,
        ];
    }
}
