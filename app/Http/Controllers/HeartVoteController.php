<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HeartVoteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'idol_idx' => ['required', 'integer', 'min:1'],
            'heart_amount' => ['required', 'integer', 'min:1'],
            'request_key' => ['required', 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9-]+$/'],
        ], [
            'heart_amount.required' => 'Enter the number of Hearts to vote.',
            'heart_amount.integer' => 'The vote amount must be a whole number.',
            'heart_amount.min' => 'You must vote at least 1 Heart.',
            'request_key.regex' => 'Unable to submit the vote. Please try again.',
        ]);

        $memberIdx = (int) $request->attributes->get('currentMember')->idx;
        $idolIdx = (int) $validated['idol_idx'];
        $heartAmount = (int) $validated['heart_amount'];
        $requestKey = $validated['request_key'];

        $result = DB::transaction(function () use ($memberIdx, $idolIdx, $heartAmount, $requestKey): array {
            // Serialize all balance changes for this member so concurrent votes cannot overspend.
            $member = DB::table('members')->where('idx', $memberIdx)
                ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                ->lockForUpdate()->first(['idx', 'heart_balance']);

            if ($member === null) {
                abort(401, 'Please sign in to continue.');
            }

            $idol = DB::table('idols')->where('idx', $idolIdx)->where('status', 'active')
                ->whereIn('type', ['individual', 'group'])->first(['idx', 'type', 'name']);
            abort_if($idol === null, 404, 'This Idol or Group is not available for voting.');

            $existingVote = DB::table('idol_votes')
                ->where('member_idx', $memberIdx)->where('request_key', $requestKey)->first();
            if ($existingVote !== null) {
                if ((int) $existingVote->idol_idx !== $idolIdx || (int) $existingVote->heart_amount !== $heartAmount) {
                    abort(409, 'This vote request has already been used.');
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
            if ($heartAmount > $balance) {
                throw ValidationException::withMessages([
                    'heart_amount' => 'You can vote up to '.number_format($balance).' Hearts.',
                ]);
            }

            $balanceAfter = $balance - $heartAmount;
            $createdAt = now();
            $voteIdx = DB::table('idol_votes')->insertGetId([
                'member_idx' => $memberIdx,
                'idol_idx' => $idolIdx,
                'heart_amount' => $heartAmount,
                'request_key' => $requestKey,
                'created_at' => $createdAt,
            ]);

            DB::table('members')->where('idx', $memberIdx)->update([
                'heart_balance' => $balanceAfter,
                'updated_at' => $createdAt,
            ]);

            DB::table('heart_histories')->insert([
                'member_idx' => $memberIdx,
                'amount' => -$heartAmount,
                'balance_after' => $balanceAfter,
                'reason' => 'vote',
                'vote_idx' => $voteIdx,
                'comment_idx' => null,
                'reversal_of_history_idx' => null,
                'operation_key' => 'vote:'.$voteIdx,
                'actor_member_idx' => $memberIdx,
                'memo' => null,
                'created_at' => $createdAt,
            ]);

            return $this->result($idolIdx, $idol->name, $heartAmount, $balanceAfter, (int) $voteIdx, false);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Your Heart vote has been counted.',
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
        $monthStart = now()->startOfMonth();
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
