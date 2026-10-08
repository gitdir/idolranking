<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicFanPostTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, profile_image_path TEXT, member_level INTEGER DEFAULT 1, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, media_type TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'MemberCode01', 'nickname' => 'public_fan']);
        DB::table('idols')->insert(['idx' => 1]);
        foreach (range(1, 21) as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'media_type' => $idx === 1 ? 'youtube' : 'text',
                'youtube_video_id' => $idx === 1 ? 'dQw4w9WgXcQ' : null,
                'content' => 'Public post '.$idx, 'view_count' => $idx,
                'status' => 'published', 'created_at' => $idx === 1 ? '2026-02-01 00:00:00' : sprintf('2026-01-%02d 00:00:00', min($idx, 28)),
            ]);
        }
        DB::table('fan_post_likes')->insert(['fan_post_idx' => 1, 'member_idx' => 1]);
        DB::table('fan_post_comments')->insert(['idx' => 1, 'fan_post_idx' => 2, 'status' => 'published']);
    }

    public function test_public_page_uses_real_posts_sorting_member_links_and_pagination(): void
    {
        $this->database();

        $this->get('/fan-posts')->assertOk()->assertSee('Fan Posts')
            ->assertSee('/member/MemberCode01/fan-posts', false)
            ->assertSee('YouTube video thumbnail', false)
            ->assertSee('/fan-posts?page=2', false);
        $this->get('/fan-posts?sort=liked')->assertOk()->assertSee('sort=liked&amp;page=2', false);
        $this->get('/fan-posts?sort=commented')->assertOk()->assertSeeInOrder(['Public post 2', 'Public post 21']);

        $homepagePosts = app(\App\Support\PublicFanPostList::class)->take('commented', 14);
        $this->assertCount(14, $homepagePosts);
        $this->assertSame(2, $homepagePosts[0]['fan_post_idx']);
        $this->assertSame('MemberCode01', $homepagePosts[0]['member_code']);
    }

    public function test_header_has_fan_posts_before_register_idol(): void
    {
        $response = $this->get('/')->assertOk();
        $response->assertSeeInOrder(['Fan Posts', 'Register Idol'])
            ->assertSee('No fan posts yet.')
            ->assertDontSee('golden_army');
    }
}
