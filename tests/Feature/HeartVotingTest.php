<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeartVotingTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement("CREATE TABLE admin_dummy_activity_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, member_idx INTEGER, action_code TEXT, request_key TEXT, payload_hash TEXT, result_type TEXT, result_idx INTEGER, created_at TEXT, completed_at TEXT, updated_at TEXT, UNIQUE(actor_member_idx, action_code, request_key))");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, type TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, actor_member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, request_key TEXT, created_at TEXT, UNIQUE (member_idx, request_key))");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER UNIQUE, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'normalMember', 'heart_balance' => 100, 'member_level' => 1, 'is_dummy' => 0],
            ['idx' => 2, 'member_code' => 'adminMember1', 'heart_balance' => 40, 'member_level' => 10, 'is_dummy' => 0],
            ['idx' => 3, 'member_code' => 'dummyMember1', 'heart_balance' => 30, 'member_level' => 1, 'is_dummy' => 1],
        ]);
        DB::table('idols')->insert([
            ['idx' => 10, 'name' => 'Active Idol', 'type' => 'individual', 'status' => 'active'],
            ['idx' => 11, 'name' => 'Active Group', 'type' => 'group', 'status' => 'active'],
            ['idx' => 12, 'name' => 'Hidden Idol', 'type' => 'individual', 'status' => 'inactive'],
        ]);
    }

    private function addActiveDummy(int $idx, int $heartBalance): void
    {
        DB::table('members')->insert([
            'idx' => $idx, 'member_code' => 'dummyMember'.$idx, 'email' => 'dummy'.$idx.'@example.test',
            'nickname' => 'Dummy '.$idx, 'heart_balance' => $heartBalance,
            'member_level' => 1, 'is_dummy' => 1, 'status' => 'active',
        ]);
    }

    private function adminDummyModeSession(int $selectedDummyIdx = 3): array
    {
        return [
            'member_idx' => 2,
            \App\Http\Middleware\ResolveActivityMember::SESSION_KEY => $selectedDummyIdx,
            \App\Http\Middleware\ResolveActivityMember::MODE_ENABLED_SESSION_KEY => true,
            \App\Http\Middleware\ResolveActivityMember::MODE_ACTOR_SESSION_KEY => 2,
        ];
    }

    public function test_vote_requires_authentication(): void
    {
        $this->postJson('/votes', [
            'idol_idx' => 10,
            'heart_amount' => 1,
            'request_key' => 'vote-request-guest-0001',
        ])->assertUnauthorized();
    }

    public function test_vote_deducts_balance_and_creates_linked_audit_rows_atomically(): void
    {
        $this->database();

        $response = $this->withSession(['member_idx' => 1])->postJson('/votes', [
            'idol_idx' => 10,
            'heart_amount' => 25,
            'request_key' => 'vote-request-normal-0001',
        ])->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('data.target_name', 'Active Idol')
            ->assertJsonPath('data.heart_amount', 25)
            ->assertJsonPath('data.heart_balance', 75)
            ->assertJsonPath('data.monthly_vote_total', 25);

        $voteIdx = $response->json('data.vote_idx');
        $this->assertDatabaseHas('idol_votes', [
            'idx' => $voteIdx, 'member_idx' => 1, 'idol_idx' => 10, 'heart_amount' => 25,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => -25, 'balance_after' => 75, 'reason' => 'vote',
            'vote_idx' => $voteIdx, 'operation_key' => 'vote:'.$voteIdx, 'actor_member_idx' => 1,
        ]);
        $this->assertSame(75, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_retry_is_idempotent_but_new_votes_for_idols_and_groups_are_allowed(): void
    {
        $this->database();
        $payload = ['idol_idx' => 10, 'heart_amount' => 10, 'request_key' => 'vote-request-retry-0001'];

        $this->withSession(['member_idx' => 1])->postJson('/votes', $payload)->assertCreated();
        $this->postJson('/votes', $payload)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->postJson('/votes', [
            'idol_idx' => 11, 'heart_amount' => 15, 'request_key' => 'vote-request-group-0001',
        ])->assertCreated()->assertJsonPath('data.heart_balance', 75);

        $this->assertSame(2, DB::table('idol_votes')->count());
        $this->assertSame(2, DB::table('heart_histories')->count());
    }

    public function test_invalid_target_or_amount_does_not_change_balance_or_write_history(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->postJson('/votes', [
            'idol_idx' => 12, 'heart_amount' => 1, 'request_key' => 'vote-request-hidden-0001',
        ])->assertNotFound();
        $this->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 101, 'request_key' => 'vote-request-over-00001',
        ])->assertUnprocessable()->assertJsonValidationErrors('heart_amount');
        $this->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 0, 'request_key' => 'vote-request-zero-00001',
        ])->assertUnprocessable()->assertJsonValidationErrors('heart_amount');

        $this->assertSame(100, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, DB::table('idol_votes')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_admin_dummy_mode_votes_as_a_dummy_and_can_be_disabled(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'vote-request-admin-dummy-0001',
        ])->assertCreated();

        $this->post(route('admin.acting-dummy-mode'), ['enabled' => '0'])->assertRedirect();
        $this->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'vote-request-admin-self-0001',
        ])->assertCreated();

        $this->withSession(['member_idx' => 3])->postJson('/votes', [
            'idol_idx' => 11, 'heart_amount' => 5, 'request_key' => 'vote-request-dummy-00001',
        ])->assertCreated();

        $this->assertSame(35, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(20, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseHas('idol_votes', ['member_idx' => 3, 'actor_member_idx' => 2, 'request_key' => 'vote-request-admin-dummy-0001']);
        $this->assertDatabaseHas('idol_votes', ['member_idx' => 2, 'actor_member_idx' => 2, 'request_key' => 'vote-request-admin-self-0001']);
        $this->assertDatabaseHas('idol_votes', ['member_idx' => 3, 'actor_member_idx' => 3]);
    }

    public function test_admin_dummy_checkbox_randomly_selects_and_tops_up_a_zero_balance_dummy_once(): void
    {
        $this->database();
        $this->addActiveDummy(4, 0);
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 0]);
        $randomSelectionQuerySeen = false;
        DB::listen(function ($query) use (&$randomSelectionQuerySeen): void {
            if (str_contains(strtolower($query->sql), 'members') && str_contains(strtolower($query->sql), 'random')) {
                $randomSelectionQuerySeen = true;
            }
        });
        $payload = ['idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'checkbox-random-zero-000001'];

        $response = $this->withSession($this->adminDummyModeSession())->postJson('/votes', $payload)
            ->assertCreated()->assertJsonPath('data.heart_balance', 0);
        $reservation = DB::table('admin_dummy_activity_requests')->where('action_code', 'heart_vote')->first();
        $vote = DB::table('idol_votes')->where('idx', $response->json('data.vote_idx'))->first();

        $this->assertTrue($randomSelectionQuerySeen);
        $this->assertContains((int) $reservation->member_idx, [3, 4]);
        $this->assertSame((int) $reservation->member_idx, (int) $vote->member_idx);
        $this->assertSame(2, (int) $vote->actor_member_idx);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => (int) $reservation->member_idx,
            'actor_member_idx' => 2,
            'amount' => 5,
            'balance_after' => 5,
            'reason' => 'random_vote_topup',
            'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => (int) $reservation->member_idx,
            'actor_member_idx' => 2,
            'amount' => -5,
            'balance_after' => 0,
            'reason' => 'vote',
            'vote_idx' => $vote->idx,
        ]);

        // A retry after the mode toggle still reuses the pending logical vote reservation.
        $this->post(route('admin.acting-dummy-mode'), ['enabled' => '0'])->assertRedirect();
        $this->postJson('/votes', $payload)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, DB::table('idol_votes')->count());
        $this->assertSame(2, DB::table('heart_histories')->count());
        $this->assertSame(1, DB::table('admin_dummy_activity_requests')->where('action_code', 'heart_vote')->count());
    }

    public function test_admin_dummy_checkbox_credits_only_an_insufficient_balance_shortage(): void
    {
        $this->database();
        $this->addActiveDummy(4, 3);
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 3]);
        $payload = ['idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'checkbox-random-short-0001'];

        $response = $this->withSession($this->adminDummyModeSession())->postJson('/votes', $payload)
            ->assertCreated()->assertJsonPath('data.heart_balance', 0);
        $reservation = DB::table('admin_dummy_activity_requests')->where('action_code', 'heart_vote')->first();
        $vote = DB::table('idol_votes')->first();

        $this->assertSame((int) $reservation->member_idx, (int) $vote->member_idx);
        $this->assertSame(2, (int) $vote->actor_member_idx);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => (int) $reservation->member_idx,
            'amount' => 2,
            'balance_after' => 5,
            'reason' => 'random_vote_topup',
            'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
        ]);
        $this->assertSame(0, (int) DB::table('members')->where('idx', $reservation->member_idx)->value('heart_balance'));
    }

    public function test_admin_dummy_checkbox_with_sufficient_balance_does_not_top_up(): void
    {
        $this->database();
        $this->addActiveDummy(4, 10);
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 10]);

        $response = $this->withSession($this->adminDummyModeSession())->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'checkbox-random-enough-01',
        ])->assertCreated()->assertJsonPath('data.heart_balance', 5);
        $reservation = DB::table('admin_dummy_activity_requests')->where('action_code', 'heart_vote')->first();
        $voteIdx = (int) $response->json('data.vote_idx');

        $this->assertGreaterThan(0, $voteIdx);
        $this->assertSame((int) $reservation->member_idx, (int) DB::table('idol_votes')->where('idx', $voteIdx)->value('member_idx'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => (int) $reservation->member_idx,
            'amount' => -5,
            'reason' => 'vote',
        ]);
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'random_vote_topup')->count());
        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }

    public function test_directly_logged_in_dummy_still_cannot_vote_without_enough_hearts(): void
    {
        $this->database();
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 0]);

        $this->withSession(['member_idx' => 3])->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'direct-dummy-short-00001',
        ])->assertUnprocessable()->assertJsonValidationErrors('heart_amount');

        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, DB::table('idol_votes')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, DB::table('admin_dummy_activity_requests')->count());
    }

    public function test_random_mode_vote_with_sufficient_balance_does_not_credit_extra_hearts_and_replays_once(): void
    {
        $this->database();
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 150]);
        $request = [
            'idol_idx' => 10, 'heart_amount' => 100, 'request_key' => 'random-vote-request-key-0001',
        ];

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson('/votes', $request)->assertCreated()->assertJsonPath('data.heart_balance', 50);
        $vote = DB::table('idol_votes')->first();
        $this->assertSame(3, (int) $vote->member_idx);
        $this->assertSame(2, (int) $vote->actor_member_idx);
        $this->assertSame(50, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => -100,
            'operation_key' => 'vote:'.$vote->idx,
        ]);

        $this->postJson('/votes', $request)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, DB::table('idol_votes')->count());
        $this->assertSame(1, DB::table('heart_histories')->count());
        $this->assertSame(50, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'random_vote_topup')->count());
    }

    public function test_random_mode_credits_only_a_partial_shortage_and_replay_does_not_credit_again(): void
    {
        $this->database();
        $request = ['idol_idx' => 10, 'heart_amount' => 100, 'request_key' => 'random-vote-partial-shortage-01'];

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson('/votes', $request)->assertCreated()->assertJsonPath('data.heart_balance', 0);
        $reservation = DB::table('admin_dummy_activity_requests')->first();
        $vote = DB::table('idol_votes')->first();
        $this->assertSame(3, (int) $reservation->member_idx);
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => 70,
            'balance_after' => 100, 'reason' => 'random_vote_topup',
            'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => -100,
            'balance_after' => 0, 'reason' => 'vote', 'vote_idx' => $vote->idx,
        ]);

        $this->postJson('/votes', $request)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(2, DB::table('heart_histories')->count());
        $this->assertSame(1, DB::table('idol_votes')->count());
        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }

    public function test_random_mode_credits_the_full_shortage_from_zero_balance(): void
    {
        $this->database();
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 0]);
        $request = ['idol_idx' => 10, 'heart_amount' => 100, 'request_key' => 'random-vote-zero-balance-0001'];

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson('/votes', $request)->assertCreated()->assertJsonPath('data.heart_balance', 0);

        $reservation = DB::table('admin_dummy_activity_requests')->first();
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => 100,
            'balance_after' => 100, 'reason' => 'random_vote_topup',
            'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
        ]);
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }

    public function test_failed_random_vote_retry_keeps_the_reserved_dummy_and_completes_once(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 4, 'member_code' => 'dummyMember2', 'email' => 'dummy2@example.test', 'nickname' => 'Dummy 2',
            'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active',
        ]);
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 0]);
        $payload = ['idol_idx' => 10, 'heart_amount' => 100, 'request_key' => 'random-vote-failure-retry-001'];

        DB::statement("CREATE TRIGGER fail_random_vote BEFORE INSERT ON idol_votes BEGIN SELECT RAISE(ABORT, 'simulated vote insert failure'); END");
        $this->withoutExceptionHandling();
        try {
            $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
                ->postJson('/votes', $payload);
            $this->fail('The simulated vote insertion failure should abort the activity transaction.');
        } catch (\Illuminate\Database\QueryException) {
            // Expected: activity data rolls back while the earlier reservation remains committed.
        }

        DB::statement('DROP TRIGGER fail_random_vote');
        $reservation = DB::table('admin_dummy_activity_requests')->where('request_key', $payload['request_key'])->first();
        $this->assertNotNull($reservation);
        $this->assertNull($reservation->completed_at);
        $this->assertContains((int) $reservation->member_idx, [3, 4]);
        $this->assertSame(0, DB::table('idol_votes')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 4)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());

        $this->withExceptionHandling();
        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson('/votes', $payload)->assertCreated();

        $vote = DB::table('idol_votes')->first();
        $completedReservation = DB::table('admin_dummy_activity_requests')->where('idx', $reservation->idx)->first();
        $this->assertSame((int) $reservation->member_idx, (int) $vote->member_idx);
        $this->assertSame((int) $reservation->member_idx, (int) $completedReservation->member_idx);
        $this->assertNotNull($completedReservation->completed_at);
        $this->assertSame(2, DB::table('heart_histories')->count());
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => (int) $reservation->member_idx,
            'actor_member_idx' => 2,
            'amount' => 100,
            'reason' => 'random_vote_topup',
            'operation_key' => 'random_dummy_vote_topup:'.$reservation->idx,
        ]);

        $this->postJson('/votes', $payload)->assertOk()->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, DB::table('idol_votes')->count());
        $this->assertSame(2, DB::table('heart_histories')->count());
    }

    public function test_random_mode_keeps_distinct_request_tokens_as_independent_reservations(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 4, 'member_code' => 'dummyMember2', 'email' => 'dummy2@example.test', 'nickname' => 'Dummy 2',
            'heart_balance' => 30, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active',
        ]);
        $session = ['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true];

        $this->withSession($session)->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 1, 'request_key' => 'random-vote-independent-key-01',
        ])->assertCreated();
        $this->postJson('/votes', [
            'idol_idx' => 11, 'heart_amount' => 1, 'request_key' => 'random-vote-independent-key-02',
        ])->assertCreated();

        $reservations = DB::table('admin_dummy_activity_requests')->where('action_code', 'heart_vote')->orderBy('idx')->get();
        $this->assertCount(2, $reservations);
        $this->assertNotSame($reservations[0]->request_key, $reservations[1]->request_key);
        $this->assertContains((int) $reservations[0]->member_idx, [3, 4]);
        $this->assertContains((int) $reservations[1]->member_idx, [3, 4]);
        $this->assertSame(2, DB::table('idol_votes')->count());
        $this->assertSame(2, DB::table('heart_histories')->count());
    }

    public function test_random_mode_rejects_safely_when_no_active_dummy_is_available(): void
    {
        $this->database();
        DB::table('members')->where('idx', 3)->update(['status' => 'suspended']);

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson('/votes', [
                'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'random-vote-no-dummy-0001',
            ])->assertUnprocessable()->assertJsonValidationErrors('random_dummy');

        $this->assertSame(0, DB::table('idol_votes')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, DB::table('admin_dummy_activity_requests')->count());
    }

    public function test_admin_can_vote_as_selected_dummy_without_changing_admin_identity(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));

        $response = $this->postJson('/votes', [
            'idol_idx' => 10,
            'heart_amount' => 5,
            'request_key' => 'vote-request-delegated-0001',
        ])->assertCreated()->assertJsonPath('data.heart_balance', 25);

        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(25, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseHas('idol_votes', ['idx' => $response->json('data.vote_idx'), 'member_idx' => 3, 'actor_member_idx' => 2]);
        $this->assertDatabaseHas('heart_histories', ['member_idx' => 3, 'actor_member_idx' => 2, 'amount' => -5]);
        $this->assertSame(2, (int) session('member_idx'));
    }

    public function test_admin_dummy_checkbox_off_votes_as_the_admin_without_top_up(): void
    {
        $this->database();
        DB::table('members')->where('idx', 3)->update(['heart_balance' => 0]);

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));
        $this->post(route('admin.acting-dummy-mode'), ['enabled' => '0'])->assertRedirect();

        $response = $this->postJson('/votes', [
            'idol_idx' => 10,
            'heart_amount' => 5,
            'request_key' => 'vote-request-checkbox-off-admin-01',
        ])->assertCreated();

        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(35, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertDatabaseHas('idol_votes', [
            'idx' => $response->json('data.vote_idx'), 'member_idx' => 2, 'actor_member_idx' => 2,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 2, 'actor_member_idx' => 2, 'amount' => -5, 'reason' => 'vote',
        ]);
        $this->assertSame(0, DB::table('admin_dummy_activity_requests')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'random_vote_topup')->count());
    }

    public function test_admin_cannot_select_a_normal_member_or_another_admin(): void
    {
        $this->database();

        foreach ([1, 2] as $memberIdx) {
            $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
                'dummy_member_idx' => $memberIdx,
            ])->assertStatus(422);
        }

        $this->assertSame(40, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }
}
