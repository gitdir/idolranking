<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveActivityMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FanPostDistributionTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, type TEXT DEFAULT 'individual', status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, title TEXT, content TEXT, status TEXT, queued_at TEXT, published_at TEXT, deleted_at TEXT, updated_at TEXT, media_type TEXT, image_path TEXT, youtube_video_id TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)');
        DB::statement('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_by_member_idx INTEGER, updated_at TEXT)');
        DB::statement('CREATE TABLE heart_reward_settings (action_code TEXT PRIMARY KEY, heart_amount INTEGER, is_enabled INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminCode001', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0],
            ['idx' => 2, 'member_code' => 'MemberCode001', 'email' => 'member@example.test', 'nickname' => 'Member', 'member_level' => 1, 'is_dummy' => 0],
            ['idx' => 3, 'member_code' => 'DummyCode001', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'member_level' => 1, 'is_dummy' => 1],
        ]);
        DB::table('idols')->insert(['idx' => 1, 'name' => 'Test Idol']);
        DB::table('fan_posts')->insert([
            ['idx' => 1, 'idol_idx' => 1, 'member_idx' => 2, 'title' => 'First queued', 'content' => 'Content 1', 'status' => 'queued', 'queued_at' => '2026-10-19 09:00:00', 'media_type' => 'text'],
            ['idx' => 2, 'idol_idx' => 1, 'member_idx' => 2, 'title' => 'Second queued', 'content' => 'Content 2', 'status' => 'queued', 'queued_at' => '2026-10-19 09:00:00', 'media_type' => 'text'],
            ['idx' => 3, 'idol_idx' => 1, 'member_idx' => 2, 'title' => 'Third queued', 'content' => 'Content 3', 'status' => 'queued', 'queued_at' => '2026-10-19 09:00:00', 'media_type' => 'text'],
        ]);
    }

    public function test_admin_can_manage_queue_and_save_distribution_settings(): void
    {
        $this->database();
        $this->withSession(['member_idx' => 1])->get(route('admin.fan-post-distribution.index'))
            ->assertOk()->assertSee('value="10"', false)->assertSee('value="2"', false)
            ->assertSee('value="09:00"', false)->assertSee('value="23:00"', false)
            ->assertSeeInOrder(['First queued', 'Second queued']);

        $this->withSession(['member_idx' => 1])->put(route('admin.fan-post-distribution.settings'), [
            'interval_minutes' => 15, 'max_posts' => 3, 'start_time' => '08:30', 'end_time' => '22:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('site_settings', ['setting_key' => 'fan_post_distribution_interval_minutes', 'setting_value' => '15']);

        $this->withSession(['member_idx' => 1])->patch(route('admin.fan-post-distribution.publish', 1))->assertRedirect();
        $this->assertDatabaseHas('fan_posts', ['idx' => 1, 'status' => 'published']);
        $this->assertNotNull(DB::table('fan_posts')->where('idx', 1)->value('published_at'));

        $this->withSession(['member_idx' => 1])->patch(route('admin.fan-post-distribution.cancel', 2))->assertRedirect();
        $this->assertDatabaseHas('fan_posts', ['idx' => 2, 'status' => 'cancelled']);
        $this->assertSame(4, DB::table('admin_activity_logs')->count());
    }

    public function test_non_admin_cannot_manage_or_publish_queued_posts(): void
    {
        $this->database();
        $this->withSession(['member_idx' => 2])->get(route('admin.fan-post-distribution.index'))->assertForbidden();
        $this->withSession(['member_idx' => 2])->patch(route('admin.fan-post-distribution.publish', 1))->assertForbidden();
        $this->assertDatabaseHas('fan_posts', ['idx' => 1, 'status' => 'queued']);
    }

    public function test_only_an_authenticated_non_dummy_admin_sees_or_can_submit_distribution_option(): void
    {
        $this->database();
        $adminPage = $this->withSession(['member_idx' => 1])->get(route('fan-post.create'))->assertOk()
            ->assertSee('name="auto_distribute" value="1" checked', false)
            ->assertSee('data-idol-loading="'.__('quiz.idol_loading').'"', false)
            ->assertSee(__('quiz.idol_choose_category'));
        $this->assertTrue($this->dummyModeCheckboxIsChecked($adminPage->getContent(), 'desktop'));
        $this->assertTrue($this->dummyModeCheckboxIsChecked($adminPage->getContent(), 'drawer'));
        $this->withSession(['member_idx' => 1, 'acting_dummy_member_idx' => 3])->get(route('fan-post.create'))->assertOk()
            ->assertSee('name="auto_distribute" value="1" checked', false);
        $disabledPage = $this->withSession([
            'member_idx' => 1,
            ResolveActivityMember::SESSION_KEY => 3,
            ResolveActivityMember::MODE_ENABLED_SESSION_KEY => false,
            ResolveActivityMember::MODE_ACTOR_SESSION_KEY => 1,
        ])->get(route('fan-post.create'))->assertOk()->assertSee('name="auto_distribute" value="1" checked', false);
        $this->assertFalse($this->dummyModeCheckboxIsChecked($disabledPage->getContent(), 'desktop'));
        $this->withSession(['member_idx' => 2])->get(route('fan-post.create'))->assertOk()
            ->assertDontSee('name="auto_distribute"', false);
        $this->withSession(['member_idx' => 3])->get(route('fan-post.create'))->assertOk()
            ->assertDontSee('name="auto_distribute"', false)
            ->assertDontSee('acting-dummy-mode', false);
        $this->withSession(['member_idx' => 2])->post(route('fan-post.store'), ['auto_distribute' => '1'])
            ->assertForbidden();
    }

    private function dummyModeCheckboxIsChecked(string $html, string $location): bool
    {
        $document = new \DOMDocument();
        $previousErrorSetting = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorSetting);
        }
        $checkbox = $document->getElementById('acting-dummy-mode-'.$location);

        return $checkbox !== null && $checkbox->hasAttribute('checked');
    }

    public function test_worker_publishes_oldest_posts_within_kst_hours_and_interval_limit(): void
    {
        $this->database();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 09:05:00', 'Asia/Seoul'));

        Artisan::call('fan-posts:publish-queued');
        $this->assertSame(['published', 'published', 'queued'], DB::table('fan_posts')->orderBy('idx')->pluck('status')->all());

        Artisan::call('fan-posts:publish-queued');
        $this->assertSame(2, DB::table('fan_posts')->where('status', 'published')->count());

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 09:15:00', 'Asia/Seoul'));
        Artisan::call('fan-posts:publish-queued');
        $this->assertDatabaseHas('fan_posts', ['idx' => 3, 'status' => 'published']);
    }
}
