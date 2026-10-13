<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerBannerClickTest extends TestCase
{
    private function database(): int
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_path TEXT, target_url TEXT, heart_reward INTEGER DEFAULT 10, reward_enabled INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, display_order INTEGER DEFAULT 0, starts_at TEXT, ends_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE partner_banner_clicks (idx INTEGER PRIMARY KEY AUTOINCREMENT, banner_idx INTEGER NOT NULL, member_idx INTEGER NULL, clicked_at TEXT NOT NULL)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, reason TEXT, operation_key TEXT)');
        foreach ([
            ['idx' => 1, 'member_code' => 'normalMember1', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'adminMember02', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummyMember003', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'staffMember004', 'member_level' => 2, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 5, 'member_code' => 'suspended005', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'suspended'],
            ['idx' => 6, 'member_code' => 'deletedMember6', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => now()],
        ] as $member) {
            DB::table('members')->insert(array_replace([
                'email' => null, 'nickname' => null, 'country_code' => null, 'profile_image_path' => null,
                'heart_balance' => 200, 'deleted_at' => null, 'updated_at' => now(),
            ], $member));
        }

        return (int) DB::table('partner_banners')->insertGetId([
            'title' => 'Tracked banner', 'image_path' => 'storage/partner-banners/tracked.png',
            'target_url' => 'https://partner.example.test/path', 'heart_reward' => 10, 'reward_enabled' => 1,
            'is_active' => 1, 'display_order' => 0, 'starts_at' => null, 'ends_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_normal_member_and_guest_clicks_redirect_to_database_url_and_repeated_clicks_are_throttled(): void
    {
        $bannerIdx = $this->database();
        $clickUrl = route('partner-banners.click', $bannerIdx);

        $this->withSession(['member_idx' => 1])->get($clickUrl.'?url=https://attacker.example.test')
            ->assertRedirect('https://partner.example.test/path');
        $this->withSession(['member_idx' => 1])->get($clickUrl)->assertRedirect('https://partner.example.test/path');
        $this->withSession(['member_idx' => null])->get($clickUrl)->assertRedirect('https://partner.example.test/path');

        $clicks = DB::table('partner_banner_clicks')->orderBy('idx')->get();
        $this->assertCount(2, $clicks);
        $this->assertSame(1, (int) $clicks[0]->member_idx);
        $this->assertNull($clicks[1]->member_idx);
        $this->assertNotNull($clicks[0]->clicked_at);
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_admin_dummy_other_level_suspended_and_deleted_members_are_not_counted(): void
    {
        $bannerIdx = $this->database();
        $clickUrl = route('partner-banners.click', $bannerIdx);

        foreach ([2, 3, 4, 5, 6] as $memberIdx) {
            $this->withSession(['member_idx' => $memberIdx])->get($clickUrl)
                ->assertRedirect('https://partner.example.test/path');
        }

        $this->assertSame(0, DB::table('partner_banner_clicks')->count());
    }

    public function test_click_endpoint_rejects_invalid_database_destination(): void
    {
        $bannerIdx = $this->database();
        DB::table('partner_banners')->where('idx', $bannerIdx)->update(['target_url' => 'javascript:alert(1)']);

        $this->withSession(['member_idx' => 1])->get(route('partner-banners.click', $bannerIdx))->assertNotFound();
        $this->assertSame(0, DB::table('partner_banner_clicks')->count());
    }
}
