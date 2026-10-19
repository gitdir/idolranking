<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveActivityMember;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActingDummyMemberAuditTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated audit tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER NOT NULL, action TEXT NOT NULL, target_type TEXT NOT NULL, target_idx INTEGER NOT NULL, reason TEXT, summary TEXT, created_at TEXT NOT NULL)');

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'admin001', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'dummy001', 'email' => 'dummy1@example.test', 'nickname' => 'Dummy 1', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummy002', 'email' => 'dummy2@example.test', 'nickname' => 'Dummy 2', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'member001', 'email' => 'member@example.test', 'nickname' => 'Member', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
    }

    public function test_entry_and_exit_are_audited_with_admin_and_dummy_attribution(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 2,
        ])->assertRedirect(route('home'));

        $entry = DB::table('admin_activity_logs')->where('action', 'dummy_activity_mode_entered')->first();
        $this->assertNotNull($entry);
        $this->assertSame(1, (int) $entry->actor_member_idx);
        $this->assertSame('dummy_member', $entry->target_type);
        $this->assertSame(2, (int) $entry->target_idx);
        $this->assertNotEmpty($entry->created_at);
        $this->assertSame(2, (int) session(ResolveActivityMember::SESSION_KEY));

        $this->post(route('admin.acting-dummy-member'), [])->assertRedirect(route('home'));

        $exit = DB::table('admin_activity_logs')->where('action', 'dummy_activity_mode_exited')->first();
        $this->assertNotNull($exit);
        $this->assertSame(1, (int) $exit->actor_member_idx);
        $this->assertSame(2, (int) $exit->target_idx);
        $this->assertNotEmpty($exit->created_at);
        $this->assertFalse(session()->has(ResolveActivityMember::SESSION_KEY));
        $this->assertSame(2, DB::table('admin_activity_logs')->count());
    }

    public function test_switching_dummy_accounts_records_the_transition(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 2,
        ])->assertRedirect();
        $this->post(route('admin.acting-dummy-member'), ['dummy_member_idx' => 3])->assertRedirect();

        $switch = DB::table('admin_activity_logs')->where('action', 'dummy_activity_mode_switched')->first();
        $this->assertNotNull($switch);
        $this->assertSame(1, (int) $switch->actor_member_idx);
        $this->assertSame(3, (int) $switch->target_idx);
        $this->assertStringContainsString('#2', $switch->summary);
        $this->assertStringContainsString('#3', $switch->summary);
        $this->assertSame(3, (int) session(ResolveActivityMember::SESSION_KEY));
        $this->assertSame(2, DB::table('admin_activity_logs')->count());
    }

    public function test_unauthorized_and_invalid_requests_do_not_create_audit_entries(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 4])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 2,
        ])->assertForbidden();

        $this->withSession([
            'member_idx' => 1,
            ResolveActivityMember::SESSION_KEY => 2,
            ResolveActivityMember::MODE_ENABLED_SESSION_KEY => true,
            ResolveActivityMember::MODE_ACTOR_SESSION_KEY => 1,
        ])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 4,
        ])->assertStatus(422);

        $this->assertSame(0, DB::table('admin_activity_logs')->count());
        $this->assertSame(2, (int) session(ResolveActivityMember::SESSION_KEY));
    }

    public function test_admin_dummy_mode_defaults_on_and_can_be_toggled_without_changing_actor(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post(route('admin.acting-dummy-mode'), ['enabled' => '0'])
            ->assertRedirect();
        $this->assertSame(1, (int) session('member_idx'));
        $this->assertSame(2, (int) session(ResolveActivityMember::SESSION_KEY));
        $this->assertFalse((bool) session(ResolveActivityMember::MODE_ENABLED_SESSION_KEY));

        $this->post(route('admin.acting-dummy-mode'), ['enabled' => '1'])->assertRedirect();
        $this->assertSame(1, (int) session('member_idx'));
        $this->assertSame(2, (int) session(ResolveActivityMember::SESSION_KEY));
        $this->assertTrue((bool) session(ResolveActivityMember::MODE_ENABLED_SESSION_KEY));
        $this->assertSame(3, DB::table('admin_activity_logs')->count());
    }

    public function test_direct_dummy_account_cannot_toggle_dummy_activity_mode(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-mode'), ['enabled' => '1'])
            ->assertForbidden();
        $this->assertSame(0, DB::table('admin_activity_logs')->count());
    }

    public function test_reselecting_the_current_dummy_does_not_create_a_duplicate_transition(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 2,
        ])->assertRedirect();
        $this->post(route('admin.acting-dummy-member'), ['dummy_member_idx' => 2])->assertRedirect();

        $this->assertSame(1, DB::table('admin_activity_logs')->count());
        $this->assertSame('dummy_activity_mode_entered', DB::table('admin_activity_logs')->value('action'));
    }

    public function test_random_mode_toggle_persists_audits_once_and_clears_manual_dummy_selection(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1, ResolveActivityMember::SESSION_KEY => 2])
            ->post(route('admin.random-dummy-mode'), ['enabled' => '1'])->assertRedirect();
        $this->assertTrue((bool) session(\App\Support\RandomDummyActivity::SESSION_KEY));
        $this->assertFalse(session()->has(ResolveActivityMember::SESSION_KEY));
        $this->assertSame('random_dummy_mode_enabled', DB::table('admin_activity_logs')->latest('idx')->value('action'));

        $this->post(route('admin.random-dummy-mode'), ['enabled' => '1'])->assertRedirect();
        $this->assertSame(1, DB::table('admin_activity_logs')->count());

        $this->post(route('admin.random-dummy-mode'), [])->assertRedirect();
        $this->assertFalse(session()->has(\App\Support\RandomDummyActivity::SESSION_KEY));
        $this->assertSame(2, DB::table('admin_activity_logs')->count());
        $this->assertSame('random_dummy_mode_disabled', DB::table('admin_activity_logs')->latest('idx')->value('action'));
        $this->assertSame(1, (int) DB::table('admin_activity_logs')->latest('idx')->value('actor_member_idx'));
    }

    public function test_normal_members_cannot_toggle_random_mode_or_create_audit_rows(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 4])->post(route('admin.random-dummy-mode'), ['enabled' => '1'])
            ->assertForbidden();

        $this->assertSame(0, DB::table('admin_activity_logs')->count());
    }
}
