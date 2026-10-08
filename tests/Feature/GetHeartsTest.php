<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GetHeartsTest extends TestCase
{
    private function database(): void
    {
        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, status TEXT DEFAULT \'active\', deleted_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))');

        foreach ([
            ['daily_attendance', 100, 1], ['watch_ads', 50, 10], ['fan_post_create', 30, 20],
            ['comment_create', 10, 20], ['fan_post_recommend_received', 1, 100],
            ['invite_friend', 1000, 0], ['idol_registration_approved', 3000, 0],
        ] as [$code, $amount, $limit]) {
            DB::table('heart_reward_settings')->insert([
                'action_code' => $code, 'heart_amount' => $amount,
                'daily_limit' => $limit, 'is_enabled' => 1,
            ]);
        }
    }

    public function test_page_is_public_and_uses_configured_rewards_without_personal_values(): void
    {
        $this->database();

        $this->get('/get-hearts')->assertOk()
            ->assertSee('Get Hearts')->assertSee('Hearts are earned, not bought.')
            ->assertSee('Watch Ads')->assertSee('+ 50')->assertSee('10 times per day')
            ->assertSee('Invite Friends')->assertSee('+ 1,000')->assertSee('No daily limit')
            ->assertSee('Sign in to see your Hearts')->assertDontSee('data-watch-ad-open', false)
            ->assertSee('<link rel="canonical" href="'.route('get-hearts').'">', false);
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
            ->assertDontSee('Completed')->assertSee('data-watch-ad-open', false)
            ->assertSee('No Hearts are granted from this preview.')
            ->assertViewHas('activities', fn ($activities) =>
                $activities->firstWhere('code', 'comment_create')->progress === 1
                && $activities->firstWhere('code', 'fan_post_recommend_received')->progress === 1
            );
    }

    public function test_member_can_claim_configured_daily_check_in_once(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['heart_amount' => 175, 'daily_limit' => 1]);
        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'HeartMember7', 'email' => 'heart@example.test',
            'nickname' => 'heart_fan', 'country_code' => 'KR', 'heart_balance' => 500,
            'member_level' => 1, 'status' => 'active',
        ]);

        $response = $this->withSession(['member_idx' => 7])
            ->postJson('/get-hearts/daily-check-in');

        $response->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', 175)
            ->assertJsonPath('data.heart_balance', 675)
            ->assertJsonPath('data.progress', 1);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 7,
            'amount' => 175,
            'reason' => 'daily_attendance',
            'operation_key' => 'daily_attendance:7:'.now()->toDateString(),
        ]);

        $this->withSession(['member_idx' => 7])->postJson('/get-hearts/daily-check-in')
            ->assertStatus(409)->assertJsonPath('already_claimed', true)
            ->assertJsonPath('data.heart_balance', 675);
        $this->assertSame(1, DB::table('heart_histories')->count());
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

        $this->withSession(['member_idx' => 7])->postJson('/get-hearts/daily-check-in')
            ->assertStatus(409)->assertJsonPath('success', false);
        $this->withSession(['member_idx' => null])->postJson('/get-hearts/daily-check-in')->assertUnauthorized();
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(500, (int) DB::table('members')->where('idx', 7)->value('heart_balance'));
    }
}
