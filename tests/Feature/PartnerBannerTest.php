<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PartnerBannerTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, image_path TEXT NOT NULL, target_url TEXT NOT NULL, heart_reward INTEGER NOT NULL DEFAULT 10, reward_enabled INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, display_order INTEGER NOT NULL DEFAULT 0, starts_at TEXT NULL, ends_at TEXT NULL, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE partner_banner_clicks (idx INTEGER PRIMARY KEY AUTOINCREMENT, banner_idx INTEGER NOT NULL, member_idx INTEGER NULL, clicked_at TEXT NOT NULL)');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))');
        DB::table('members')->insert([
            'idx' => 1, 'member_code' => 'adminCode001', 'email' => 'admin@example.test',
            'nickname' => 'Admin', 'country_code' => 'KR', 'member_level' => 10,
            'status' => 'active', 'updated_at' => now(),
        ]);
    }

    private function adminPost(string $uri, array $data = [])
    {
        return $this->withSession(['member_idx' => 1])->post($uri, $data);
    }

    private function bannerValues(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Partner banner',
            'target_url' => 'https://partner.example.test/offer',
            'heart_reward' => 30,
            'reward_enabled' => 1,
            'is_active' => 1,
            'display_order' => 4,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function test_admin_can_create_edit_and_delete_owned_banner_images(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 1])->get('/admin/partner-banners/create')->assertOk();
        $this->adminPost('/admin/partner-banners', $this->bannerValues([
            'banner_image' => UploadedFile::fake()->image('partner.jpg', 600, 160),
        ]))->assertRedirect('/admin/partner-banners');

        $banner = DB::table('partner_banners')->first();
        $this->assertNotNull($banner);
        $this->assertStringStartsWith('storage/partner-banners/', $banner->image_path);
        Storage::disk('public')->assertExists(substr($banner->image_path, strlen('storage/')));
        $this->assertSame(30, (int) $banner->heart_reward);
        $this->assertSame(1, (int) $banner->reward_enabled);
        $this->assertSame(1, DB::table('admin_activity_logs')->where('action', 'partner_banner_created')->count());

        $oldImagePath = $banner->image_path;
        $this->withSession(['member_idx' => 1])->put('/admin/partner-banners/'.$banner->idx, $this->bannerValues([
            'title' => 'Updated banner',
            'banner_image' => UploadedFile::fake()->image('replacement.webp', 640, 180),
            'display_order' => 2,
        ]))->assertRedirect('/admin/partner-banners');

        $updated = DB::table('partner_banners')->where('idx', $banner->idx)->first();
        $this->assertSame('Updated banner', $updated->title);
        $this->assertSame(2, (int) $updated->display_order);
        $this->assertNotSame($oldImagePath, $updated->image_path);
        Storage::disk('public')->assertMissing(substr($oldImagePath, strlen('storage/')));
        Storage::disk('public')->assertExists(substr($updated->image_path, strlen('storage/')));

        $this->withSession(['member_idx' => 1])->delete('/admin/partner-banners/'.$banner->idx)
            ->assertRedirect('/admin/partner-banners');
        $this->assertSame(0, DB::table('partner_banners')->count());
        Storage::disk('public')->assertMissing(substr($updated->image_path, strlen('storage/')));
        $this->assertSame(3, DB::table('admin_activity_logs')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_image_is_required_and_upload_type_and_size_are_validated(): void
    {
        $this->database();
        Storage::fake('public');

        $this->adminPost('/admin/partner-banners', $this->bannerValues())->assertSessionHasErrors('banner_image');
        $this->adminPost('/admin/partner-banners', $this->bannerValues([
            'banner_image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]))->assertSessionHasErrors('banner_image');
        $this->adminPost('/admin/partner-banners', $this->bannerValues([
            'banner_image' => UploadedFile::fake()->image('too-large.png')->size(5121),
        ]))->assertSessionHasErrors('banner_image');

        $this->assertSame(0, DB::table('partner_banners')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('partner-banners'));
    }

    public function test_url_and_date_range_are_validated(): void
    {
        $this->database();
        Storage::fake('public');
        $upload = UploadedFile::fake()->image('partner.png');
        $this->adminPost('/admin/partner-banners', $this->bannerValues([
            'banner_image' => $upload,
            'target_url' => 'javascript:alert(1)',
        ]))->assertSessionHasErrors('target_url');
        $this->adminPost('/admin/partner-banners', $this->bannerValues([
            'banner_image' => $upload,
            'starts_at' => '2026-10-10T12:00',
            'ends_at' => '2026-10-09T12:00',
        ]))->assertSessionHasErrors('ends_at');

        $this->assertSame(0, DB::table('partner_banners')->count());
        $this->assertSame([], Storage::disk('public')->allFiles('partner-banners'));
    }

    public function test_public_page_filters_orders_and_links_only_available_banners(): void
    {
        $this->database();
        Storage::fake('public');
        foreach (['first.png', 'second.png', 'not-started.png', 'expired.png'] as $path) {
            Storage::disk('public')->put('partner-banners/'.$path, 'image');
        }

        $base = [
            'target_url' => 'https://partner.example.test/offer', 'heart_reward' => 10,
            'reward_enabled' => 0, 'is_active' => 1, 'starts_at' => null, 'ends_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('partner_banners')->insert([
            array_merge($base, ['title' => 'Later order', 'image_path' => 'storage/partner-banners/second.png', 'display_order' => 2]),
            array_merge($base, ['title' => 'First order', 'image_path' => 'storage/partner-banners/first.png', 'display_order' => 1]),
            array_merge($base, ['title' => 'Inactive', 'image_path' => 'storage/partner-banners/second.png', 'display_order' => 0, 'is_active' => 0]),
            array_merge($base, ['title' => 'Future', 'image_path' => 'storage/partner-banners/not-started.png', 'display_order' => 0, 'starts_at' => now()->addDay()]),
            array_merge($base, ['title' => 'Expired', 'image_path' => 'storage/partner-banners/expired.png', 'display_order' => 0, 'ends_at' => now()->subDay()]),
            array_merge($base, ['title' => 'Missing image', 'image_path' => 'storage/partner-banners/missing.png', 'display_order' => 0]),
            array_merge($base, ['title' => 'Outside upload folder', 'image_path' => 'images/no-image.png', 'display_order' => 0]),
        ]);

        $this->get('/get-hearts')->assertOk()
            ->assertSee('alt="First order"', false)
            ->assertSee('alt="Later order"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee('alt="Inactive"', false)
            ->assertDontSee('alt="Future"', false)
            ->assertDontSee('alt="Expired"', false)
            ->assertDontSee('alt="Missing image"', false)
            ->assertDontSee('alt="Outside upload folder"', false);

        $rendered = $this->get('/get-hearts')->viewData('partnerBanners');
        $this->assertSame(['First order', 'Later order'], $rendered->pluck('title')->all());
    }

    public function test_delete_preserves_shared_and_non_partner_assets(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('partner-banners/shared.png', 'shared image');
        Storage::disk('public')->put('images/shared.png', 'shared asset');
        $base = [
            'title' => 'Shared image', 'target_url' => 'https://partner.example.test', 'heart_reward' => 10,
            'reward_enabled' => 0, 'is_active' => 1, 'display_order' => 0, 'starts_at' => null,
            'ends_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('partner_banners')->insert([
            $base + ['image_path' => 'storage/partner-banners/shared.png'],
            $base + ['image_path' => 'storage/partner-banners/shared.png'],
            $base + ['image_path' => 'images/shared.png'],
        ]);
        $rows = DB::table('partner_banners')->orderBy('idx')->get();

        $this->withSession(['member_idx' => 1])->delete('/admin/partner-banners/'.$rows[0]->idx)->assertRedirect();
        Storage::disk('public')->assertExists('partner-banners/shared.png');
        $this->withSession(['member_idx' => 1])->delete('/admin/partner-banners/'.$rows[1]->idx)->assertRedirect();
        Storage::disk('public')->assertMissing('partner-banners/shared.png');
        $this->withSession(['member_idx' => 1])->delete('/admin/partner-banners/'.$rows[2]->idx)->assertRedirect();
        Storage::disk('public')->assertExists('images/shared.png');
    }

    public function test_admin_list_shows_compact_columns_and_only_valid_total_clicks(): void
    {
        $this->database();
        $bannerIdx = DB::table('partner_banners')->insertGetId([
            'title' => 'Stats banner', 'image_path' => 'storage/partner-banners/stats.png',
            'target_url' => 'https://partner.example.test', 'heart_reward' => 20, 'reward_enabled' => 1,
            'is_active' => 1, 'display_order' => 0, 'starts_at' => null, 'ends_at' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'normalUser02', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'adminUser003', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'dummyUser004', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 5, 'member_code' => 'suspendUser05', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'suspended'],
        ]);
        DB::table('partner_banner_clicks')->insert([
            ['banner_idx' => $bannerIdx, 'member_idx' => null, 'clicked_at' => now()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 2, 'clicked_at' => now()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 2, 'clicked_at' => now()->subDay()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 1, 'clicked_at' => now()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 3, 'clicked_at' => now()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 4, 'clicked_at' => now()],
            ['banner_idx' => $bannerIdx, 'member_idx' => 5, 'clicked_at' => now()],
        ]);

        $response = $this->withSession(['member_idx' => 1])->get('/admin/partner-banners')->assertOk()
            ->assertSee('Stats banner')->assertSee('20')
            ->assertDontSee('today_valid_clicks')->assertDontSee('total_successful_rewards');
        $this->assertSame(3, $response->viewData('banners')->first()->total_valid_clicks);
    }
}
