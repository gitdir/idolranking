<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RankingLinksTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated public-data tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, profile_image_path TEXT, country_code TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, debut_date TEXT, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, status TEXT DEFAULT 'active', view_count INTEGER DEFAULT 0)");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, tag TEXT)');
    }

    private function addRealRecords(): void
    {
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'MemberCode01', 'nickname' => 'real_voter', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'MemberCode02', 'nickname' => 'second_voter', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'DummyCode003', 'nickname' => 'dummy_voter', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
        ]);
        DB::table('idols')->insert([
            ['idx' => 11, 'type' => 'individual', 'gender' => 'male', 'name' => 'Actual Idol', 'image_path' => 'idols/actual.jpg', 'status' => 'active'],
            ['idx' => 12, 'type' => 'individual', 'gender' => 'female', 'name' => 'Actual Girl', 'image_path' => null, 'status' => 'active'],
            ['idx' => 13, 'type' => 'group', 'gender' => 'male', 'name' => 'Actual Boy Group', 'image_path' => null, 'status' => 'active'],
            ['idx' => 14, 'type' => 'group', 'gender' => 'female', 'name' => 'Actual Girl Group', 'image_path' => null, 'status' => 'active'],
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
        $this->assertSame('Actual Idol', trim($home->query('//section[@id="ranking-boy"]//h3/a')->item(0)->textContent));

        $ranking = $this->get('/ranking/idol')->assertOk()
            ->assertSee('Actual Idol')->assertSee('real_voter')->assertSee('second_voter')
            ->assertSee('dummy_voter')->assertDontSee('Jungkook')->assertDontSee('golden_army');
        $this->assertSame(9180, (int) DB::table('idol_votes')->sum('heart_amount'));
        $ranking->assertViewHas('idols', fn (array $idols): bool => count($idols) === 2 && $idols[0]['hearts'] === 9180);

        $detail = $this->get('/idol/11')->assertOk()
            ->assertSee('Actual Idol')->assertSee('Actual database post')
            ->assertDontSee('Jungkook')->assertDontSee('golden_army');
    }

    public function test_inactive_idols_and_their_public_details_are_not_rendered(): void
    {
        $this->database();
        $this->addRealRecords();
        DB::table('idols')->where('idx', 11)->update(['status' => 'inactive']);

        $this->get('/ranking/idol')->assertOk()->assertDontSee('Actual Idol');
        $this->get('/idol/11')->assertNotFound();
        $this->get('/fan-post/21')->assertNotFound();
    }

    public function test_real_top_voter_avatar_and_nickname_link_to_the_member_page(): void
    {
        $html = view('components.ranking-top-voters', [
            'idolName' => 'Test Idol', 'monthLabel' => '2026.10',
            'voters' => [[
                'member_code' => 'MemberCode04', 'nickname' => 'real_member',
                'image_url' => null, 'bio' => '', 'hearts' => 100,
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
        $this->assertSame(0, $xpath->query('//p/a | //progress/ancestor::a')->length);
    }
}
