<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RandomDummyActivity
{
    public const SESSION_KEY = 'random_dummy_activity_mode';

    public function enabled(Request $request): bool
    {
        $actor = $request->attributes->get('currentMember');

        return (int) ($actor?->member_level ?? 0) === 10
            && filter_var($request->session()->get(self::SESSION_KEY, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** A completed registration can be replayed even after its one-time form key was cleared. */
    public function completed(Request $request, string $action, string $requestKey, array $payload): ?object
    {
        if (! $this->enabled($request)) {
            return null;
        }

        $actor = $request->attributes->get('currentMember');
        $row = DB::table('admin_dummy_activity_requests')->where('actor_member_idx', $actor->idx)
            ->where('action_code', $action)->where('request_key', $requestKey)
            ->whereNotNull('completed_at')->first();
        if ($row === null) {
            return null;
        }

        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                abort_if(! hash_equals($row->payload_hash, $payloadHash), 409, '동일한 요청 키로 다른 내용을 제출할 수 없습니다.');

        return $this->reservation($row);
    }

    /** Persist the actor/action/request identity before the activity transaction starts. */
    public function reserve(Request $request, string $action, string $requestKey, array $payload): ?object
    {
        if (! $this->enabled($request)) {
            return null;
        }

        $actor = $request->attributes->get('currentMember');
        abort_unless((int) $actor->member_level === 10, 403);
        $payloadHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $action, $requestKey, $payloadHash): object {
            // Serialize reservation creation for this administrator in a short transaction.
            $lockedAdmin = DB::table('members')->where('idx', $actor->idx)->where('member_level', 10)
                ->where('status', 'active')->whereNull('deleted_at')->lockForUpdate()->first(['idx']);
            abort_if($lockedAdmin === null, 403);

            $existing = DB::table('admin_dummy_activity_requests')
                ->where('actor_member_idx', $actor->idx)->where('action_code', $action)
                ->where('request_key', $requestKey)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_if(! hash_equals($existing->payload_hash, $payloadHash), 409, '동일한 요청 키로 다른 내용을 제출할 수 없습니다.');

                return $this->reservation($existing);
            }

            $dummy = DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                ->where('status', 'active')->whereNull('deleted_at')->inRandomOrder()->lockForUpdate()
                ->first(['idx']);
            if ($dummy === null) {
                throw ValidationException::withMessages(['random_dummy' => '사용할 수 있는 활성 더미 회원이 없습니다.']);
            }

            $id = DB::table('admin_dummy_activity_requests')->insertGetId([
                'actor_member_idx' => $actor->idx,
                'member_idx' => $dummy->idx,
                'action_code' => $action,
                'request_key' => $requestKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (object) [
                'idx' => (int) $id,
                'actor_member_idx' => (int) $actor->idx,
                'member_idx' => (int) $dummy->idx,
                'action_code' => $action,
                'request_key' => $requestKey,
                'result_type' => null,
                'result_idx' => null,
                'replay' => false,
            ];
        });
    }

    /** Re-read under a row lock so concurrent retries observe the committed result. */
    public function lockReservation(object $reservation): object
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('A random dummy reservation must be locked inside the activity transaction.');
        }

        $row = DB::table('admin_dummy_activity_requests')->where('idx', $reservation->idx)
            ->where('actor_member_idx', $reservation->actor_member_idx)
            ->where('action_code', $reservation->action_code)
            ->where('request_key', $reservation->request_key)
            ->lockForUpdate()->first();

        abort_if($row === null || (int) $row->member_idx !== (int) $reservation->member_idx, 409);

        return $this->reservation($row);
    }

    private function reservation(object $row): object
    {
        return (object) [
            'idx' => (int) $row->idx,
            'actor_member_idx' => (int) $row->actor_member_idx,
            'member_idx' => (int) $row->member_idx,
            'action_code' => $row->action_code,
            'request_key' => $row->request_key,
            'result_type' => $row->result_type,
            'result_idx' => $row->result_idx === null ? null : (int) $row->result_idx,
            'replay' => $row->completed_at !== null,
        ];
    }

    public function complete(object $reservation, string $resultType, int $resultIdx): void
    {
        if ($reservation->replay) {
            return;
        }
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('A random dummy reservation must be completed inside the activity transaction.');
        }

        $updated = DB::table('admin_dummy_activity_requests')->where('idx', $reservation->idx)
            ->whereNull('completed_at')->update([
                'result_type' => $resultType,
                'result_idx' => $resultIdx,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new \LogicException('Random dummy activity reservation was not pending when completed.');
        }
    }

    public function member(object $reservation): ?object
    {
        return DB::table('members')->where('idx', $reservation->member_idx)
            ->where('member_level', 1)->where('is_dummy', 1)->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx', 'member_code', 'email', 'nickname', 'country_code', 'profile_image_path', 'heart_balance', 'member_level', 'is_dummy']);
    }

    public function log(Request $request, object $reservation, string $action, string $summary): void
    {
        if ($reservation->replay) {
            return;
        }

        $actor = $request->attributes->get('currentMember');
        AdminActivityLogger::record((int) $actor->idx, 'random_dummy_'.$action, 'dummy_member', (int) $reservation->member_idx,
            (string) $reservation->idx, $summary);
    }

    /** Verify the persisted dummy action before allowing the normal-member reward exclusion to be bypassed. */
    public function permitsDummyReward(int $reservationIdx, int $recipientIdx, int $actorIdx, string $rewardAction): bool
    {
        $reservation = DB::table('admin_dummy_activity_requests')->where('idx', $reservationIdx)
            ->where('actor_member_idx', $actorIdx)->where('member_idx', $recipientIdx)->whereNotNull('completed_at')->first();
        if ($reservation === null || ! $this->adminActor($actorIdx)) {
            return false;
        }

        $resultTable = match ([$rewardAction, $reservation->action_code, $reservation->result_type]) {
            ['fan_post_create', 'fan_post_create', 'fan_post'] => ['fan_posts', 'idx'],
            ['comment_create', 'comment_create', 'comment'] => ['fan_post_comments', 'idx'],
            ['idol_registration_approved', 'idol_registration', 'registration_request'] => ['idol_registration_requests', 'idx'],
            default => null,
        };
        if ($resultTable === null || (int) $reservation->result_idx < 1) {
            return false;
        }

        return DB::table($resultTable[0])->where($resultTable[1], $reservation->result_idx)
            ->where('member_idx', $recipientIdx)->where('actor_member_idx', $actorIdx)->exists();
    }

    public function permitsDummyRecommendationReward(int $reservationIdx, int $recipientIdx, int $actorIdx): bool
    {
        $reservation = DB::table('admin_dummy_activity_requests')->where('idx', $reservationIdx)
            ->where('actor_member_idx', $actorIdx)->where('action_code', 'fan_post_recommend')
            ->whereNotNull('completed_at')->first();
        if ($reservation === null || ! $this->adminActor($actorIdx)) {
            return false;
        }

        return DB::table('fan_post_likes as recommendation')
            ->join('fan_posts as post', 'post.idx', '=', 'recommendation.fan_post_idx')
            ->where('recommendation.fan_post_idx', $reservation->result_idx)
            ->where('recommendation.member_idx', $reservation->member_idx)
            ->where('recommendation.actor_member_idx', $actorIdx)
            ->where('post.member_idx', $recipientIdx)->exists();
    }

    private function adminActor(int $actorIdx): bool
    {
        return DB::table('members')->where('idx', $actorIdx)->where('member_level', 10)
            ->where('status', 'active')->whereNull('deleted_at')->exists();
    }
}
