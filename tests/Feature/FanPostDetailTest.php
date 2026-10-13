<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FanPostDetailTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT UNIQUE, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, password_hash TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, english_name TEXT, agency TEXT, debut_date TEXT, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, gender TEXT DEFAULT 'male', type TEXT DEFAULT 'individual', status TEXT DEFAULT 'active', view_count INTEGER DEFAULT 0)");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, request_key TEXT, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, title TEXT, media_type TEXT DEFAULT 'text', image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT DEFAULT 'published', deleted_at TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, tag TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, created_at TEXT, PRIMARY KEY (fan_post_idx, member_idx))');
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, request_key TEXT, content TEXT, status TEXT DEFAULT 'published', deleted_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE (actor_member_idx, request_key))");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
        DB::statement("CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE fan_post_reports (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reason TEXT, details TEXT, status TEXT DEFAULT 'pending', created_at TEXT, updated_at TEXT, UNIQUE (fan_post_idx, member_idx))");
        DB::table('members')->insert([
            'idx' => 1, 'member_code' => 'memberCodeA1', 'email' => 'member@example.test', 'nickname' => 'member_one', 'country_code' => 'KR',
            'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('idols')->insert(['idx' => 1, 'name' => 'Demo Idol', 'status' => 'active']);
        DB::table('heart_reward_settings')->insert([
            ['action_code' => 'fan_post_create', 'heart_amount' => 30, 'daily_limit' => 20, 'is_enabled' => 1],
            ['action_code' => 'comment_create', 'heart_amount' => 10, 'daily_limit' => 20, 'is_enabled' => 1],
            ['action_code' => 'fan_post_recommend_received', 'heart_amount' => 1, 'daily_limit' => 100, 'is_enabled' => 1],
        ]);
    }

    public function test_authenticated_member_can_create_a_youtube_fan_post(): void
    {
        $this->database();

        $response = $this->withSession(['member_idx' => 1])->post('/fan-post', [
            'idol_idx' => 1,
            'title' => 'A new fan post',
            'content' => 'A thoughtful post for this idol.',
            'media_type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'tags' => 'concert,comeback',
        ]);

        $post = DB::table('fan_posts')->first();
        $response->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $response->assertSessionHas('global_notification.message', 'Fan Post created! +30 Hearts');
        $this->assertSame(1, $post->member_idx);
        $this->assertSame(1, $post->actor_member_idx);
        $this->assertSame('dQw4w9WgXcQ', $post->youtube_video_id);
        $this->assertSame('youtube', $post->media_type);
        $this->assertNull($post->image_path);
        $this->assertSame(['concert', 'comeback'], DB::table('fan_post_tags')->orderBy('idx')->pluck('tag')->all());
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => 30, 'reason' => 'fan_post_reward',
            'operation_key' => 'fan_post_reward:'.$post->idx,
        ]);
        $this->get(route('fan-post.show', ['idx' => $post->idx]))->assertOk()
            ->assertSee('A new fan post')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$post->idx, [
            'idol_idx' => 1, 'title' => 'Text only now', 'content' => 'No media remains.',
            'media_type' => 'text', 'tags' => '',
        ])->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $post = DB::table('fan_posts')->where('idx', $post->idx)->first();
        $this->assertSame('text', $post->media_type);
        $this->assertNull($post->image_path);
        $this->assertNull($post->youtube_video_id);

        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$post->idx, [
            'idol_idx' => 1, 'title' => 'Shorts now', 'content' => 'A Shorts video.',
            'media_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $post = DB::table('fan_posts')->where('idx', $post->idx)->first();
        $this->assertSame('youtube', $post->media_type);
        $this->assertNull($post->image_path);
        $this->assertSame('dQw4w9WgXcQ', $post->youtube_video_id);
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_reward')->count());
    }

    public function test_admin_can_create_and_comment_as_a_dummy_with_rewards_owned_by_dummy(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode0001', 'email' => 'admin@example.test', 'nickname' => 'admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummyCode0001', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
        ]);

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));

        $response = $this->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Delegated post', 'content' => 'Posted as a dummy member.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect();
        $post = DB::table('fan_posts')->where('title', 'Delegated post')->first();
        $this->assertSame(3, (int) $post->member_idx);
        $this->assertSame(2, (int) $post->actor_member_idx);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'reason' => 'fan_post_reward', 'amount' => 30,
        ]);

        $this->postJson('/fan-post/'.$post->idx.'/comments', [
            'content' => 'Comment as dummy', 'request_key' => 'delegated-comment-0001',
        ])->assertCreated()->assertJsonPath('data.comment.nickname', 'dummy');
        $this->assertDatabaseHas('fan_post_comments', [
            'fan_post_idx' => $post->idx, 'member_idx' => 3, 'actor_member_idx' => 2,
            'content' => 'Comment as dummy',
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'reason' => 'comment_reward', 'amount' => 10,
        ]);
        $this->assertSame(40, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(2, (int) session('member_idx'));

        $this->withSession(['member_idx' => 3])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Direct dummy post', 'content' => 'Posted while signed in directly.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect();
        $directPost = DB::table('fan_posts')->where('title', 'Direct dummy post')->first();
        $this->assertSame(3, (int) $directPost->member_idx);
        $this->assertSame(3, (int) $directPost->actor_member_idx);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 3, 'operation_key' => 'fan_post_reward:'.$directPost->idx,
        ]);
    }

    public function test_text_posts_and_media_posts_past_the_daily_limit_are_not_rewarded(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'fan_post_create')
            ->update(['heart_amount' => 45, 'daily_limit' => 1]);

        $this->withSession(['member_idx' => 1])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Text post', 'content' => 'No eligible media.',
            'media_type' => 'text', 'tags' => '',
        ])->assertRedirect()->assertSessionMissing('global_notification');

        foreach (['First video', 'Second video'] as $title) {
            $this->withSession(['member_idx' => 1])->post('/fan-post', [
                'idol_idx' => 1, 'title' => $title, 'content' => 'Eligible video post.',
                'media_type' => 'youtube',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'tags' => '',
            ])->assertRedirect();
        }

        $this->assertSame(3, DB::table('fan_posts')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_reward')->count());
        $this->assertSame(45, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_shared_cards_link_content_and_known_member_authors_but_not_statistics(): void
    {
        $this->database();
        $this->get('/fan-post/1')->assertNotFound();

        foreach (range(1, 14) as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
                'title' => 'Post '.$idx, 'content' => 'A sample post body.', 'media_type' => 'text',
                'status' => 'published', 'created_at' => now()->subMinutes($idx),
            ]);
        }

        foreach (['/', '/idol/1'] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($document);
            $cards = $xpath->query('//article[contains(@class,"fan-post-card")]');
            $this->assertSame(14, $cards->length);
            foreach ($cards as $card) {
                $expected = $xpath->query('./a[img]', $card)->item(0)->getAttribute('href');
                $this->assertMatchesRegularExpression('~/fan-post/\d+$~', $expected);
                $this->assertSame($expected, $xpath->query('.//p[@class="post-preview"]/a', $card)->item(0)->getAttribute('href'));
                $authorLinks = $xpath->query('./div[@class="post-body"]/div[@class="post-author"]/a', $card);
                $this->assertContains($authorLinks->length, [0, 2]);
                if ($authorLinks->length === 2) {
                    $this->assertSame($authorLinks->item(0)->getAttribute('href'), $authorLinks->item(1)->getAttribute('href'));
                    $this->assertMatchesRegularExpression('~/member/[A-Za-z0-9]{12}/fan-posts$~', $authorLinks->item(0)->getAttribute('href'));
                }
                $this->assertSame(0, $xpath->query('./div[@class="post-statistics"]//a | ./a//div[@class="post-statistics"]', $card)->length);
            }
        }

    }

    public function test_fixture_fan_post_ids_are_not_public_without_database_records(): void
    {
        $this->database();

        foreach ([1, 8, 14] as $idx) {
            $this->get(route('fan-post.show', ['idx' => $idx]))->assertNotFound();
        }
    }

    public function test_database_detail_lists_real_public_posts_for_the_same_idol_and_highlights_current(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'author@example.test',
            'nickname' => 'real_author', 'profile_image_path' => null,
        ]);
        DB::table('idols')->insert(['idx' => 2, 'name' => 'Other Idol', 'status' => 'active']);
        DB::table('fan_posts')->insert([
            ['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Current post', 'content' => 'Current body', 'media_type' => 'text', 'view_count' => 10, 'status' => 'published', 'created_at' => '2026-09-01 10:00:00'],
            ['idx' => 51, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Related post', 'content' => 'Related body', 'media_type' => 'text', 'view_count' => 20, 'status' => 'published', 'created_at' => '2026-09-02 10:00:00'],
            ['idx' => 52, 'idol_idx' => 2, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Other Idol post', 'content' => 'Other body', 'media_type' => 'text', 'view_count' => 0, 'status' => 'published', 'created_at' => '2026-09-03 10:00:00'],
            ['idx' => 53, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Hidden related post', 'content' => 'Hidden body', 'media_type' => 'text', 'view_count' => 0, 'status' => 'hidden', 'created_at' => '2026-09-04 10:00:00'],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 50, 'member_idx' => 1],
            ['fan_post_idx' => 50, 'member_idx' => 2],
        ]);
        DB::table('fan_post_comments')->insert([
            'fan_post_idx' => 51, 'member_idx' => 1, 'actor_member_idx' => 1,
            'request_key' => 'detail-related-comment-0001', 'content' => 'Related comment',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withSession(['member_idx' => 1])->get('/fan-post/50?sort=liked')->assertOk()
            ->assertViewHas('posts', fn (array $posts): bool =>
                array_column($posts, 'fan_post_idx') === [50, 51]
                && $posts[0][4] === 2
                && $posts[1][5] === 1
                && $posts[1]['member_code'] === 'memberCodeB2'
            )
            ->assertSee(route('fan-post.create', ['idol_idx' => 1]), false)
            ->assertDontSee('Hidden related post');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $this->assertSame(2, $xpath->query('//article[contains(@class,"fan-post-card")]')->length);
        $this->assertSame(1, $xpath->query('//article[contains(@class,"fan-post-card--current")]')->length);
        $this->assertSame(
            route('member.fan-posts', ['member_code' => 'memberCodeB2']),
            $xpath->query('//article[contains(@class,"fan-post-card")][2]//div[@class="post-author"]/a')->item(0)->getAttribute('href')
        );
    }

    public function test_existing_lists_have_no_current_card_and_unknown_posts_are_not_found(): void
    {
        $this->database();

        foreach (['/', '/idol/1'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('fan-post-card--current');
        }
        $this->get('/fan-post/99999')->assertNotFound();
        $this->get('/fan-post/not-an-idx')->assertNotFound();
        foreach (range(1, 14) as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
                'title' => 'Database post '.$idx, 'content' => 'Real database detail.', 'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (range(1, 14) as $idx) {
            $this->get('/fan-post/'.$idx)->assertOk();
        }
    }

    public function test_mobile_detail_keeps_comments_before_the_remaining_sidebar_panels(): void
    {
        $this->database();
        DB::table('fan_posts')->insert([
            'idx' => 1, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Real mobile detail', 'content' => 'Actual database post.', 'media_type' => 'text',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.fan-detail-main, .fan-detail-sidebar { display: contents; }', $css);
        $this->assertStringContainsString('.fan-detail-comments { order: 3; }', $css);
        $this->assertStringContainsString('.fan-detail-sidebar > :nth-child(1) { order: 4; }', $css);
        $this->assertStringContainsString('.fan-detail-sidebar > :nth-child(2) { order: 5; }', $css);

        $response = $this->get('/fan-post/1')->assertOk();
        $this->assertSame(0, substr_count($response->getContent(), 'id="author-info-title"'));
    }

    public function test_database_comments_are_paginated_ten_per_page_with_dedicated_query_parameter(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'commenter@example.test', 'nickname' => 'commenter',
            'country_code' => 'US', 'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('fan_posts')->insert(['idx' => 5, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        foreach (range(1, 25) as $commentIdx) {
            DB::table('fan_post_comments')->insert([
                'idx' => $commentIdx,
                'fan_post_idx' => 5,
                'member_idx' => 2,
                'content' => 'Database comment '.$commentIdx,
                'status' => 'published',
                'created_at' => sprintf('2026-09-%02d 12:00:00', $commentIdx),
            ]);
        }

        $response = $this->get('/fan-post/5?filter=popular&comments_page=2')->assertOk();
        $response->assertViewHas('comments', fn ($comments) =>
            $comments->currentPage() === 2 && $comments->perPage() === 10
            && $comments->total() === 25 && $comments->count() === 10
        );
        $response->assertSee('Database comment 15')->assertDontSee('Database comment 16');
        $response->assertSee('comments_page=1', false)->assertSee('filter=popular', false);
        $this->assertSame(20, substr_count($response->getContent(), 'href="'.route('member.fan-posts', ['member_code' => 'memberCodeB2']).'"'));

        $this->get('/fan-post/5?comments_page=999')->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->currentPage() === 3 && $comments->count() === 5);
        $this->get('/fan-post/5?comments_page=invalid')->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->currentPage() === 1);
    }

    public function test_member_can_create_reward_and_soft_delete_own_comment(): void
    {
        $this->database();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => "  First line\nSecond line  ",
            'request_key' => 'comment-request-0001',
        ])->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Comment posted! +10 Hearts')
            ->assertJsonPath('data.rewarded_hearts', 10);

        $commentIdx = $response->json('data.comment.idx');
        $this->assertSame("First line\nSecond line", DB::table('fan_post_comments')->where('idx', $commentIdx)->value('content'));
        $this->assertSame(10, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $history = DB::table('heart_histories')->first();
        $this->assertSame('comment_reward', $history->reason);
        $this->assertSame('comment_reward:'.$commentIdx, $history->operation_key);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => "First line\nSecond line",
            'request_key' => 'comment-request-0001',
        ])->assertOk()->assertJsonPath('data.rewarded_hearts', 0);
        $this->assertSame(1, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->count());

        $this->withSession(['member_idx' => 1])->deleteJson('/fan-post/50/comments/'.$commentIdx)
            ->assertOk()->assertJsonPath('data.comment_count', 0);
        $deleted = DB::table('fan_post_comments')->where('idx', $commentIdx)->first();
        $this->assertSame('hidden', $deleted->status);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame(1, DB::table('heart_histories')->count());
    }

    public function test_comment_creation_continues_when_reward_is_disabled_or_daily_limit_is_reached(): void
    {
        $this->database();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['heart_amount' => 17, 'daily_limit' => 1]);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'First rewarded comment', 'request_key' => 'comment-limit-test-01',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 17);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Comment after limit', 'request_key' => 'comment-limit-test-02',
        ])->assertCreated()->assertJsonPath('message', 'Comment posted.')
            ->assertJsonPath('data.rewarded_hearts', 0);

        DB::table('heart_reward_settings')->where('action_code', 'comment_create')->update(['is_enabled' => 0]);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Comment while disabled', 'request_key' => 'comment-limit-test-03',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);

        $this->assertSame(3, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
        $this->assertSame(17, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_comment_endpoints_enforce_authorship_and_published_post(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'other@example.test', 'nickname' => 'other', 'is_dummy' => 0, 'status' => 'active']);
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Hidden', 'content' => 'Body', 'status' => 'hidden', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/fan-post/50/comments', ['content' => 'No', 'request_key' => 'comment-request-0002'])->assertUnauthorized();
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', ['content' => 'No', 'request_key' => 'comment-request-0002'])->assertNotFound();

        DB::table('fan_posts')->where('idx', 50)->update(['status' => 'published']);
        $comment = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', ['content' => 'Mine', 'request_key' => 'comment-request-0003'])->json('data.comment');
        $this->withSession(['member_idx' => 2])->deleteJson('/fan-post/50/comments/'.$comment['idx'])->assertForbidden();
    }

    public function test_recommendations_are_irreversible_and_reward_the_post_author_once(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'recommender@example.test',
            'nickname' => 'recommender', 'country_code' => 'US', 'status' => 'active',
        ]);
        DB::table('fan_posts')->insert(['idx' => 5, 'idol_idx' => 1, 'member_idx' => 1, 'title' => 'Real post', 'content' => 'Database content', 'status' => 'published']);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/5/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        $this->get('/fan-post/5')->assertOk()->assertSee('Recommend')->assertDontSee('Recommended');
        $this->postJson('/fan-post/5/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        DB::table('heart_reward_settings')->where('action_code', 'fan_post_recommend_received')
            ->update(['heart_amount' => 7, 'daily_limit' => 1]);
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);

        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->assertSame(7, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1,
            'reason' => 'fan_post_recommend_received',
            'amount' => 7,
            'operation_key' => 'fan_post_recommend_received:50:2',
            'actor_member_idx' => 2,
        ]);

        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());

        DB::table('fan_posts')->insert(['idx' => 51, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/51/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 51)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());

        DB::table('fan_posts')->insert(['idx' => 52, 'idol_idx' => 1, 'member_idx' => 2, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/52/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 2)
            ->where('reason', 'fan_post_recommend_received')->count());

        DB::table('heart_reward_settings')->where('action_code', 'fan_post_recommend_received')
            ->update(['is_enabled' => 0]);
        DB::table('fan_posts')->insert(['idx' => 53, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/53/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 53)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());
    }

    public function test_admin_recommendation_as_dummy_is_owned_and_audited_separately(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode0001', 'email' => 'admin@example.test', 'nickname' => 'admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummyCode0001', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'authorCode001', 'email' => 'author@example.test', 'nickname' => 'author', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
        DB::table('fan_posts')->insert([
            'idx' => 60, 'idol_idx' => 1, 'member_idx' => 4, 'title' => 'Delegated recommendation target',
            'content' => 'Public post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));
        $this->postJson('/fan-post/60/recommendation')->assertOk()
            ->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);

        $this->assertDatabaseHas('fan_post_likes', [
            'fan_post_idx' => 60, 'member_idx' => 3, 'actor_member_idx' => 2,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 4, 'actor_member_idx' => 2, 'reason' => 'fan_post_recommend_received',
            'operation_key' => 'fan_post_recommend_received:60:3',
        ]);
        $this->assertSame(1, (int) DB::table('members')->where('idx', 4)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
    }

    public function test_member_can_report_another_published_post_once_with_valid_details(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'reporter@example.test',
            'nickname' => 'reporter', 'country_code' => 'US', 'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('fan_posts')->insert([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Reportable post', 'content' => 'Post body', 'status' => 'published',
        ]);

        $this->postJson('/fan-post/50/report', ['reason' => 'spam'])->assertUnauthorized();
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/report', [
            'reason' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->postJson('/fan-post/50/report', [
            'reason' => 'other', 'details' => 'Additional context for review.',
        ])->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('fan_post_reports', [
            'fan_post_idx' => 50, 'member_idx' => 2, 'reason' => 'other',
            'details' => 'Additional context for review.', 'status' => 'pending',
        ]);
        $this->postJson('/fan-post/50/report', ['reason' => 'spam'])
            ->assertStatus(409);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/report', ['reason' => 'spam'])
            ->assertUnprocessable()->assertJsonPath('message', 'You cannot report your own Fan Post.');
    }
}
