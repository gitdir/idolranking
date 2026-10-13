<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdolDetailTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, debut_date TEXT, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, status TEXT, view_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, media_type TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)");

        DB::table('idols')->insert([
            ['idx' => 101, 'type' => 'individual', 'gender' => 'female', 'name' => 'Test Idol', 'status' => 'active'],
            ['idx' => 102, 'type' => 'group', 'gender' => 'female', 'name' => 'Test Group', 'status' => 'active'],
        ]);
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'memberCode01', 'nickname' => 'active_author', 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'memberCode02', 'nickname' => 'inactive_author', 'status' => 'inactive'],
        ]);
        DB::table('fan_posts')->insert([
            ['idx' => 1001, 'idol_idx' => 101, 'member_idx' => 1, 'media_type' => 'text', 'content' => 'Older published Idol post', 'view_count' => 10, 'status' => 'published', 'created_at' => '2026-09-01 10:00:00'],
            ['idx' => 1002, 'idol_idx' => 101, 'member_idx' => 1, 'media_type' => 'text', 'content' => 'Newest published Idol post', 'view_count' => 20, 'status' => 'published', 'created_at' => '2026-09-02 10:00:00'],
            ['idx' => 1003, 'idol_idx' => 101, 'member_idx' => 1, 'media_type' => 'text', 'content' => 'Hidden Idol post', 'view_count' => 0, 'status' => 'hidden', 'created_at' => '2026-09-03 10:00:00'],
            ['idx' => 1004, 'idol_idx' => 101, 'member_idx' => 2, 'media_type' => 'text', 'content' => 'Inactive author post', 'view_count' => 0, 'status' => 'published', 'created_at' => '2026-09-04 10:00:00'],
            ['idx' => 2001, 'idol_idx' => 102, 'member_idx' => 1, 'media_type' => 'text', 'content' => 'Published Group post', 'view_count' => 30, 'status' => 'published', 'created_at' => '2026-09-05 10:00:00'],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 1001, 'member_idx' => 1],
            ['fan_post_idx' => 1001, 'member_idx' => 2],
        ]);
        DB::table('fan_post_comments')->insert([
            ['idx' => 1, 'fan_post_idx' => 1002, 'status' => 'published'],
            ['idx' => 2, 'fan_post_idx' => 1002, 'status' => 'hidden'],
        ]);
    }

    public function test_missing_fixture_idol_is_not_served_and_database_detail_uses_empty_states(): void
    {
        $this->database();
        $this->get('/idol/1')->assertNotFound();

        $this->get('/idol/101')->assertOk()
            ->assertSee('Test Idol')->assertSee('No votes have been recorded this month.')
            ->assertSee('No voting activity has been recorded in the last 30 days.')
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
    }

    public function test_fan_post_section_sorting_uses_the_requested_metric(): void
    {
        $this->database();
        $this->get('/idol/101?sort=liked')->assertOk()
            ->assertViewHas('fanPostSort', 'liked')
            ->assertViewHas('posts', fn ($posts) => $posts[0]['fan_post_idx'] === 1001);

        $this->get('/idol/101?sort=commented')->assertOk()
            ->assertViewHas('fanPostSort', 'commented')
            ->assertViewHas('posts', fn ($posts) => $posts[0]['fan_post_idx'] === 1002);

        $this->get('/idol/101?sort=invalid')->assertOk()->assertViewHas('fanPostSort', 'latest');
    }

    public function test_unavailable_demo_idols_return_not_found(): void
    {
        $this->database();
        $this->get('/idol/99999')->assertNotFound();
        $this->get('/idol/not-an-id')->assertNotFound();
    }

    public function test_database_idol_and_group_details_load_only_their_public_fan_posts(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->get('/idol/101')->assertOk()
            ->assertViewHas('posts', fn (array $posts): bool =>
                array_column($posts, 'fan_post_idx') === [1002, 1001]
                && $posts[0][5] === 1
            )
            ->assertSee(route('fan-post.show', ['idx' => 1002]), false)
            ->assertSee(route('fan-post.create', ['idol_idx' => 101]), false)
            ->assertDontSee('Hidden Idol post')
            ->assertDontSee('Inactive author post');

        $this->get('/idol/101?sort=liked')->assertOk()
            ->assertViewHas('posts', fn (array $posts): bool => $posts[0]['fan_post_idx'] === 1001);

        $this->get('/idol/102')->assertOk()
            ->assertViewHas('posts', fn (array $posts): bool =>
                count($posts) === 1 && $posts[0]['fan_post_idx'] === 2001
            )
            ->assertSee('Published Group post')
            ->assertDontSee('Be the first to create a Fan Post for this idol!');
    }
}
