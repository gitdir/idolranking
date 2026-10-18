<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDummyMemberManagementTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated dummy member tests require pdo_sqlite.');
        }

        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT \'active\', country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, email_verified_at TEXT, last_login_at TEXT, password_changed_at TEXT, created_by_member_idx INTEGER, referred_by_member_idx INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE member_login_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, login_type TEXT, result TEXT, ip_address TEXT, user_agent TEXT, failure_reason TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, title TEXT, status TEXT, deleted_at TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE restricted_nicknames (idx INTEGER PRIMARY KEY AUTOINCREMENT, word TEXT, match_type TEXT, is_enabled INTEGER)');

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminCode001', 'email' => 'admin@example.test', 'password_hash' => Hash::make('AdminPass123'), 'nickname' => 'admin_user', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'NormalCode01', 'email' => 'member@example.test', 'password_hash' => Hash::make('MemberPass123'), 'nickname' => 'normal_user', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
    }

    private function validDummy(array $overrides = []): array
    {
        return array_replace([
            'email' => 'Dummy.Account@example.test',
            'nickname' => 'dummy_account',
            'country_code' => 'KR',
            'password' => 'DummyPass123',
            'password_confirmation' => 'DummyPass123',
        ], $overrides);
    }

    public function test_admin_creates_audited_dummy_account_that_can_log_in_normally(): void
    {
        $this->database();

        $page = $this->withSession(['member_idx' => 1])->get(route('admin.members.dummy.create'))
            ->assertOk()->assertSee('더미 계정 생성');
        $this->assertStringContainsString('name="password_confirmation"', $page->getContent());

        $this->withSession(['member_idx' => 1])->post(route('admin.members.dummy.store'), $this->validDummy())
            ->assertRedirect();
        $dummy = DB::table('members')->where('email', 'dummy.account@example.test')->first();

        $this->assertNotNull($dummy);
        $this->assertSame(1, (int) $dummy->member_level);
        $this->assertSame(1, (int) $dummy->is_dummy);
        $this->assertSame(1, (int) $dummy->created_by_member_idx);
        $this->assertSame('active', $dummy->status);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $dummy->member_code);
        $this->assertTrue(Hash::check('DummyPass123', $dummy->password_hash));
        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_member_idx' => 1, 'action' => 'dummy_member_created',
            'target_type' => 'dummy_member', 'target_idx' => $dummy->idx,
        ]);
        $this->assertSame(0, DB::table('heart_histories')->count());

        $this->withSession([])->postJson('/login', [
            'email' => 'dummy.account@example.test', 'password' => 'DummyPass123',
        ])->assertOk()->assertJson(['success' => true])->assertSessionHas('member_idx', $dummy->idx);
    }

    public function test_guests_and_normal_members_cannot_create_dummy_accounts(): void
    {
        $this->database();

        $this->post(route('admin.members.dummy.store'), $this->validDummy())->assertRedirect(route('home'));
        $this->withSession(['member_idx' => 2])->post(route('admin.members.dummy.store'), $this->validDummy())->assertForbidden();

        $this->assertSame(2, DB::table('members')->count());
        $this->assertSame(0, DB::table('admin_activity_logs')->count());
    }

    public function test_dummy_accounts_are_identified_filterable_and_use_existing_suspend_restore_audit(): void
    {
        $this->database();
        $this->withSession(['member_idx' => 1])->post(route('admin.members.dummy.store'), $this->validDummy())->assertRedirect();
        $dummy = DB::table('members')->where('is_dummy', 1)->first();

        $list = $this->withSession(['member_idx' => 1])->get(route('admin.members.index', ['dummy' => 1]))
            ->assertOk()->assertSee('dummy_account')->assertSee('더미 계정');
        $this->assertStringNotContainsString('normal_user', $list->getContent());
        $this->get(route('admin.members.show', $dummy->idx))->assertOk()->assertSee('생성 관리자 회원 ID')->assertSee('더미 계정 생성');
        $this->get(route('admin.activity-logs.index'))->assertOk()->assertSee('더미 계정 생성')->assertSee('더미 회원');

        $this->from(route('admin.members.show', $dummy->idx))->withSession(['member_idx' => 1])
            ->patch(route('admin.members.status', $dummy->idx), ['status' => 'suspended', 'reason' => '테스트 제한'])
            ->assertRedirect();
        $this->assertDatabaseHas('members', ['idx' => $dummy->idx, 'status' => 'suspended', 'is_dummy' => 1]);
        $this->assertDatabaseHas('admin_activity_logs', ['actor_member_idx' => 1, 'action' => 'member_restricted', 'target_idx' => $dummy->idx]);

        $this->from(route('admin.members.show', $dummy->idx))->withSession(['member_idx' => 1])
            ->patch(route('admin.members.status', $dummy->idx), ['status' => 'active', 'reason' => '테스트 복원'])
            ->assertRedirect();
        $this->assertDatabaseHas('members', ['idx' => $dummy->idx, 'status' => 'active', 'is_dummy' => 1]);
        $this->assertDatabaseHas('admin_activity_logs', ['actor_member_idx' => 1, 'action' => 'member_restored', 'target_idx' => $dummy->idx]);
    }

    public function test_dummy_creation_validates_credentials_and_never_accepts_client_role_flags(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->from(route('admin.members.dummy.create'))
            ->post(route('admin.members.dummy.store'), $this->validDummy([
                'email' => 'admin@example.test', 'nickname' => 'x', 'password' => 'weak',
                'password_confirmation' => 'different', 'member_level' => 10, 'is_dummy' => 0,
            ]))->assertSessionHasErrors(['email', 'nickname', 'password']);

        $this->withSession(['member_idx' => 1])->post(route('admin.members.dummy.store'), $this->validDummy([
            'member_level' => 10, 'is_dummy' => 0,
        ]))->assertRedirect();
        $created = DB::table('members')->where('email', 'dummy.account@example.test')->first();
        $this->assertSame(1, (int) $created->member_level);
        $this->assertSame(1, (int) $created->is_dummy);
        $this->assertSame(3, DB::table('members')->count());
    }

    public function test_restricted_nickname_validation_uses_korean_admin_feedback(): void
    {
        $this->database();
        DB::table('restricted_nicknames')->insert([
            'word' => 'blocked', 'match_type' => 'exact', 'is_enabled' => 1,
        ]);

        $this->withSession(['member_idx' => 1])->from(route('admin.members.dummy.create'))
            ->post(route('admin.members.dummy.store'), $this->validDummy(['nickname' => 'blocked']))
            ->assertSessionHasErrors('nickname');

        $message = session('errors')->getBag('default')->first('nickname');
        $this->assertSame('사용할 수 없는 닉네임입니다.', $message);
        $this->assertSame(2, DB::table('members')->count());
    }
}
