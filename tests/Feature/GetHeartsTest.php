<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use App\Support\HeartRewardManager;
use Tests\TestCase;

class GetHeartsTest extends TestCase
{
    private function database(): void
    {
        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT \'active\', deleted_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, min_heart_amount INTEGER NULL, max_heart_amount INTEGER NULL, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))');
        DB::statement('CREATE TABLE daily_check_ins (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER NOT NULL, actor_member_idx INTEGER NOT NULL, language_code TEXT, content TEXT NOT NULL, awarded_hearts INTEGER NOT NULL, check_in_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE (member_idx, check_in_date))');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_path TEXT, target_url TEXT, heart_reward INTEGER DEFAULT 10, reward_enabled INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, display_order INTEGER DEFAULT 0, starts_at TEXT, ends_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE partner_banner_clicks (idx INTEGER PRIMARY KEY AUTOINCREMENT, banner_idx INTEGER NOT NULL, member_idx INTEGER NULL, clicked_at TEXT NOT NULL)');

        foreach ([
            ['daily_attendance', 100, 1], ['watch_ads', 50, 10], ['fan_post_create', 30, 20],
            ['comment_create', 10, 20], ['fan_post_recommend_received', 1, 100],
            ['invite_friend', 1000, 0], ['idol_registration_approved', 3000, 0],
        ] as [$code, $amount, $limit]) {
            DB::table('heart_reward_settings')->insert([
                'action_code' => $code, 'heart_amount' => $amount,
                'min_heart_amount' => $code === 'daily_attendance' ? $amount : null,
                'max_heart_amount' => $code === 'daily_attendance' ? $amount : null,
                'daily_limit' => $limit, 'is_enabled' => 1,
            ]);
        }
    }

    public function test_page_is_public_and_uses_configured_rewards_without_personal_values(): void
    {
        $this->database();

        $response = $this->get('/get-hearts')->assertOk()
            ->assertSee('Get Hearts')->assertSee('Hearts are earned, not bought.')
            ->assertSee('Watch Ads')->assertSee(__('ui.unavailable'))
            ->assertSee('Invite Friends')->assertSee('+ 1,000')->assertSee('No daily limit')
            ->assertSee('Sign in to see your Hearts')->assertDontSee('data-watch-ad-open', false)
            ->assertSee('<link rel="canonical" href="'.route('get-hearts').'">', false);
        foreach (['calendar-check', 'play-circle', 'image-plus', 'message-circle', 'thumbs-up', 'user-plus', 'users'] as $icon) {
            $response->assertSee('href="#icon-'.$icon.'"', false);
        }
        $this->assertSame(0, DB::table('partner_banner_clicks')->count());
    }

    public function test_member_summary_and_progress_use_today_positive_history_records(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 5320,
            'member_level' => 1, 'status' => 'active',
        ]);
        DB::table('heart_histories')->insert([
            ['member_idx' => 7, 'amount' => 100, 'reason' => 'daily_attendance', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => 30, 'reason' => 'fan_post_reward', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => 30, 'reason' => 'fan_post_reward', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => 10, 'reason' => 'comment_reward', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => 1, 'reason' => 'fan_post_recommend_received', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => -50, 'reason' => 'idol_vote', 'created_at' => now()],
            ['member_idx' => 7, 'amount' => 999, 'reason' => 'event_reward', 'created_at' => now()->subDay()],
        ]);
        $this->withSession(['member_idx' => 7])->get('/get-hearts')->assertOk()
            ->assertSee('5,320')->assertSee('+ 171')->assertSee('1 / 1')->assertSee('2 / 20')
            ->assertSee('href="'.route('attendance').'"', false)->assertDontSee('data-watch-ad-open', false)
            ->assertSee('No Hearts are granted from this preview.')
            ->assertViewHas('activities', fn ($activities) =>
                $activities->firstWhere('code', 'comment_create')->progress === 1
                && $activities->firstWhere('code', 'fan_post_recommend_received')->progress === 1
            );
    }

    public function test_daily_check_in_activity_links_to_the_attendance_page_without_submitting(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);

        $this->withSession(['member_idx' => 7])->get('/get-hearts')->assertOk()
            ->assertSee('href="'.route('attendance').'"', false)
            ->assertDontSee('data-daily-check-in-form', false);
        $this->assertSame(0, DB::table('daily_check_ins')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_invite_activity_opens_the_existing_referral_ui_for_members_and_sign_in_for_guests(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'heart_balance' => 500, 'member_level' => 1, 'status' => 'active',
        ]);

        $memberPage = $this->withSession(['member_idx' => 7])->get('/get-hearts')->assertOk()
            ->assertSee('data-invite-friends-open', false)
            ->assertSee('data-signed-in="true"', false)
            ->assertSee('aria-controls="invite-friends-dialog"', false)
            ->assertSee(__('ui.share_invitation'))
            ->assertSee('id="invite-friends-dialog"', false)
            ->assertSee('data-referral-copy', false)
            ->assertSee('data-referral-copy-status', false)
            ->assertSee('data-invite-share="kakao"', false)
            ->assertSee('value="'.url('/?ref=HeartMember7').'" readonly data-referral-url', false);
        $this->assertSame(1, substr_count($memberPage->getContent(), 'id="invite-friends-dialog"'));

        $this->withSession(['member_idx' => null])->get('/get-hearts')->assertOk()
            ->assertSee('class="get-hearts-action" type="button" data-invite-friends-open', false)
            ->assertSee('data-signed-in="false"', false)
            ->assertSee('data-sign-in-open', false)
            ->assertSee('aria-controls="sign-in-modal"', false)
            ->assertSee(__('ui.sign_in'));
    }

    public function test_member_can_claim_configured_daily_check_in_once(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['heart_amount' => 175, 'min_heart_amount' => 174, 'max_heart_amount' => 176, 'daily_limit' => 1]);
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);

        $response = $this->withSession(['member_idx' => 7])
            ->postJson('/get-hearts/daily-check-in', ['content' => 'A lovely day with my favorite group.', 'language_code' => 'en']);

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.progress', 1);
        $this->assertContains((int) $response->json('data.heart_balance'), [674, 675, 676]);
        $history = DB::table('heart_histories')->where('member_idx', 7)->first();
        $checkIn = DB::table('daily_check_ins')->where('member_idx', 7)->first();
        $this->assertContains((int) $history->amount, [174, 175, 176]);
        $this->assertSame('daily_attendance', $history->reason);
        $this->assertSame('daily_attendance:7:'.now('UTC')->toDateString(), $history->operation_key);
        $this->assertSame((int) $history->amount, (int) $checkIn->awarded_hearts);
        $this->assertSame('A lovely day with my favorite group.', $checkIn->content);
        $this->assertSame('en', $checkIn->language_code);
        $this->assertSame(7, (int) $checkIn->actor_member_idx);
        $balanceAfterAward = (int) DB::table('members')->where('idx', 7)->value('heart_balance');

        $this->withSession(['member_idx' => 7])->postJson('/get-hearts/daily-check-in', ['content' => 'Another attempt', 'language_code' => 'en'])
            ->assertStatus(409)->assertJsonPath('already_claimed', true)
            ->assertJsonPath('data.heart_balance', $balanceAfterAward);
        $this->assertSame(1, DB::table('heart_histories')->count());
        $this->assertSame(1, DB::table('daily_check_ins')->count());
    }

    public function test_disabled_or_guest_daily_check_in_cannot_grant_hearts(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);
        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['is_enabled' => 0]);

        $this->withSession(['member_idx' => 7])->postJson('/get-hearts/daily-check-in', ['content' => 'Attempt', 'language_code' => 'en'])
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->withSession(['member_idx' => null])->postJson('/get-hearts/daily-check-in')->assertUnauthorized();
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(500, (int) DB::table('members')->where('idx', 7)->value('heart_balance'));
    }

    public function test_check_in_requires_message_and_stores_zero_or_maximum_range_boundary(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);
        $session = ['member_idx' => 7];

        $this->withSession($session)->postJson('/get-hearts/daily-check-in', ['content' => '   ', 'language_code' => 'en'])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->withSession($session)->postJson('/get-hearts/daily-check-in', ['content' => str_repeat('a', 201), 'language_code' => 'en'])
            ->assertUnprocessable()->assertJsonValidationErrors('content');
        $this->withSession($session)->postJson('/get-hearts/daily-check-in', ['content' => 'Valid message', 'language_code' => 'xx'])
            ->assertUnprocessable()->assertJsonValidationErrors('language_code');

        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['min_heart_amount' => 0, 'max_heart_amount' => 0]);
        $zero = $this->withSession($session)->postJson('/get-hearts/daily-check-in', ['content' => 'Zero boundary', 'language_code' => 'en']);
        $zero->assertOk()->assertJsonPath('data.amount', 0)->assertJsonPath('data.progress', 1);
        $this->assertDatabaseHas('heart_histories', ['amount' => 0, 'operation_key' => 'daily_attendance:7:'.now('UTC')->toDateString()]);

        $this->travelTo(now('UTC')->addDay());
        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['min_heart_amount' => 500, 'max_heart_amount' => 500]);
        $maximum = $this->withSession($session)->postJson('/get-hearts/daily-check-in', ['content' => 'Maximum boundary', 'language_code' => 'en']);
        $maximum->assertOk()->assertJsonPath('data.amount', 500);
        $this->assertSame(2, DB::table('daily_check_ins')->count());
    }

    public function test_check_in_and_heart_award_roll_back_together_when_post_insert_fails(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);
        DB::statement("CREATE TRIGGER fail_daily_check_in BEFORE INSERT ON daily_check_ins BEGIN SELECT RAISE(ABORT, 'forced check-in insert failure'); END");

        $this->withSession(['member_idx' => 7])->postJson('/get-hearts/daily-check-in', ['content' => 'Rollback check', 'language_code' => 'en'])
            ->assertServerError();

        $this->assertSame(500, (int) DB::table('members')->where('idx', 7)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, DB::table('daily_check_ins')->count());
    }

    public function test_admin_can_configure_valid_daily_check_in_range_only(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 8, 'member_code' => 'AdminMember08', 'email' => 'admin@example.test',
            'nickname' => 'admin', 'country_code' => 'KR', 'member_level' => 10, 'status' => 'active',
        ]);
        $payload = [
            'heart_amount' => 100, 'daily_limit' => 1, 'min_heart_amount' => 25,
            'max_heart_amount' => 75, 'is_enabled' => 1,
        ];

        $this->withSession(['member_idx' => 8])->put('/admin/hearts/settings/daily_attendance', $payload)->assertRedirect();
        $this->assertSame(25, (int) DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')->value('min_heart_amount'));
        $this->assertSame(75, (int) DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')->value('max_heart_amount'));

        $this->withSession(['member_idx' => 8])->put('/admin/hearts/settings/daily_attendance', array_replace($payload, [
            'min_heart_amount' => 80, 'max_heart_amount' => 50,
        ]))->assertSessionHasErrors('max_heart_amount');
        $this->assertSame(75, (int) DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')->value('max_heart_amount'));
    }

    public function test_admin_comment_range_defaults_to_fixed_amount_and_validates_before_saving(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 8, 'member_code' => 'AdminMember08', 'email' => 'admin@example.test',
            'nickname' => 'admin', 'country_code' => 'KR', 'member_level' => 10, 'status' => 'active',
        ]);

        $settingsPage = $this->withSession(['member_idx' => 8])->get('/admin/hearts/settings')->assertOk()
            ->assertSee('name="min_heart_amount" min="0" max="2147483647" value="10"', false)
            ->assertSee('name="max_heart_amount" min="0" max="2147483647" value="10"', false)
            ->assertSee('name="heart_amount"', false);
        $this->assertSame(2, substr_count($settingsPage->getContent(), 'name="min_heart_amount"'));
        $this->assertSame(2, substr_count($settingsPage->getContent(), 'name="max_heart_amount"'));

        $payload = ['heart_amount' => 10, 'daily_limit' => 20, 'min_heart_amount' => 4, 'max_heart_amount' => 8, 'is_enabled' => 1];
        $this->withSession(['member_idx' => 8])->put('/admin/hearts/settings/comment_create', $payload)->assertRedirect();
        $setting = DB::table('heart_reward_settings')->where('action_code', 'comment_create')->first();
        $this->assertSame(4, (int) $setting->min_heart_amount);
        $this->assertSame(8, (int) $setting->max_heart_amount);
        $this->assertSame(10, (int) $setting->heart_amount);

        $this->withSession(['member_idx' => 8])->put('/admin/hearts/settings/comment_create', array_replace($payload, [
            'min_heart_amount' => 9, 'max_heart_amount' => 8,
        ]))->assertSessionHasErrors('max_heart_amount');
        $this->withSession(['member_idx' => 8])->put('/admin/hearts/settings/comment_create', array_replace($payload, [
            'min_heart_amount' => -1, 'max_heart_amount' => 8,
        ]))->assertSessionHasErrors('min_heart_amount');
        $this->assertSame(4, (int) DB::table('heart_reward_settings')->where('action_code', 'comment_create')->value('min_heart_amount'));
    }

    public function test_watch_ads_remains_unavailable_without_verified_completion(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 0,
            'member_level' => 1, 'status' => 'active',
        ]);

        $this->assertSame(0, app(HeartRewardManager::class)->award(7, 'watch_ads', 'watch_ads:test-1'));
        $this->assertSame(0, app(HeartRewardManager::class)->award(7, 'watch_ads', 'watch_ads:test-1'));
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 7)->value('heart_balance'));

        $page = $this->withSession(['member_idx' => 7])->get('/get-hearts')->assertOk();
        $activities = $page->viewData('activities')->keyBy('code');
        $this->assertFalse($activities['watch_ads']->is_enabled);
        $this->assertStringNotContainsString('data-watch-ad-open', $page->getContent());
    }

    public function test_automatic_reward_manager_rejects_admin_and_dummy_recipients(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 7, 'member_code' => 'DummyMember007', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 8, 'member_code' => 'AdminMember008', 'email' => 'admin@example.test', 'nickname' => 'admin', 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
        ]);

        foreach ([7, 8] as $memberIdx) {
            $this->assertSame(0, app(HeartRewardManager::class)->award($memberIdx, 'watch_ads', 'watch_ads:test-'.$memberIdx));
        }

        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, (int) DB::table('members')->whereIn('idx', [7, 8])->sum('heart_balance'));
    }

    public function test_admin_checking_in_as_a_dummy_keeps_member_and_actor_attribution_separate(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 7, 'member_code' => 'DummyMember007', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'country_code' => 'KR', 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 8, 'member_code' => 'AdminMember008', 'email' => 'admin@example.test', 'nickname' => 'admin', 'country_code' => 'KR', 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
        ]);

        $this->withSession(['member_idx' => 8, 'acting_dummy_member_idx' => 7])
            ->postJson('/get-hearts/daily-check-in', ['content' => 'Working as the selected test member.', 'language_code' => 'en'])
            ->assertOk();

        $checkIn = DB::table('daily_check_ins')->first();
        $this->assertSame(7, (int) $checkIn->member_idx);
        $this->assertSame(8, (int) $checkIn->actor_member_idx);
        $this->assertSame(0, (int) $checkIn->awarded_hearts);
        $this->assertSame(0, DB::table('heart_histories')->count());

        $this->withSession(['member_idx' => 8, 'acting_dummy_member_idx' => null])
            ->postJson('/get-hearts/daily-check-in', ['content' => 'Admin check-in without a reward.', 'language_code' => 'en'])
            ->assertOk()->assertJsonPath('data.reward_awarded', false)->assertJsonPath('data.amount', null);
        $adminCheckIn = DB::table('daily_check_ins')->where('member_idx', 8)->first();
        $this->assertSame(0, (int) $adminCheckIn->awarded_hearts);
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_get_hearts_aggregates_use_the_utc_calendar_day(): void
    {
        $this->database();
        \Illuminate\Support\Carbon::setTestNow('2026-10-17 00:30:00 UTC');

        try {
            DB::table('members')->insert([
                'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
                'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
                'member_level' => 1, 'is_dummy' => 0, 'status' => 'active',
            ]);
            DB::table('heart_histories')->insert([
                ['member_idx' => 7, 'amount' => 80, 'reason' => 'fan_post_reward', 'operation_key' => 'old-day', 'created_at' => '2026-10-16 23:59:59'],
                ['member_idx' => 7, 'amount' => 25, 'reason' => 'fan_post_reward', 'operation_key' => 'utc-today', 'created_at' => '2026-10-17 00:00:01'],
            ]);
            DB::table('daily_check_ins')->insert([
                ['member_idx' => 7, 'actor_member_idx' => 7, 'content' => 'Previous UTC day', 'awarded_hearts' => 100, 'check_in_date' => '2026-10-16'],
                ['member_idx' => 7, 'actor_member_idx' => 7, 'content' => 'Current UTC day', 'awarded_hearts' => 0, 'check_in_date' => '2026-10-17'],
            ]);

            $response = $this->withSession(['member_idx' => 7])->get('/get-hearts')->assertOk();
            $response->assertViewHas('todayEarned', 25)
                ->assertViewHas('activities', fn ($activities) =>
                    $activities->firstWhere('code', 'fan_post_create')->progress === 1
                    && $activities->firstWhere('code', 'daily_attendance')->progress === 1
                );
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }
}
