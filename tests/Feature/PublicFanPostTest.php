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
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, content TEXT, image_path TEXT, status TEXT DEFAULT 'active')");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, tag TEXT)');
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
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 1, 'member_idx' => 1, 'created_at' => now()->format('Y-m-d H:i:s')],
            ['fan_post_idx' => 1, 'member_idx' => 2, 'created_at' => now()->subMonthNoOverflow()->format('Y-m-d H:i:s')],
        ]);
        DB::table('fan_post_comments')->insert(['idx' => 1, 'fan_post_idx' => 2, 'status' => 'published']);
    }

    public function test_public_page_uses_real_posts_sorting_member_links_and_pagination(): void
    {
        $this->database();

        $this->get('/fan-posts')->assertOk()->assertSee('Fan Posts')
            ->assertSeeInOrder(['Latest', 'This Month Likes', 'All-Time Likes', 'Most Commented'])
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

    public function test_hidden_fan_posts_are_excluded_from_public_list_and_search(): void
    {
        $this->database();
        DB::table('fan_posts')->where('idx', 1)->update([
            'status' => 'hidden', 'content' => 'hidden-only-search-token',
        ]);

        $this->get('/fan-posts')->assertOk()
            ->assertDontSee('href="'.route('fan-post.show', ['idx' => 1]).'"', false);
        $fanPosts = app(\App\Support\PublicFanPostList::class);
        $this->assertSame(0, $fanPosts->searchCount('hidden-only-search-token'));
        $this->assertSame([], $fanPosts->search('hidden-only-search-token', 'latest', 10));
    }

    public function test_fan_post_sorts_use_utc_monthly_likes_lifetime_likes_and_comments(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-16 12:00:00', 'UTC'));
        $this->database();

        DB::table('fan_posts')->whereIn('idx', [1, 2])->update(['created_at' => '2026-10-15 12:00:00']);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 2, 'member_idx' => 3, 'created_at' => '2026-10-02 00:00:00'],
            ['fan_post_idx' => 2, 'member_idx' => 4, 'created_at' => '2026-10-03 00:00:00'],
        ]);
        DB::table('fan_post_comments')->insert([
            ['idx' => 2, 'fan_post_idx' => 21, 'status' => 'published'],
            ['idx' => 3, 'fan_post_idx' => 21, 'status' => 'published'],
        ]);

        $posts = app(\App\Support\PublicFanPostList::class);

        $this->assertSame(2, $posts->take('monthly_liked', 21)[0]['fan_post_idx']);
        // Equal lifetime counts use newest creation time, then descending post idx as the stable tie-breaker.
        $this->assertSame(2, $posts->take('liked', 21)[0]['fan_post_idx']);
        $this->assertSame(21, $posts->take('commented', 21)[0]['fan_post_idx']);
        $this->assertSame(2, $posts->take('monthly_liked', 21)[0]['monthly_like_count']);
        $this->assertSame(2, $posts->take('liked', 21)[0][4]);
    }

    public function test_monthly_like_counts_include_utc_month_start_and_exclude_adjacent_months(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-31 23:59:59', 'UTC'));
        $this->database();
        DB::table('fan_posts')->insert([
            ['idx' => 30, 'idol_idx' => 1, 'member_idx' => 1, 'media_type' => 'image', 'image_path' => 'month-top.jpg', 'content' => 'Month top', 'status' => 'published'],
            ['idx' => 31, 'idol_idx' => 1, 'member_idx' => 1, 'media_type' => 'image', 'image_path' => 'month-second.jpg', 'content' => 'Month second', 'status' => 'published'],
            ['idx' => 32, 'idol_idx' => 1, 'member_idx' => 1, 'media_type' => 'image', 'image_path' => 'adjacent.jpg', 'content' => 'Adjacent months', 'status' => 'published'],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 30, 'member_idx' => 2, 'created_at' => '2026-10-01 00:00:00'],
            ['fan_post_idx' => 30, 'member_idx' => 3, 'created_at' => '2026-10-31 23:59:59'],
            ['fan_post_idx' => 31, 'member_idx' => 2, 'created_at' => '2026-10-01 00:00:00'],
            ['fan_post_idx' => 32, 'member_idx' => 2, 'created_at' => '2026-09-30 23:59:59'],
            ['fan_post_idx' => 32, 'member_idx' => 3, 'created_at' => '2026-11-01 00:00:00'],
        ]);

        $posts = collect(app(\App\Support\PublicFanPostList::class)->take('monthly_liked', 50));
        $this->assertSame(30, $posts[0]['fan_post_idx']);
        $this->assertSame(2, $posts->firstWhere('fan_post_idx', 30)['monthly_like_count']);
        $this->assertSame(1, $posts->firstWhere('fan_post_idx', 31)['monthly_like_count']);
        $this->assertSame(0, $posts->firstWhere('fan_post_idx', 32)['monthly_like_count']);
        $this->assertSame(2, $posts->firstWhere('fan_post_idx', 32)[4]);
    }

    public function test_korean_fan_post_sort_labels_match_the_requested_order(): void
    {
        $this->database();

        $this->withSession(['locale' => 'ko'])->get('/fan-posts')->assertOk()
            ->assertSeeInOrder(['최신순', '이번 달 추천', '누적 추천', '댓글순']);
    }

    public function test_header_has_fan_posts_before_register_idol(): void
    {
        $this->database();
        $response = $this->get('/')->assertOk();
        $response->assertSeeInOrder(['Fan Posts', 'Register Idol'])
            ->assertSee('Public post 21')
            ->assertDontSee('golden_army')->assertDontSee('No fan posts yet.');
    }

    public function test_dummy_member_fan_posts_remain_public(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'DummyCode0001', 'nickname' => 'dummy_author',
            'country_code' => 'KR', 'profile_image_path' => null, 'is_dummy' => 1,
        ]);
        DB::table('fan_posts')->insert([
            'idx' => 22, 'idol_idx' => 1, 'member_idx' => 2, 'media_type' => 'text',
            'content' => 'A real public post from a dummy member', 'view_count' => 0,
            'status' => 'published', 'created_at' => now(),
        ]);

        $this->get('/fan-posts')->assertOk()->assertSee('A real public post from a dummy member');
        $this->get('/member/DummyCode0001/fan-posts')->assertOk()
            ->assertSee('dummy_author')->assertSee('A real public post from a dummy member');
    }

    public function test_shared_cards_show_member_flag_by_nickname_and_language_code_only(): void
    {
        $this->database();
        DB::table('members')->where('idx', 1)->update(['country_code' => 'KR']);
        DB::table('fan_posts')->where('idx', 1)->update(['language_code' => 'ja']);

        $this->get('/fan-posts')->assertOk()->assertSee('title="JA" aria-label="JA"', false)
            ->assertSee('src="'.asset('images/flags/kr.svg').'"', false)
            ->assertDontSee('title="South Korea · JA"', false)
            ->assertSee('1 (2)')
            ->assertSee('class="fan-post-card-language-badge"', false)
            ->assertDontSee('class="fan-post-author-badge"', false);

        DB::table('members')->where('idx', 1)->update(['country_code' => 'ZZ']);
        $this->get('/fan-posts')->assertOk()->assertSee('aria-label="JA"', false)
            ->assertDontSee('src="'.asset('images/flags/zz.svg').'"', false);

        DB::table('fan_posts')->where('idx', 1)->update(['language_code' => 'zz']);
        $this->get('/fan-posts')->assertOk()->assertDontSee('title="South Korea · JA"', false);
    }

    public function test_public_member_summary_shows_the_members_flag_before_the_nickname(): void
    {
        $this->database();
        DB::table('members')->where('idx', 1)->update(['country_code' => 'KR']);

        $this->get('/member/MemberCode01/fan-posts')->assertOk()
            ->assertSee('src="'.asset('images/flags/kr.svg').'"', false)
            ->assertSee('id="member-post-summary-name"', false)
            ->assertSee('public_fan');
    }

    public function test_shared_card_shows_compact_counts_and_exact_accessible_values(): void
    {
        $post = [
            'portrait-rose', 'fan', 'preview', 1200, 3500000, 12, 'square',
            'media_type' => 'text', 'monthly_like_count' => 2000000,
        ];

        $this->blade('<x-fan-post-card :post="$post" />', compact('post'))
            ->assertSee('1.2K', false)
            ->assertSee('2M (3.5M)', false)
            ->assertSee('Monthly likes: 2,000,000; lifetime likes: 3,500,000', false)
            ->assertSee('1,200 views', false);
    }
}
