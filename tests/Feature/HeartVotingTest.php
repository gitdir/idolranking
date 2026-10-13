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

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
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

    public function test_admin_and_dummy_members_can_vote(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->postJson('/votes', [
            'idol_idx' => 10, 'heart_amount' => 5, 'request_key' => 'vote-request-admin-00001',
        ])->assertCreated();
        $this->withSession(['member_idx' => 3])->postJson('/votes', [
            'idol_idx' => 11, 'heart_amount' => 5, 'request_key' => 'vote-request-dummy-00001',
        ])->assertCreated();

        $this->assertSame(35, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(25, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseHas('idol_votes', ['member_idx' => 3, 'actor_member_idx' => 3]);
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
