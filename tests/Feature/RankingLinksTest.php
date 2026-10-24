<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RankingLinksTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated public-data tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, profile_image_path TEXT, country_code TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, status TEXT DEFAULT 'active', view_count INTEGER DEFAULT 0)");
        DB::statement("CREATE TABLE idol_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, country_code))");
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))");
        DB::statement("CREATE TABLE idol_name_aliases (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, alias_name TEXT NOT NULL, created_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, queued_at TEXT, published_at TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, tag TEXT)');
    }

    private function addRealRecords(): void
    {
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'MemberCode01', 'nickname' => 'real_voter', 'country_code' => 'KR', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'MemberCode02', 'nickname' => 'second_voter', 'country_code' => 'JP', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'DummyCode003', 'nickname' => 'dummy_voter', 'country_code' => 'US', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
        ]);
        DB::table('idols')->insert([
            ['idx' => 11, 'type' => 'individual', 'gender' => 'male', 'name' => 'Actual Idol', 'english_name' => 'Canonical English', 'image_path' => 'idols/actual.jpg', 'content' => 'Legacy description', 'status' => 'active'],
            ['idx' => 12, 'type' => 'individual', 'gender' => 'female', 'name' => 'Actual Girl', 'english_name' => null, 'content' => null, 'image_path' => null, 'status' => 'active'],
            ['idx' => 13, 'type' => 'group', 'gender' => 'male', 'name' => 'Actual Boy Group', 'english_name' => null, 'content' => null, 'image_path' => null, 'status' => 'active'],
            ['idx' => 14, 'type' => 'group', 'gender' => 'female', 'name' => 'Actual Girl Group', 'english_name' => null, 'content' => null, 'image_path' => null, 'status' => 'active'],
        ]);
        DB::table('idol_votes')->insert([
            ['idx' => 1, 'member_idx' => 1, 'idol_idx' => 11, 'heart_amount' => 100, 'created_at' => now()],
            ['idx' => 2, 'member_idx' => 2, 'idol_idx' => 11, 'heart_amount' => 80, 'created_at' => now()],
            ['idx' => 3, 'member_idx' => 3, 'idol_idx' => 11, 'heart_amount' => 9000, 'created_at' => now()],
        ]);
        DB::table('fan_posts')->insert([
            ['idx' => 21, 'idol_idx' => 11, 'member_idx' => 1, 'title' => 'Actual media post', 'media_type' => 'image', 'image_path' => 'fan-posts/actual.jpg', 'content' => 'Actual database post', 'view_count' => 20, 'status' => 'published', 'created_at' => now()],
        ]);
    }

    private function page(string $url): DOMXPath
    {
        $response = $this->get($url)->assertOk();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new DOMXPath($document);
    }

    public function test_empty_public_datasets_render_empty_states_and_fixture_ids_404(): void
    {
        $this->database();

        $this->get('/')->assertOk()
            ->assertSee('No active records are available for this ranking yet.')
            ->assertSee('No fan posts yet.')
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
        $this->get('/ranking/idol')->assertOk()
            ->assertSee('No active idols are available for this ranking yet.')
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
        $this->get('/idol/1')->assertNotFound();
        $this->get('/fan-post/1')->assertNotFound();
        $this->get('/member/demoMemberA1/fan-posts')->assertNotFound();
    }

    public function test_home_ranking_and_top_voters_render_only_real_active_records(): void
    {
        $this->database();
        $this->addRealRecords();

        $home = $this->page('/');
        $this->assertSame(1, $home->query('//section[@id="ranking-boy"]//li[contains(@class,"ranking-entry")]')->length);
        $this->assertSame('Canonical English', trim($home->query('//section[@id="ranking-boy"]//h3/a')->item(0)->textContent));

        $ranking = $this->get('/ranking/idol')->assertOk()
            ->assertSee('Canonical English')->assertSee('real_voter')->assertSee('second_voter')
            ->assertSee('dummy_voter')->assertDontSee('Jungkook')->assertDontSee('golden_army');
        $this->assertSame(9180, (int) DB::table('idol_votes')->sum('heart_amount'));
        $ranking->assertViewHas('idols', fn (array $idols): bool => count($idols) === 2 && $idols[0]['hearts'] === 9180);

        $detail = $this->get('/idol/11')->assertOk()
            ->assertSee('Canonical English')->assertSee('Actual database post')
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
    }

    public function test_home_and_full_ranking_image_strips_fall_back_without_changing_fan_post_priority(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('fan_posts')->where('idx', 21)->update(['media_type' => 'text']);
        $profileImage = 'https://images.example.test/idols/actual.jpg';
        DB::table('idols')->where('idx', 11)->update(['image_path' => $profileImage]);

        $home = $this->get('/')->assertOk();
        $home->assertSee('src="'.$profileImage.'"', false)->assertDontSee('No Fan Post images yet.');
        $ranking = $this->get('/ranking/idol')->assertOk()
            ->assertViewHas('featuredImages', [$profileImage]);
        $ranking->assertSee('src="'.$profileImage.'"', false)->assertDontSee('No Fan Post images yet.');

        $fanPostImage = 'https://images.example.test/fan-posts/ranking.jpg';
        DB::table('fan_posts')->insert([
            'idx' => 22, 'idol_idx' => 11, 'member_idx' => 1, 'title' => 'Representative photo',
            'media_type' => 'image', 'image_path' => $fanPostImage, 'content' => 'Image post',
            'view_count' => 0, 'status' => 'published', 'created_at' => now(),
        ]);

        $this->get('/ranking/idol')->assertOk()
            ->assertViewHas('featuredImages', [$fanPostImage]);
    }

    public function test_home_and_full_ranking_use_global_crown_when_profile_and_fan_post_images_are_absent(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('fan_posts')->where('idx', 21)->update(['media_type' => 'text']);

        $fallback = asset('images/no-image.png');
        $this->get('/')->assertOk()->assertSee('src="'.$fallback.'"', false)
            ->assertDontSee('No Fan Post images yet.');
        $this->get('/ranking/idol')->assertOk()->assertViewHas('featuredImages', [$fallback])
            ->assertSee('src="'.$fallback.'"', false)
            ->assertDontSee('No Fan Post images yet.');
    }

    public function test_full_ranking_lower_cards_use_one_monthly_fan_post_image(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idols')->insert([
            'idx' => 15, 'type' => 'individual', 'gender' => 'male', 'name' => 'Third Idol',
            'english_name' => 'Third Idol', 'image_path' => 'https://images.example.test/profile.jpg', 'status' => 'active',
        ]);
        DB::table('idol_votes')->insert([
            ['idx' => 10, 'member_idx' => 1, 'idol_idx' => 12, 'heart_amount' => 100, 'created_at' => now()],
            ['idx' => 11, 'member_idx' => 1, 'idol_idx' => 15, 'heart_amount' => 50, 'created_at' => now()],
        ]);
        $fanPostImage = 'https://images.example.test/fan-posts/third-idol.jpg';
        DB::table('fan_posts')->insert([
            'idx' => 22, 'idol_idx' => 15, 'member_idx' => 1, 'title' => 'Third idol photo',
            'media_type' => 'image', 'image_path' => $fanPostImage, 'content' => 'Photo',
            'status' => 'published', 'deleted_at' => null, 'created_at' => now(),
        ]);
        DB::table('fan_post_likes')->insert([
            'fan_post_idx' => 22, 'member_idx' => 2, 'created_at' => now('UTC'),
        ]);

        $this->get('/ranking/idol')->assertOk()
            ->assertViewHas('idols', fn (array $idols): bool => count($idols) === 3
                && $idols[2]['idx'] === 15 && $idols[2]['representative_image_url'] === $fanPostImage);
    }

    public function test_monthly_rankings_and_top_voters_use_utc_half_open_boundaries_without_resetting_history(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idol_votes')->delete();
        DB::table('members')->where('idx', 1)->update(['heart_balance' => 321]);
        Carbon::setTestNow('2026-10-31 23:59:59 UTC');
        DB::table('idol_votes')->insert([
            ['idx' => 10, 'member_idx' => 1, 'idol_idx' => 11, 'heart_amount' => 10, 'created_at' => '2026-10-01 00:00:00'],
            ['idx' => 11, 'member_idx' => 1, 'idol_idx' => 11, 'heart_amount' => 900, 'created_at' => '2026-09-30 23:59:59'],
            ['idx' => 12, 'member_idx' => 1, 'idol_idx' => 11, 'heart_amount' => 1000, 'created_at' => '2026-11-01 00:00:00'],
            ['idx' => 13, 'member_idx' => 2, 'idol_idx' => 11, 'heart_amount' => 5, 'created_at' => '2026-10-31 23:59:59'],
            ['idx' => 14, 'member_idx' => 1, 'idol_idx' => 12, 'heart_amount' => 7, 'created_at' => '2026-10-15 12:00:00'],
            ['idx' => 15, 'member_idx' => 1, 'idol_idx' => 13, 'heart_amount' => 8, 'created_at' => '2026-10-31 23:59:59'],
            ['idx' => 16, 'member_idx' => 1, 'idol_idx' => 14, 'heart_amount' => 4, 'created_at' => '2026-10-01 00:00:00'],
        ]);

        try {
            $rankings = app(\App\Support\PublicRanking::class);
            $month = Carbon::parse('2026-10-01 00:00:00', 'UTC');
            $this->assertSame(15, $rankings->ranking('individual', 'male', $month)[0]['hearts']);
            $this->assertSame(7, $rankings->ranking('individual', 'female', $month)[0]['hearts']);
            $this->assertSame(8, $rankings->ranking('group', 'male', $month)[0]['hearts']);
            $this->assertSame(4, $rankings->ranking('group', 'female', $month)[0]['hearts']);
            $voters = $rankings->topVoters(11, $month);
            $this->assertSame([10, 5], array_column($voters, 'hearts'));
            $this->assertSame(['KR', 'JP'], array_column($voters, 'country_code'));

            Carbon::setTestNow('2026-11-01 00:00:00 UTC');
            $this->assertSame(1000, $rankings->category('boy')[0]['hearts']);
            $this->assertSame(1000, $rankings->topVoters(11, Carbon::now('UTC'))[0]['hearts']);
            $this->assertSame(1934, (int) DB::table('idol_votes')->sum('heart_amount'));
            $this->assertSame(7, DB::table('idol_votes')->count());
            $this->assertSame(321, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_zero_vote_items_use_a_daily_stable_order(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idols')->insert([
            ['idx' => 30, 'type' => 'group', 'gender' => 'male', 'name' => 'Zero Group 30', 'status' => 'active'],
            ['idx' => 31, 'type' => 'group', 'gender' => 'male', 'name' => 'Zero Group 31', 'status' => 'active'],
            ['idx' => 32, 'type' => 'group', 'gender' => 'male', 'name' => 'Zero Group 32', 'status' => 'active'],
            ['idx' => 33, 'type' => 'group', 'gender' => 'male', 'name' => 'Zero Group 33', 'status' => 'active'],
        ]);
        Carbon::setTestNow('2026-10-17 12:00:00 UTC');

        try {
            $rankings = app(\App\Support\PublicRanking::class);
            $month = Carbon::now('UTC');
            $first = array_column($rankings->ranking('group', 'male', $month), 'idx');
            $this->assertSame($first, array_column($rankings->ranking('group', 'male', $month), 'idx'));

            Carbon::setTestNow('2026-10-18 12:00:00 UTC');
            $this->assertNotSame($first, array_column($rankings->ranking('group', 'male', Carbon::now('UTC')), 'idx'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_korean_and_english_localizations_render_in_detail_rankings_and_seo(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 11, 'language_code' => 'ko', 'localized_name' => '별하', 'description' => "한국어 소개\n둘째 줄", 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 11, 'language_code' => 'en', 'localized_name' => 'Stella', 'description' => 'English bio', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $korean = $this->withSession(['locale' => 'ko'])->get('/idol/11')->assertOk()
            ->assertSee('별하')->assertSee('한국어 소개')
            ->assertSee('<meta property="og:title" content="별하 | IDOL RANKING">', false)
            ->assertSee('<meta name="description" content="한국어 소개', false);
        $korean->assertViewHas('seo', fn (array $seo): bool => $seo['title'] === '별하 | IDOL RANKING'
            && str_contains($seo['description'], '한국어 소개'));

        $this->withSession(['locale' => 'ko'])->get('/ranking/idol')->assertOk()->assertSee('별하');
        $this->withSession(['locale' => 'en'])->get('/ranking/idol')->assertOk()->assertSee('Stella');
        $this->withSession(['locale' => 'en'])->get('/idol/11')->assertOk()
            ->assertSee('Stella')->assertSee('English bio')
            ->assertSee('<meta property="og:title" content="Stella | IDOL RANKING">', false)
            ->assertSee('<meta property="og:description" content="English bio">', false);
    }

    public function test_member_fan_post_like_percentile_uses_public_likes_and_assigns_ties_consistently(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('fan_posts')->insert([
            'idx' => 22, 'idol_idx' => 11, 'member_idx' => 2, 'title' => 'Second member post',
            'media_type' => 'image', 'image_path' => 'fan-posts/second.jpg', 'content' => 'Second post',
            'status' => 'published', 'created_at' => now(),
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 21, 'member_idx' => 2, 'created_at' => now()],
            ['fan_post_idx' => 21, 'member_idx' => 3, 'created_at' => now()],
            ['fan_post_idx' => 22, 'member_idx' => 1, 'created_at' => now()],
            ['fan_post_idx' => 22, 'member_idx' => 3, 'created_at' => now()],
        ]);

        $ranking = app(\App\Support\PublicRanking::class);

        $this->assertSame(34, $ranking->memberFanPostLikesPercentile(1));
        $this->assertSame(34, $ranking->memberFanPostLikesPercentile(2));
        $this->assertSame(100, $ranking->memberFanPostLikesPercentile(3));
        $this->assertNull($ranking->memberFanPostLikesPercentile(999));
    }

    public function test_idol_names_and_descriptions_are_escaped_in_document_title_and_seo_metadata(): void
    {
        $this->database();
        $this->addRealRecords();
        $unsafeName = '<script>alert(1)</script>';
        $unsafeDescription = '<img src=x onerror=alert(2)>';
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 11, 'language_code' => 'en', 'localized_name' => $unsafeName, 'description' => $unsafeDescription, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->withSession(['locale' => 'en'])->get('/idol/11')->assertOk();
        $response->assertSee('<title>&lt;script&gt;alert(1)&lt;/script&gt; | IDOL RANKING</title>', false)
            ->assertSee('<meta property="og:title" content="&lt;script&gt;alert(1)&lt;/script&gt; | IDOL RANKING">', false)
            ->assertSee('<meta name="description" content="&lt;img src=x onerror=alert(2)&gt;">', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false);
    }

    public function test_localization_fallback_uses_canonical_english_then_legacy_and_legacy_description(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 11, 'language_code' => 'ko', 'localized_name' => '별하', 'description' => '한국어 소개', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['locale' => 'en'])->get('/idol/11')->assertOk()
            ->assertSee('Canonical English')->assertSee('Legacy description');
        $this->get('/idol/12')->assertOk()->assertSee('Actual Girl');
    }

    public function test_search_matches_localized_names_and_aliases_once_and_excludes_inactive_entities(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 11, 'language_code' => 'ko', 'localized_name' => 'Star Alias', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 11, 'language_code' => 'en', 'alias_name' => 'Star Alias', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 11, 'language_code' => 'ko', 'alias_name' => 'Star Alias Official', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/search?q=Star%20Alias&type=idol')->assertOk()
            ->assertViewHas('counts', fn (array $counts): bool => $counts['idol'] === 1)
            ->assertViewHas('idols', fn (array $idols): bool => count($idols) === 1 && $idols[0]['name'] === 'Canonical English');

        DB::table('idols')->where('idx', 11)->update(['status' => 'inactive']);
        $this->get('/search?q=Star%20Alias&type=idol')->assertOk()
            ->assertViewHas('counts', fn (array $counts): bool => $counts['idol'] === 0)
            ->assertViewHas('idols', fn (array $idols): bool => $idols === []);
    }

    public function test_inactive_idols_and_their_public_details_are_not_rendered(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idols')->where('idx', 11)->update(['status' => 'inactive']);

        $this->get('/ranking/idol')->assertOk()->assertDontSee('Canonical English');
        $this->get('/idol/11')->assertNotFound();
        $this->get('/fan-post/21')->assertNotFound();
    }

    public function test_real_top_voter_avatar_and_nickname_link_to_the_member_page(): void
    {
        $html = view('components.ranking-top-voters', [
            'idolName' => 'Test Idol', 'monthLabel' => '2026.10',
            'voters' => [[
                'member_code' => 'MemberCode04', 'nickname' => 'real_member',
                'country_code' => 'KR', 'image_url' => null, 'bio' => '', 'hearts' => 100,
            ]],
        ])->render();
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $expected = route('member.fan-posts', ['member_code' => 'MemberCode04']);

        $this->assertSame($expected, $xpath->query('//a[contains(@class,"ranking-voter-avatar-link")]')->item(0)->getAttribute('href'));
        $this->assertSame($expected, $xpath->query('//a[contains(@class,"ranking-voter-name-link")]')->item(0)->getAttribute('href'));
        $this->assertSame(asset('images/flags/kr.svg'), $xpath->query('//h3//img[contains(@class,"country-flag")]')->item(0)->getAttribute('src'));
        $this->assertSame(0, $xpath->query('//p/a | //progress/ancestor::a')->length);
    }
}
