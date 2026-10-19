<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminReportQueueTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated admin queue tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, title TEXT, status TEXT, queued_at TEXT, published_at TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_reports (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reason TEXT, details TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comment_reports (idx INTEGER PRIMARY KEY, status TEXT)");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, created_at TEXT)");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY, member_idx INTEGER, amount INTEGER, reason TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY, member_idx INTEGER, name TEXT, status TEXT, created_at TEXT)");
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminQueue01', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'member_level' => 10, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'AuthorQueue02', 'email' => 'author@example.test', 'nickname' => 'Author', 'member_level' => 1, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'Reporter003', 'email' => 'reporter@example.test', 'nickname' => 'Reporter', 'member_level' => 1, 'status' => 'active'],
        ]);
        DB::table('fan_posts')->insert([
            ['idx' => 10, 'member_idx' => 2, 'title' => 'Rights report target', 'status' => 'published'],
            ['idx' => 11, 'member_idx' => 2, 'title' => 'Spam report target', 'status' => 'published'],
        ]);
        DB::table('fan_post_reports')->insert([
            ['idx' => 20, 'fan_post_idx' => 10, 'member_idx' => 3, 'actor_member_idx' => 3, 'reason' => 'copyright', 'details' => 'Rights concern', 'status' => 'pending'],
            ['idx' => 21, 'fan_post_idx' => 11, 'member_idx' => 3, 'actor_member_idx' => 3, 'reason' => 'spam', 'details' => 'Spam concern', 'status' => 'pending'],
        ]);
    }

    public function test_admin_can_filter_fan_post_reports_by_rights_reason(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->get(route('admin.fan-post-reports.index', [
            'status' => 'pending', 'reason' => 'copyright',
        ]))->assertOk()
            ->assertSee('Rights report target')
            ->assertDontSee('Spam report target')
            ->assertSee('name="reason"', false);
    }

    public function test_non_admin_cannot_open_report_management_queue(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->get(route('admin.fan-post-reports.index'))
            ->assertForbidden();
    }

    public function test_dashboard_highlights_pending_rights_and_comment_reports(): void
    {
        $this->database();
        DB::table('fan_post_comments')->insert([
            ['idx' => 1, 'status' => 'published', 'created_at' => now()],
        ]);
        DB::table('fan_post_comment_reports')->insert([
            ['idx' => 1, 'status' => 'pending'], ['idx' => 2, 'status' => 'pending'],
        ]);

        $this->withSession(['member_idx' => 1])->get(route('admin.index'))->assertOk()
            ->assertSee(e(route('admin.fan-post-reports.index', ['status' => 'pending', 'reason' => 'copyright'])), false)
            ->assertSee('대기 중인 권리 침해 신고')
            ->assertSee(e(route('admin.fan-post-comment-reports.index', ['status' => 'pending'])), false)
            ->assertSee('대기 중인 댓글 신고');
    }
}
