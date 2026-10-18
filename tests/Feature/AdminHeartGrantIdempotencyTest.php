<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminHeartGrantIdempotencyTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated Heart grant tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'GrantAdmin001', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'GrantMember02', 'email' => 'member@example.test', 'nickname' => 'Member', 'heart_balance' => 100, 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
    }

    private function grant(int $memberIdx, string $token, int $amount = 50, string $reason = 'Support adjustment')
    {
        return $this->withSession(['member_idx' => 1])->post(route('admin.hearts.credit', $memberIdx), [
            'grant_request_token' => $token,
            'amount' => $amount,
            'reason' => $reason,
        ]);
    }

    public function test_same_grant_token_is_idempotent_and_form_reuses_token_after_validation_error(): void
    {
        $this->database();

        $page = $this->withSession(['member_idx' => 1])->get(route('admin.hearts.show', 2))->assertOk();
        preg_match('/name="grant_request_token" value="([^"]+)"/', $page->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $token = $matches[1];
        $this->assertTrue(Str::isUuid($token));

        $this->from(route('admin.hearts.show', 2))->withSession(['member_idx' => 1])
            ->post(route('admin.hearts.credit', 2), [
                'grant_request_token' => $token, 'amount' => 0, 'reason' => 'Support adjustment',
            ])->assertSessionHasErrors('amount')->assertSessionHas('_old_input.grant_request_token', $token);
        $this->get(route('admin.hearts.show', 2))
            ->assertSee('name="grant_request_token" value="'.$token.'"', false);

        $this->grant(2, $token)->assertRedirect();
        $this->grant(2, $token)->assertRedirect()->assertSessionHas('admin_success', '이미 처리된 하트 지급 요청입니다.');

        $this->assertSame(150, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 2)->count());
        $this->assertSame(1, DB::table('admin_activity_logs')->where('action', 'admin_heart_credited')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'admin_credit:1:2:'.strtolower($token))->count());
    }

    public function test_fresh_tokens_allow_separate_grants_of_the_same_amount(): void
    {
        $this->database();

        $this->grant(2, (string) Str::uuid())->assertRedirect();
        $this->grant(2, (string) Str::uuid())->assertRedirect();

        $this->assertSame(200, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(2, DB::table('heart_histories')->where('member_idx', 2)->count());
    }

    public function test_non_admin_and_invalid_tokens_cannot_issue_a_grant(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->post(route('admin.hearts.credit', 2), [
            'grant_request_token' => (string) Str::uuid(), 'amount' => 50, 'reason' => 'Unauthorized',
        ])->assertForbidden();

        $this->grant(2, 'not-a-uuid')->assertSessionHasErrors('grant_request_token');
        $this->assertSame(100, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_reusing_a_token_with_different_grant_details_is_rejected(): void
    {
        $this->database();
        $token = (string) Str::uuid();

        $this->grant(2, $token)->assertRedirect();
        $this->grant(2, $token, 75)->assertStatus(409);

        $this->assertSame(150, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(1, DB::table('heart_histories')->count());
    }
}
