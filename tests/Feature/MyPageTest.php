<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MyPageTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', config('database.default'));
        // Test-only SQL, never applied to the application database.
        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT UNIQUE, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, referred_by_member_idx INTEGER, heart_balance INTEGER DEFAULT 0, password_hash TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT \'active\', deleted_at TEXT, password_changed_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER DEFAULT 1, member_idx INTEGER, media_type TEXT DEFAULT \'text\', image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT DEFAULT \'published\', deleted_at TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)');
        DB::statement('CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT DEFAULT \'published\', deleted_at TEXT)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY, member_idx INTEGER, actor_member_idx INTEGER, amount INTEGER, reason TEXT, operation_key TEXT, vote_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, idol_idx INTEGER)');
        DB::statement('CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, status TEXT DEFAULT \'active\')');
        DB::table('idols')->insert(['idx' => 1, 'type' => 'individual', 'gender' => 'male']);
        DB::table('members')->insert([
            'idx' => 1, 'member_code' => 'memberCodeA1', 'email' => 'member@example.test', 'nickname' => 'member_one', 'country_code' => 'KR',
            'password_hash' => Hash::make('Old-password1!'),
        ]);
        DB::table('members')->insert(['idx' => 2, 'member_code' => 'memberCodeB2', 'password_hash' => Hash::make('Other-password1!')]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['current_password' => 'Old-password1!', 'password' => 'New-password2!', 'password_confirmation' => 'New-password2!'], $changes);
    }

    public function test_guest_is_redirected_and_password_access_is_denied(): void
    {
        $this->get('/my-page')->assertRedirect('/')->assertSessionHas('open_sign_in');
        $this->postJson('/my-page/password', $this->payload())->assertUnauthorized()->assertJson(['success' => false]);
    }

    public function test_password_changes_only_session_member_and_records_timestamp(): void
    {
        $this->database();
        $otherHash = DB::table('members')->where('idx', 2)->value('password_hash');
        $this->withSession(['member_idx' => 1])->postJson('/my-page/password', $this->payload(['member_idx' => 2]))
            ->assertOk()->assertJson(['success' => true]);
        $member = DB::table('members')->where('idx', 1)->first();
        $this->assertTrue(Hash::check('New-password2!', $member->password_hash));
        $this->assertFalse(Hash::check('Old-password1!', $member->password_hash));
        $this->assertNotNull($member->password_changed_at);
        $this->assertSame($otherHash, DB::table('members')->where('idx', 2)->value('password_hash'));
        $this->postJson('/my-page/password', $this->payload())->assertUnprocessable();
    }

    public function test_wrong_current_password_validation_and_google_only_do_not_change_hash(): void
    {
        $this->database();
        $before = DB::table('members')->where('idx', 1)->value('password_hash');
        foreach ([['current_password' => 'wrong'], ['password_confirmation' => 'different'], ['password' => 'short', 'password_confirmation' => 'short'], ['password' => str_repeat('A1!', 25), 'password_confirmation' => str_repeat('A1!', 25)]] as $changes) {
            $this->withSession(['member_idx' => 1])->postJson('/my-page/password', $this->payload($changes))->assertUnprocessable()->assertJson(['success' => false]);
        }
        $this->assertSame($before, DB::table('members')->where('idx', 1)->value('password_hash'));
        DB::table('members')->where('idx', 1)->update(['password_hash' => null]);
        $this->postJson('/my-page/password', $this->payload())->assertConflict();
        $this->assertNull(DB::table('members')->where('idx', 1)->value('password_hash'));
    }

    public function test_invalid_members_are_rejected_and_session_cleared(): void
    {
        $this->database();
        foreach ([['member_level' => 0], ['status' => 'suspended'], ['deleted_at' => '2026-09-01 00:00:00']] as $changes) {
            DB::table('members')->where('idx', 1)->update(array_replace(['member_level' => 1, 'status' => 'active', 'deleted_at' => null], $changes));
            $this->withSession(['member_idx' => 1])->postJson('/my-page/password', $this->payload())->assertUnauthorized()->assertSessionMissing('member_idx');
        }
        $this->withSession(['member_idx' => 999])->postJson('/my-page/password', $this->payload())->assertUnauthorized()->assertSessionMissing('member_idx');
    }

    public function test_posts_are_owned_published_latest_ten_with_counts(): void
    {
        $this->database();
        for ($idx = 1; $idx <= 14; $idx++) {
            DB::table('fan_posts')->insert(['idx' => $idx, 'member_idx' => $idx === 14 ? 2 : 1,
                'content' => 'Post '.$idx, 'image_path' => 'uploads/fan-posts/'.$idx.'.png',
                'status' => $idx === 13 ? 'hidden' : 'published', 'deleted_at' => $idx === 12 ? '2026-09-01' : null,
                'created_at' => '2026-09-01 00:00:00']);
        }
        DB::table('fan_post_likes')->insert(['fan_post_idx' => 11, 'member_idx' => 2]);
        DB::table('fan_post_comments')->insert([['idx' => 1, 'fan_post_idx' => 11, 'status' => 'published'], ['idx' => 2, 'fan_post_idx' => 11, 'status' => 'hidden']]);
        $response = $this->withSession(['member_idx' => 1])->get('/my-page?tab=posts')->assertOk()
            ->assertSee('Like Ranking')
            ->assertSee('Fan Posts')
            ->assertSee('Comments')
            ->assertViewHas('memberStats', fn ($stats) => $stats === [
                'like_ranking' => 'Top 5%',
                'fan_post_count' => 11,
                'comment_count' => 1,
            ])->assertViewHas('posts', function ($posts) {
            $this->assertCount(10, $posts);
            $this->assertSame('Post 11', $posts[0][2]);
            $this->assertSame('Post 2', $posts[9][2]);
            $this->assertSame(1, $posts[0][4]);
            $this->assertSame(1, $posts[0][5]);
            $this->assertSame(11, $posts[0]['fan_post_idx']);
            $this->assertSame(asset('images/no-image.png'), $posts[0]['image_url']);
            return true;
        })->assertSee(route('fan-post.show', ['idx' => 11]), false);

        $this->assertSame(
            3,
            substr_count($response->getContent(), 'href="'.route('fan-post.show', ['idx' => 11]).'"')
        );
    }

    public function test_heart_history_is_member_scoped_newest_first_and_keeps_tab_on_next_page(): void
    {
        $this->database();
        DB::table('idols')->insert(['idx' => 3, 'type' => 'group']);
        DB::table('idol_votes')->insert(['idx' => 4, 'idol_idx' => 3]);
        $recentDate = now()->subDay()->toDateTimeString();
        for ($idx = 1; $idx <= 21; $idx++) {
            DB::table('heart_histories')->insert([
                'idx' => $idx, 'member_idx' => 1, 'amount' => $idx === 21 ? -100 : 10,
                'reason' => $idx === 21 ? 'vote' : ($idx === 20 ? 'partner_banner_reward' : 'comment_reward'),
                'vote_idx' => $idx === 21 ? 4 : null,
                'created_at' => $recentDate,
            ]);
        }
        DB::table('heart_histories')->insert([
            'idx' => 22, 'member_idx' => 2, 'amount' => 9000,
            'reason' => 'invite_friend_reward', 'vote_idx' => null,
            'created_at' => now()->toDateTimeString(),
        ]);
        DB::table('heart_histories')->insert([
            'idx' => 23, 'member_idx' => 1, 'amount' => 8000,
            'reason' => 'signup_reward', 'vote_idx' => null,
            'created_at' => now()->subMonthsNoOverflow(3)->subDay()->toDateTimeString(),
        ]);

        $this->withSession(['member_idx' => 1])->get('/my-page')->assertOk()
            ->assertViewHas('activeTab', 'hearts')
            ->assertViewHas('posts', [])
            ->assertViewHas('heartHistories', function ($histories) {
                $this->assertSame(21, $histories->items()[0]->idx);
                $this->assertSame(21, $histories->total());
                $this->assertStringContainsString('tab=hearts', $histories->url(2));
                $this->assertStringEndsWith('#my-page-lower-tabs', $histories->url(2));
                return true;
            })
            ->assertSee('Vote for Group')
            ->assertSee('Partner Banner')
            ->assertSee('-100')
            ->assertSee('Heart history from the last 3 months.')
            ->assertDontSee('+8,000')
            ->assertDontSee('+9,000');

        $this->withSession(['member_idx' => 1])->get('/my-page?tab=hearts&page=2')->assertOk()
            ->assertSee('Write Comment')
            ->assertDontSee('Vote for Group');
    }

    public function test_heart_history_shows_empty_state_when_only_older_records_exist(): void
    {
        $this->database();
        DB::table('heart_histories')->insert([
            'idx' => 1, 'member_idx' => 1, 'amount' => 100,
            'reason' => 'daily_attendance', 'vote_idx' => null,
            'created_at' => now()->subMonthsNoOverflow(3)->subDay()->toDateTimeString(),
        ]);

        $this->withSession(['member_idx' => 1])->get('/my-page')->assertOk()
            ->assertViewHas('heartHistories', fn ($histories) => $histories->total() === 0)
            ->assertSee('No Heart history yet.');
    }

    public function test_invites_show_actual_referrals_and_only_their_paid_rewards(): void
    {
        $this->database();
        for ($idx = 3; $idx <= 25; $idx++) {
            DB::table('members')->insert([
                'idx' => $idx, 'member_code' => 'invited'.str_pad((string) $idx, 5, '0', STR_PAD_LEFT),
                'nickname' => 'friend_'.$idx, 'referred_by_member_idx' => $idx === 21 ? 2 : 1,
                'created_at' => '2026-10-05 10:00:00',
            ]);
        }
        DB::table('heart_histories')->insert([
            ['idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 25, 'amount' => 1250,
                'reason' => 'invite_friend_reward', 'operation_key' => 'referral_reward:25'],
            ['idx' => 2, 'member_idx' => 1, 'actor_member_idx' => 24, 'amount' => 1250,
                'reason' => 'invite_friend_reward', 'operation_key' => 'referral_reward:999'],
            ['idx' => 3, 'member_idx' => 2, 'actor_member_idx' => 20, 'amount' => 3000,
                'reason' => 'invite_friend_reward', 'operation_key' => 'referral_reward:20'],
        ]);

        $this->withSession(['member_idx' => 1])->get('/my-page?tab=invites')->assertOk()
            ->assertViewHas('activeTab', 'invites')
            ->assertViewHas('posts', [])
            ->assertViewHas('heartHistories', null)
            ->assertViewHas('invitedMembers', function ($invites) {
                $this->assertSame(22, $invites->total());
                $this->assertSame(25, $invites->items()[0]->idx);
                $this->assertSame(1250, $invites->items()[0]->reward_amount);
                $this->assertNull($invites->items()[1]->reward_amount);
                $this->assertStringContainsString('tab=invites', $invites->url(2));
                $this->assertStringEndsWith('#my-page-lower-tabs', $invites->url(2));
                return true;
            })
            ->assertSee('+1,250')
            ->assertSee('—')
            ->assertDontSee('friend_21');

        $this->withSession(['member_idx' => 1])->get('/my-page?tab=invites&page=2')->assertOk()
            ->assertViewHas('activeTab', 'invites')
            ->assertSee('friend_3')
            ->assertDontSee('friend_25');
    }

    public function test_guest_member_pages_require_sign_in(): void
    {
        $this->get('/my-page')->assertRedirect('/');
        $this->get('/my-page/fan-posts')->assertRedirect('/');
    }

    public function test_profile_country_must_use_a_supported_iso_code(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->putJson('/my-page/profile', [
            'nickname' => 'member_one',
            'country_code' => 'ZZ',
        ])->assertUnprocessable()->assertJsonValidationErrors('country_code');
    }

    public function test_member_fan_post_view_renders_the_shared_list_and_summary(): void
    {
        $post = ['portrait-rose', 'member_one', 'A shared card', 12, 3, 4, 'square', 'member_code' => 'memberCodeA1'];
        $fanPosts = (new LengthAwarePaginator([$post, $post], 42, 20, 1, [
            'path' => url('/my-page/fan-posts'),
            'query' => ['sort' => 'liked'],
        ]))->withQueryString();
        $summary = [
            'member_code' => 'memberCodeA1',
            'profile_image_url' => asset('images/placeholders/portrait-rose.svg'),
            'nickname' => 'member_one', 'country' => 'South Korea', 'country_code' => 'KR',
            'bio' => 'Fan bio', 'like_ranking' => 'Top 5%', 'fan_post_count' => 42, 'comment_count' => 8,
        ];

        $html = view('members.fan-posts', [
            'memberSummary' => $summary, 'fanPosts' => $fanPosts, 'sort' => 'liked', 'member_code' => 'memberCodeA1',
        ])->render();

        $this->assertSame(2, substr_count($html, 'class="fan-post-card"'));
        $this->assertStringContainsString('fan-posts--member', $html);
        $this->assertStringContainsString('member-post-summary', $html);
        $this->assertStringContainsString('sort=liked', $html);
        $this->assertSame(6, substr_count($html, 'href="'.route('member.fan-posts', ['member_code' => 'memberCodeA1']).'"'));
    }

    public function test_member_fan_posts_are_paginated_twenty_and_keep_sort_query(): void
    {
        $this->database();
        for ($idx = 1; $idx <= 22; $idx++) {
            DB::table('fan_posts')->insert([
                'idx' => $idx,
                'member_idx' => 1,
                'content' => 'Member post '.$idx,
                'image_path' => null,
                'status' => 'published',
                'created_at' => sprintf('2026-09-%02d 00:00:00', $idx),
            ]);
        }
        DB::table('fan_posts')->insert([
            'idx' => 99, 'member_idx' => 2, 'content' => 'Another member post',
            'status' => 'published', 'created_at' => '2026-09-30 00:00:00',
        ]);

        $this->withSession(['member_idx' => 1])->get('/my-page/fan-posts?sort=latest')
            ->assertRedirect('/member/memberCodeA1/fan-posts?sort=latest');

        $response = $this->get('/member/memberCodeA1/fan-posts?sort=latest')
            ->assertOk()
            ->assertSee('Posts written by member_one')
            ->assertSee('Most Liked')
            ->assertSee('Most Commented')
            ->assertDontSee('Another member post');

        $response->assertViewHas('fanPosts', function ($posts) {
            $this->assertSame(20, $posts->count());
            $this->assertSame(22, $posts->total());
            $this->assertSame('Member post 22', $posts->items()[0][2]);
            $this->assertStringContainsString('sort=latest', $posts->url(2));
            return true;
        });
        $this->assertSame(20, substr_count($response->getContent(), 'class="fan-post-card"'));
    }

    public function test_canonical_member_page_is_public_empty_safe_and_rejects_inaccessible_members(): void
    {
        $this->database();
        DB::table('members')->where('idx', 2)->update([
            'email' => 'private@example.test',
            'nickname' => 'public_member',
            'country_code' => 'US',
        ]);

        $this->get('/member/memberCodeB2/fan-posts')->assertOk()
            ->assertSee('public_member')
            ->assertSee('United States')
            ->assertSee('No fan posts yet.')
            ->assertDontSee('+ Create Fan Post')
            ->assertDontSee('private@example.test');
        $this->withSession(['member_idx' => 1])->get('/member/memberCodeA1/fan-posts')
            ->assertOk()->assertSee('+ Create Fan Post');
        $this->withSession(['member_idx' => 1])->get('/member/memberCodeB2/fan-posts')
            ->assertOk()->assertDontSee('+ Create Fan Post');

        DB::table('members')->where('idx', 2)->update(['is_dummy' => 1]);
        $this->get('/member/memberCodeB2/fan-posts')->assertOk()->assertSee('public_member');

        foreach ([['member_level' => 0], ['status' => 'suspended'], ['deleted_at' => '2026-09-01 00:00:00']] as $changes) {
            DB::table('members')->where('idx', 2)->update(array_replace([
                'member_level' => 1,
                'status' => 'active',
                'deleted_at' => null,
            ], $changes));
            $this->get('/member/memberCodeB2/fan-posts')->assertNotFound();
        }
        $this->get('/member/unknownCode1/fan-posts')->assertNotFound()->assertSee('Member Not Found');
        $this->get('/member/1/fan-posts')->assertNotFound()->assertSee('Member Not Found');
        $this->get('/member/demoMemberA1/fan-posts')->assertNotFound();
    }

    public function test_member_fan_post_sorting_uses_like_and_published_comment_counts(): void
    {
        $this->database();
        foreach ([1, 2, 3] as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'member_idx' => 1, 'content' => 'Sorted post '.$idx,
                'status' => 'published', 'created_at' => '2026-09-0'.$idx.' 00:00:00',
            ]);
        }
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 2, 'member_idx' => 1],
            ['fan_post_idx' => 2, 'member_idx' => 2],
        ]);
        DB::table('fan_post_comments')->insert([
            ['idx' => 1, 'fan_post_idx' => 3, 'status' => 'published'],
            ['idx' => 2, 'fan_post_idx' => 3, 'status' => 'published'],
            ['idx' => 3, 'fan_post_idx' => 2, 'status' => 'hidden'],
        ]);

        $this->get('/member/memberCodeA1/fan-posts?sort=liked')
            ->assertOk()->assertViewHas('fanPosts', fn ($posts) => $posts->items()[0][2] === 'Sorted post 2');
        $this->get('/member/memberCodeA1/fan-posts?sort=commented')
            ->assertOk()->assertViewHas('fanPosts', fn ($posts) => $posts->items()[0][2] === 'Sorted post 3')
            ->assertViewHas('memberSummary', fn ($summary) => $summary['fan_post_count'] === 3
                && $summary['comment_count'] === 2 && $summary['like_ranking'] === '—');
    }

    public function test_password_attempts_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/my-page/password', $this->payload())->assertUnauthorized();
        }
        $this->postJson('/my-page/password', $this->payload())->assertStatus(429);
    }
}
