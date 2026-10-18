<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateDummyMemberBatchCommandTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated command tests require pdo_sqlite.');
        }

        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT \'active\', country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, email_verified_at TEXT, last_login_at TEXT, password_changed_at TEXT, created_by_member_idx INTEGER, referred_by_member_idx INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE restricted_nicknames (idx INTEGER PRIMARY KEY AUTOINCREMENT, word TEXT, match_type TEXT, is_enabled INTEGER)');
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'BatchAdmin01', 'email' => 'admin@example.test', 'nickname' => 'batch_admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'BatchMember1', 'email' => 'member@example.test', 'nickname' => 'batch_member', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
    }

    public function test_dry_run_previews_without_writing_and_creation_requires_explicit_confirmation(): void
    {
        $this->database();
        $options = ['--batch-id' => 'dev-preview-01', '--admin-id' => '1'];

        $this->artisan('idolranking:create-dummy-members', $options + ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(2, DB::table('members')->count());
        $this->artisan('idolranking:create-dummy-members', $options)->assertFailed();
        $this->assertSame(2, DB::table('members')->count());
    }

    public function test_confirmed_admin_batch_creates_exactly_fifty_audited_korean_dummy_members_once(): void
    {
        $this->database();
        $options = ['--batch-id' => 'dev-round-one', '--admin-id' => '1', '--confirm' => true];

        $this->artisan('idolranking:create-dummy-members', $options)->assertSuccessful();

        $members = DB::table('members')->where('is_dummy', 1)->get();
        $this->assertCount(50, $members);
        $this->assertSame(50, $members->pluck('email')->unique()->count());
        $this->assertSame(50, $members->pluck('member_code')->unique()->count());
        $this->assertSame(50, $members->pluck('nickname')->unique()->count());
        foreach ($members as $member) {
            $this->assertSame('KR', $member->country_code);
            $this->assertSame(1, (int) $member->member_level);
            $this->assertSame(1, (int) $member->is_dummy);
            $this->assertSame('active', $member->status);
            $this->assertSame(1, (int) $member->created_by_member_idx);
            $this->assertMatchesRegularExpression('/^idolranking\.dummy\.[a-f0-9]{16}\.\d{2}@gmail\.com$/', $member->email);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $member->member_code);
            $this->assertNotSame('', $member->password_hash);
        }

        $this->assertDatabaseHas('admin_activity_logs', [
            'actor_member_idx' => 1,
            'action' => 'dummy_member_batch_created',
            'target_type' => 'dummy_member',
            'reason' => 'dummy_batch:dev-round-one',
        ]);
        $this->assertSame(1, DB::table('admin_activity_logs')->count());

        $this->artisan('idolranking:create-dummy-members', $options)->assertFailed();
        $this->assertSame(50, DB::table('members')->where('is_dummy', 1)->count());
    }

    public function test_non_admin_cannot_run_the_bulk_creation_command(): void
    {
        $this->database();

        $this->artisan('idolranking:create-dummy-members', [
            '--batch-id' => 'dev-round-one', '--admin-id' => '2', '--confirm' => true,
        ])->assertFailed();

        $this->assertSame(2, DB::table('members')->count());
        $this->assertSame(0, DB::table('admin_activity_logs')->count());
    }
}
