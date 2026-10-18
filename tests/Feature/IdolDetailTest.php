<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdolDetailTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, genre TEXT, leader TEXT, sns TEXT, sns_links TEXT, image_path TEXT, content TEXT, status TEXT, view_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, country_code))");
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))");
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::statement("CREATE TABLE idol_profile_image_reports (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reported_image_path TEXT, image_path_hash TEXT, evidence_path TEXT, reason TEXT, details TEXT, status TEXT, moderated_by_member_idx INTEGER, moderated_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, actor_member_idx, image_path_hash))");

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
            ->assertSee('voting-trend-chart', false)
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
    }

    public function test_detail_page_offers_a_report_action_for_its_current_managed_profile_image(): void
    {
        $this->database();
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('idol-profiles/current.jpg', 'image');
        DB::table('idols')->where('idx', 101)->update(['image_path' => 'storage/idol-profiles/current.jpg']);

        $this->withSession(['member_idx' => 1])->get('/idol/101')->assertOk()
            ->assertSee('data-idol-image-report-open', false)
            ->assertSee(route('idol.profile-image.report', 101), false)
            ->assertSee(__('ui.report_profile_image'));
    }

    public function test_vote_trend_aggregates_the_latest_thirty_days_and_isolates_the_current_idol(): void
    {
        $this->database();
        Carbon::setTestNow('2026-10-17 12:00:00');

        try {
            DB::table('idol_votes')->insert([
                ['idx' => 1, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 120, 'created_at' => '2026-09-18 00:00:00'],
                ['idx' => 2, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 80, 'created_at' => '2026-09-18 23:59:59'],
                ['idx' => 3, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 35, 'created_at' => '2026-10-01 08:30:00'],
                ['idx' => 7, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 1200, 'created_at' => '2026-10-01 10:30:00'],
                ['idx' => 4, 'member_idx' => 1, 'idol_idx' => 102, 'heart_amount' => 999, 'created_at' => '2026-10-01 08:30:00'],
                ['idx' => 5, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 500, 'created_at' => '2026-09-17 23:59:59'],
                ['idx' => 6, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 600, 'created_at' => '2026-10-18 00:00:00'],
                ['idx' => 8, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 50, 'created_at' => '2026-10-17 23:59:59'],
            ]);

            $response = $this->withSession(['locale' => 'en'])->get('/idol/101')->assertOk();
            $trend = $response->viewData('trend');

            $this->assertCount(30, $trend);
            $this->assertSame('2026-09-18', $trend[0]['date']);
            $this->assertSame('2026-10-17', $trend[29]['date']);
            $this->assertSame(200, $trend[0]['votes']);
            $this->assertSame(1235, $trend[13]['votes']); // October 1, across the month boundary.
            $this->assertSame(50, $trend[29]['votes']); // Today's votes remain in the window.
            $this->assertSame(0, $trend[1]['votes']);
            $this->assertSame(1485, array_sum(array_column($trend, 'votes')));
            $response->assertSee('09-18')->assertSee('10-17')->assertSee('09-25');
            $response->assertSee('2026-10-01: 1,235 Hearts');

            $this->withSession(['locale' => 'ko'])->get('/idol/101')->assertOk()
                ->assertSee('09-18')->assertSee('10-17')->assertSee('2026-10-01: 1,235 '.__('ui.hearts'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_vote_trend_keeps_all_zero_days_in_the_chart(): void
    {
        $this->database();
        Carbon::setTestNow('2026-10-17 12:00:00');

        try {
            $response = $this->get('/idol/101')->assertOk();
            $trend = $response->viewData('trend');

            $this->assertCount(30, $trend);
            $this->assertSame(0, array_sum(array_column($trend, 'votes')));
            $this->assertSame(30, substr_count($response->getContent(), 'class="trend-point"'));
            $response->assertSee('09-18')->assertSee('10-17');
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_detail_displays_platform_icons_for_validated_social_links(): void
    {
        $this->database();
        DB::table('idols')->where('idx', 101)->update([
            'sns_links' => json_encode([
                'https://www.instagram.com/test-idol',
                'https://youtu.be/test-video',
                'https://example.test/profile',
            ]),
        ]);

        $this->get('/idol/101')->assertOk()
            ->assertSee('social-link-icon--instagram', false)
            ->assertSee('social-link-icon--youtube', false)
            ->assertSee('social-link-icon--external', false)
            ->assertSee('aria-label="Instagram"', false)
            ->assertSee('target="_blank" rel="noopener noreferrer"', false)
            ->assertSee('href="https://example.test/profile"', false);
    }

    public function test_representative_images_fall_back_to_profile_image_then_global_crown(): void
    {
        $this->database();
        $profileImage = 'https://images.example.test/idols/test-profile.jpg';
        DB::table('idols')->where('idx', 101)->update(['image_path' => $profileImage]);

        $this->get('/idol/101')->assertOk()
            ->assertSee('src="'.$profileImage.'"', false)
            ->assertDontSee('no_fan_post_images');

        DB::table('idols')->where('idx', 101)->update(['image_path' => null]);
        $this->get('/idol/101')->assertOk()
            ->assertSee('src="'.asset('images/no-image.png').'"', false)
            ->assertDontSee('no_fan_post_images');
    }

    public function test_eligible_fan_post_images_remain_primary_without_profile_fillers(): void
    {
        $this->database();
        $profileImage = 'https://images.example.test/idols/test-profile.jpg';
        $fanPostImage = 'https://images.example.test/fan-posts/representative.jpg';
        DB::table('idols')->where('idx', 101)->update(['image_path' => $profileImage]);
        DB::table('fan_posts')->insert([
            'idx' => 1005, 'idol_idx' => 101, 'member_idx' => 1, 'media_type' => 'image',
            'image_path' => $fanPostImage, 'content' => 'Representative photo', 'view_count' => 0,
            'status' => 'published', 'created_at' => '2026-09-06 10:00:00',
        ]);

        $response = $this->get('/idol/101')->assertOk()->assertSee('src="'.$fanPostImage.'"', false);
        $images = $response->viewData('idol')['popular_images'];
        $this->assertSame([$fanPostImage], $images);
        $this->assertNotContains($profileImage, $images);
    }

    public function test_representative_images_prefer_monthly_likes_fall_back_to_lifetime_and_respect_rank_limit(): void
    {
        $this->database();
        Carbon::setTestNow('2026-10-17 12:00:00 UTC');

        try {
            DB::table('idols')->insert([
                'idx' => 103, 'type' => 'individual', 'gender' => 'female', 'name' => 'Second Idol', 'status' => 'active',
            ]);
            DB::table('idol_votes')->insert([
                'idx' => 20, 'member_idx' => 1, 'idol_idx' => 101, 'heart_amount' => 1, 'created_at' => '2026-10-01 00:00:00',
            ]);
            foreach ([
                [1005, 101, 'https://images.example.test/monthly-first.jpg'], [1006, 101, 'https://images.example.test/monthly-second.jpg'],
                [1007, 101, 'https://images.example.test/lifetime-first.jpg'], [1008, 101, 'https://images.example.test/lifetime-second.jpg'],
                [1010, 103, 'https://images.example.test/rank-two-first.jpg'], [1011, 103, 'https://images.example.test/rank-two-second.jpg'],
            ] as [$idx, $idolIdx, $image]) {
                DB::table('fan_posts')->insert([
                    'idx' => $idx, 'idol_idx' => $idolIdx, 'member_idx' => 1, 'media_type' => 'image',
                    'image_path' => $image, 'content' => 'Representative image', 'view_count' => 0,
                    'status' => 'published', 'deleted_at' => null, 'created_at' => '2026-10-01 00:00:00',
                ]);
            }
            DB::table('fan_post_likes')->insert([
                ['fan_post_idx' => 1005, 'member_idx' => 2, 'created_at' => '2026-10-01 00:00:00'],
                ['fan_post_idx' => 1005, 'member_idx' => 3, 'created_at' => '2026-10-02 00:00:00'],
                ['fan_post_idx' => 1006, 'member_idx' => 2, 'created_at' => '2026-10-03 00:00:00'],
                ['fan_post_idx' => 1007, 'member_idx' => 2, 'created_at' => '2026-09-30 23:59:59'],
                ['fan_post_idx' => 1007, 'member_idx' => 3, 'created_at' => '2026-09-29 23:59:59'],
                ['fan_post_idx' => 1007, 'member_idx' => 4, 'created_at' => '2026-09-28 23:59:59'],
                ['fan_post_idx' => 1010, 'member_idx' => 2, 'created_at' => '2026-10-04 00:00:00'],
                ['fan_post_idx' => 1010, 'member_idx' => 4, 'created_at' => '2026-10-06 00:00:00'],
                ['fan_post_idx' => 1011, 'member_idx' => 3, 'created_at' => '2026-10-05 00:00:00'],
            ]);

            $rankOne = $this->get('/idol/101')->assertOk()->viewData('idol')['popular_images'];
            $this->assertSame(['monthly-first.jpg', 'monthly-second.jpg'], array_map(
                fn (string $url) => basename(parse_url($url, PHP_URL_PATH)), $rankOne
            )); // Missing third monthly slot is not filled with a lifetime image.

            $rankTwo = $this->get('/idol/103')->assertOk()->viewData('idol')['popular_images'];
            $this->assertCount(1, $rankTwo);
            $this->assertStringEndsWith('rank-two-first.jpg', $rankTwo[0]);

            DB::table('fan_post_likes')->whereIn('fan_post_idx', [1005, 1006])->delete();
            $lifetimeFallback = $this->get('/idol/101')->assertOk()->viewData('idol')['popular_images'];
            $this->assertStringEndsWith('lifetime-first.jpg', $lifetimeFallback[0]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_detail_keeps_legacy_single_sns_urls_visible(): void
    {
        $this->database();
        DB::table('idols')->where('idx', 101)->update(['sns' => 'https://twitter.com/legacy-idol']);

        $this->get('/idol/101')->assertOk()
            ->assertSee('social-link-icon--x', false)
            ->assertSee('href="https://twitter.com/legacy-idol"', false)
            ->assertSee('aria-label="X / Twitter"', false);
    }

    public function test_group_detail_displays_saved_genre_and_free_text_leader(): void
    {
        $this->database();
        DB::table('idols')->where('idx', 102)->update(['genre' => 'Pop', 'leader' => 'Member Leader']);

        $this->get('/idol/102')->assertOk()
            ->assertSee('Genre')->assertSee('Pop')
            ->assertSee('Leader')->assertSee('Member Leader');
    }

    public function test_localized_profile_fields_fall_back_per_field_and_debut_dates_are_localized(): void
    {
        $this->database();
        DB::table('idols')->where('idx', 101)->update([
            'agency' => 'Legacy Agency', 'position' => 'Legacy Position',
        ]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 101, 'language_code' => 'en', 'localized_name' => 'Test Idol', 'description' => 'English bio',
                'agency' => 'English Agency', 'position' => 'English Position', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 101, 'language_code' => 'ko', 'localized_name' => '테스트 아이돌', 'description' => null,
                'agency' => '한국 기획사', 'position' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('idol_debuts')->insert([
            ['idol_idx' => 101, 'country_code' => 'KR', 'debut_date' => '2015-10-20', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 101, 'country_code' => 'JP', 'debut_date' => '2017-06-28', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $korean = $this->withSession(['locale' => 'ko'])->get('/idol/101')->assertOk();
        $this->assertSame(2, substr_count($korean->getContent(), 'idol-profile-debut-entry'));
        $korean->assertSee(asset('images/flags/kr.svg'), false)->assertSee(asset('images/flags/jp.svg'), false)
            ->assertSee('datetime="2015-10-20"', false)->assertSee('datetime="2017-06-28"', false);
        $koreanProfile = $korean->viewData('profile');
        $this->assertSame('한국 기획사', $koreanProfile['Agency']);
        $this->assertSame('English Position', $koreanProfile['Position']);
        $this->assertSame('English bio', $koreanProfile['Description']);
        $this->assertSame('대한민국 · 2015년 10월 20일 / 일본 · 2017년 6월 28일', $koreanProfile['Debut Dates']);

        $englishPage = $this->withSession(['locale' => 'en'])->get('/idol/101')->assertOk()
            ->assertSee(asset('images/flags/kr.svg'), false)->assertSee(asset('images/flags/jp.svg'), false);
        $englishProfile = $englishPage->viewData('profile');
        $this->assertSame('English Agency', $englishProfile['Agency']);
        $this->assertSame('English Position', $englishProfile['Position']);
        $this->assertSame('South Korea · October 20, 2015 / Japan · June 28, 2017', $englishProfile['Debut Dates']);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 102, 'language_code' => 'en', 'localized_name' => 'Test Group', 'description' => null,
                'genre' => 'English Genre', 'leader' => 'English Leader', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 102, 'language_code' => 'ko', 'localized_name' => '테스트 그룹', 'description' => null,
                'genre' => null, 'leader' => '한국 리더', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $koreanGroupProfile = $this->withSession(['locale' => 'ko'])->get('/idol/102')->assertOk()->viewData('profile');
        $this->assertSame('English Genre', $koreanGroupProfile['Genre']);
        $this->assertSame('한국 리더', $koreanGroupProfile['Leader']);
    }

    public function test_country_flag_component_uses_local_svg_and_ignores_unknown_codes(): void
    {
        $this->blade('<x-country-flag code="KR" />')
            ->assertSee(asset('images/flags/kr.svg'), false)
            ->assertSee('width="18" height="13"', false);
        $this->blade('<x-country-flag code="XX" />')->assertDontSee('<img', false);
        $this->blade('<x-country-flag code="../../secret" />')->assertDontSee('<img', false);
    }

    public function test_idol_and_group_profile_tables_render_explicit_label_columns(): void
    {
        $this->database();

        $this->get('/idol/101')->assertOk()->assertSee('<colgroup>', false)
            ->assertSee('<col class="idol-profile-label-column">', false);
        $this->get('/idol/102')->assertOk()->assertSee('<colgroup>', false)
            ->assertSee('<col class="idol-profile-label-column">', false);
    }

    public function test_profile_fields_follow_form_order_and_omit_empty_values(): void
    {
        $this->database();
        DB::table('idols')->where('idx', 101)->update([
            'height_cm' => 0, 'weight_kg' => null, 'blood_type' => '', 'agency' => 'N/A',
            'position' => 'Vocal',
            'sns_links' => '[]', 'sns' => null,
        ]);
        DB::table('idols')->where('idx', 102)->update([
            'agency' => 'Agency', 'genre' => '   ', 'leader' => 'Leader Name',
            'sns_links' => json_encode(['https://instagram.com/test-group']), 'content' => 'Group description',
        ]);

        $individualResponse = $this->withSession(['locale' => 'en'])->get('/idol/101')->assertOk();
        $individualLabels = $this->profileLabels($individualResponse->getContent());
        $this->assertSame(['Name', 'Height / Weight', 'Position'], $individualLabels);
        $individualResponse->assertSee('0 cm');
        $this->assertNotContains('English Name', $individualLabels);
        $this->assertNotContains('Group', $individualLabels);
        $this->assertNotContains('Agency', $individualLabels);
        $this->assertNotContains('SNS', $individualLabels);

        DB::table('idol_group_members')->insert(['individual_idx' => 101, 'group_idx' => 102]);
        $assignedGroupResponse = $this->withSession(['locale' => 'en'])->get('/idol/101')->assertOk();
        $assignedLabels = $this->profileLabels($assignedGroupResponse->getContent());
        $this->assertContains('Group', $assignedLabels);
        $assignedGroupResponse->assertSee('Test Group');

        $groupResponse = $this->withSession(['locale' => 'en'])->get('/idol/102')->assertOk();
        $this->assertSame(
            ['Name', 'Agency', 'Leader', 'SNS', 'Description'],
            $this->profileLabels($groupResponse->getContent())
        );
        $groupResponse->assertSee('Leader Name')->assertSee('Group description')
            ->assertSee('social-link-icon--instagram', false)->assertDontSee('Genre')->assertDontSee('Height / Weight');
    }

    private function profileLabels(string $html): array
    {
        $document = new DOMDocument();
        $previousLibxmlMode = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previousLibxmlMode);
        $xpath = new DOMXPath($document);
        $labels = [];
        foreach ($xpath->query('//section[contains(@class,"idol-profile")]//tbody/tr/th') as $node) {
            $labels[] = trim($node->textContent);
        }

        return $labels;
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
