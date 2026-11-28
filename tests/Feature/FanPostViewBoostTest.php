<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FanPostViewBoostTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, status TEXT, deleted_at TEXT, view_count INTEGER DEFAULT 0)");
        DB::statement("CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_by_member_idx INTEGER, updated_at TEXT)");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminCode001', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'member_level' => 10],
            ['idx' => 2, 'member_code' => 'MemberCode001', 'email' => 'member@example.test', 'nickname' => 'Member', 'member_level' => 1],
        ]);
        DB::table('idols')->insert([
            ['idx' => 1, 'status' => 'active'],
            ['idx' => 2, 'status' => 'inactive'],
        ]);
        DB::table('fan_posts')->insert([
            ['idx' => 1, 'idol_idx' => 1, 'status' => 'published', 'deleted_at' => null],
            ['idx' => 2, 'idol_idx' => 1, 'status' => 'published', 'deleted_at' => null],
            ['idx' => 3, 'idol_idx' => 1, 'status' => 'hidden', 'deleted_at' => null],
            ['idx' => 4, 'idol_idx' => 1, 'status' => 'published', 'deleted_at' => '2026-10-01 00:00:00'],
            ['idx' => 5, 'idol_idx' => 2, 'status' => 'published', 'deleted_at' => null],
        ]);
    }

    public function test_admin_toggle_controls_one_random_view_increment_per_run(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->get(route('admin.settings.edit', 'management'))
            ->assertOk()->assertSee('팬포스트 조회수 자동 증가');

        Artisan::call('fan-posts:boost-views');
        $this->assertSame([0, 0, 0, 0, 0], DB::table('fan_posts')->orderBy('idx')->pluck('view_count')->all());

        $this->withSession(['member_idx' => 2])->put(route('admin.settings.update', 'management'), [
            'fan_post_view_boost_enabled' => '1',
            'dummy_auto_vote_enabled' => '0', 'dummy_auto_create_enabled' => '0', 'dummy_auto_vote_interval_minutes' => 5,
            'dummy_auto_vote_min_hearts' => 10, 'dummy_auto_vote_max_hearts' => 50,
        ])->assertForbidden();

        $this->withSession(['member_idx' => 1])->put(route('admin.settings.update', 'management'), [
            'fan_post_view_boost_enabled' => '1',
            'dummy_auto_vote_enabled' => '0', 'dummy_auto_create_enabled' => '0', 'dummy_auto_vote_interval_minutes' => 5,
            'dummy_auto_vote_min_hearts' => 10, 'dummy_auto_vote_max_hearts' => 50,
        ])->assertRedirect();
        $this->assertDatabaseHas('site_settings', ['setting_key' => 'fan_post_view_boost_enabled', 'setting_value' => '1']);

        Artisan::call('fan-posts:boost-views');
        $views = DB::table('fan_posts')->orderBy('idx')->pluck('view_count')->all();
        $this->assertSame(1, count(array_filter($views, fn ($count) => (int) $count > 0)));
        $this->assertGreaterThanOrEqual(1, (int) $views[0] + (int) $views[1]);
        $this->assertLessThanOrEqual(5, (int) $views[0] + (int) $views[1]);
        $this->assertSame([0, 0, 0], array_map('intval', array_slice($views, 2)));

        $this->withSession(['member_idx' => 1])->put(route('admin.settings.update', 'management'), [
            'fan_post_view_boost_enabled' => '0',
            'dummy_auto_vote_enabled' => '0', 'dummy_auto_create_enabled' => '0', 'dummy_auto_vote_interval_minutes' => 5,
            'dummy_auto_vote_min_hearts' => 10, 'dummy_auto_vote_max_hearts' => 50,
        ])->assertRedirect();
        Artisan::call('fan-posts:boost-views');
        $this->assertSame($views, DB::table('fan_posts')->orderBy('idx')->pluck('view_count')->all());
    }
}
