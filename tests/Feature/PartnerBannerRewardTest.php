<?php

namespace Tests\Feature;

use App\Support\HeartRewardManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerBannerRewardTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))');
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_path TEXT, target_url TEXT, heart_reward INTEGER DEFAULT 10, reward_enabled INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, display_order INTEGER DEFAULT 0, starts_at TEXT, ends_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::table('members')->insert([
            'idx' => 1, 'member_code' => 'BannerMember1', 'email' => 'member@example.test', 'nickname' => 'banner_member',
            'heart_balance' => 200, 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active',
            'updated_at' => now(),
        ]);
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'BannerAdmin02', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'BannerDummy003', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'BannerStaff004', 'member_level' => 2, 'is_dummy' => 0, 'status' => 'active'],
        ]);
        Storage::fake('public');
    }

    private function banner(array $overrides = []): int
    {
        $idx = (int) (DB::table('partner_banners')->insertGetId(array_replace([
            'title' => 'Partner Banner', 'image_path' => 'storage/partner-banners/banner.png',
            'target_url' => 'https://partner.example.test', 'heart_reward' => 25, 'reward_enabled' => 1,
            'is_active' => 1, 'display_order' => 0, 'starts_at' => null, 'ends_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides)));
        Storage::disk('public')->put(substr(DB::table('partner_banners')->where('idx', $idx)->value('image_path'), strlen('storage/')), 'image');

        return $idx;
    }

    public function test_claim_uses_configured_amount_and_updates_balance_and_history_atomically(): void
    {
        $this->database();
        $bannerIdx = $this->banner(['heart_reward' => 37]);

        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx), ['heart_amount' => 9999])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.amount', 37)
            ->assertJsonPath('data.heart_balance', 237)->assertJsonPath('data.today_earned', 37)
            ->assertJsonPath('data.completed_today', true);

        $this->assertSame(237, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => 37, 'balance_after' => 237,
            'reason' => HeartRewardManager::PARTNER_BANNER_REASON,
            'operation_key' => 'partner_banner:'.$bannerIdx.':1:'.now()->format('Ymd'),
        ]);
    }

    public function test_same_banner_cannot_be_claimed_twice_but_other_banners_can(): void
    {
        $this->database();
        $firstBannerIdx = $this->banner(['heart_reward' => 15]);
        $secondBannerIdx = $this->banner(['image_path' => 'storage/partner-banners/second.png', 'heart_reward' => 30]);

        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $firstBannerIdx))->assertOk();
        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $firstBannerIdx))
            ->assertStatus(409)->assertJsonPath('already_claimed', true);
        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $secondBannerIdx))->assertOk();

        $this->assertSame(245, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(2, DB::table('heart_histories')->where('member_idx', 1)->count());
    }

    public function test_inactive_expired_disabled_and_zero_reward_banners_cannot_be_claimed(): void
    {
        $this->database();
        $bannerIds = [
            $this->banner(['is_active' => 0]),
            $this->banner(['image_path' => 'storage/partner-banners/expired.png', 'ends_at' => now()->subSecond()]),
            $this->banner(['image_path' => 'storage/partner-banners/disabled.png', 'reward_enabled' => 0]),
            $this->banner(['image_path' => 'storage/partner-banners/zero.png', 'heart_reward' => 0]),
            $this->banner(['image_path' => 'storage/partner-banners/future.png', 'starts_at' => now()->addDay()]),
        ];

        foreach ($bannerIds as $bannerId) {
            $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $bannerId))
                ->assertStatus(409)->assertJsonPath('success', false);
        }

        $this->assertSame(200, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_guests_and_invalid_members_cannot_claim(): void
    {
        $this->database();
        $bannerIdx = $this->banner();

        $this->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertUnauthorized();
        DB::table('members')->where('idx', 1)->update(['member_level' => 0]);
        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertUnauthorized();
        $this->withSession(['member_idx' => 2])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertForbidden();
        $this->withSession(['member_idx' => 3])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertForbidden();
        $this->withSession(['member_idx' => 4])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertForbidden();

        foreach ([['status' => 'suspended'], ['deleted_at' => now()]] as $changes) {
            DB::table('members')->where('idx', 1)->update(array_replace(['member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null], $changes));
            $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertUnauthorized();
        }

        $this->assertSame(200, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_reward_badges_are_public_but_completed_state_and_claims_are_member_specific(): void
    {
        $this->database();
        $eligibleIdx = $this->banner(['heart_reward' => 18]);
        $completedIdx = $this->banner(['image_path' => 'storage/partner-banners/completed.png', 'heart_reward' => 42]);
        $disabledIdx = $this->banner(['image_path' => 'storage/partner-banners/hidden.png', 'heart_reward' => 99, 'reward_enabled' => 0]);
        $operationKey = 'partner_banner:'.$completedIdx.':1:'.now()->format('Ymd');
        DB::table('heart_histories')->insert([
            'member_idx' => 1, 'amount' => 42, 'balance_after' => 242,
            'reason' => HeartRewardManager::PARTNER_BANNER_REASON, 'operation_key' => $operationKey, 'created_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->get('/get-hearts')->assertOk()
            ->assertSee('width="16" height="16" viewBox="0 0 24 24"', false)->assertSee('+18')
            ->assertSee('✓ 완료')->assertDontSee('+99')->assertDontSee('❤️')
            ->assertSee('<p class="get-hearts-sponsor-note"><svg width="14" height="14"', false)
            ->assertSee('Heart rewards are available to eligible signed-in members only. Each banner can earn Hearts once per day.')
            ->assertSee('id="partner-banner-reward-dialog"', false)
            ->assertSee(route('get-hearts.partner-banners.claim', $eligibleIdx), false);
        $this->withSession(['member_idx' => null])->get('/get-hearts')->assertOk()
            ->assertSee('+18')->assertSee('+42')->assertDontSee('✓ 완료')->assertDontSee('❤️')
            ->assertDontSee('+99')->assertDontSee('id="partner-banner-reward-dialog"', false);
        $this->withSession(['member_idx' => 2])->get('/get-hearts')->assertOk()
            ->assertSee('+18')->assertSee('+42')->assertDontSee('✓ 완료')->assertDontSee('❤️')
            ->assertDontSee('+99')->assertDontSee('id="partner-banner-reward-dialog"', false)
            ->assertDontSee('data-partner-banner-reward', false);
        $this->withSession(['member_idx' => 3])->get('/get-hearts')->assertOk()
            ->assertSee('+18')->assertSee('+42')->assertDontSee('✓ 완료')->assertDontSee('❤️')
            ->assertDontSee('+99')->assertDontSee('id="partner-banner-reward-dialog"', false)
            ->assertDontSee('data-partner-banner-reward', false);
        $this->withSession(['member_idx' => 4])->get('/get-hearts')->assertOk()
            ->assertSee('+18')->assertSee('+42')->assertDontSee('✓ 완료')->assertDontSee('❤️')
            ->assertDontSee('+99')->assertDontSee('data-partner-banner-reward', false);
    }

    public function test_admin_acting_as_dummy_cannot_claim_partner_banner_hearts(): void
    {
        $this->database();
        $bannerIdx = $this->banner();

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));
        $this->postJson(route('get-hearts.partner-banners.claim', $bannerIdx))->assertForbidden();

        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }
}
