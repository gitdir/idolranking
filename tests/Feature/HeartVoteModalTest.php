<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HeartVoteModalTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated voting UI tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, status TEXT DEFAULT 'active', view_count INTEGER DEFAULT 0)");
        DB::statement("CREATE TABLE idol_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, country_code))");
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, tag TEXT)');
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'ActualCode01', 'nickname' => 'actual_member']);
        DB::table('idols')->insert([
            ['idx' => 1, 'type' => 'individual', 'gender' => 'male', 'name' => 'Actual Idol'],
            ['idx' => 2, 'type' => 'group', 'gender' => 'male', 'name' => 'Actual Group'],
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

    public function test_main_uses_one_shared_modal_and_data_driven_vote_buttons(): void
    {
        $this->database();
        $xpath = $this->page('/');
        $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
        $this->assertSame('false', $xpath->query('//dialog[@id="heart-vote-modal"]')->item(0)->getAttribute('data-signed-in'));
        $buttons = $xpath->query('//*[@data-heart-vote-open]');
        $this->assertSame(2, $buttons->length);
        foreach ($buttons as $button) {
            foreach (['data-vote-target-id', 'data-vote-name', 'data-vote-meta', 'data-vote-image', 'data-vote-total', 'data-vote-posts'] as $attribute) {
                $this->assertNotSame('', $button->getAttribute($attribute));
            }
            $this->assertFalse($button->hasAttribute('disabled'));
            $this->assertSame('heart-vote-modal', $button->getAttribute('aria-controls'));
        }
    }

    public function test_unified_ranking_pages_and_idol_detail_use_the_shared_vote_modal(): void
    {
        $this->database();
        foreach (['/ranking/idol', '/ranking/group'] as $url) {
            $xpath = $this->page($url);
            $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
            $this->assertSame(1, $xpath->query('//*[@data-heart-vote-open]')->length);
        }

        $xpath = $this->page('/idol/1');
        $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
        $this->assertSame('Actual Idol', $xpath->query('//*[@data-heart-vote-open]')->item(0)->getAttribute('data-vote-name'));
    }

    public function test_modal_has_eight_presets_and_shared_heart_icon_column(): void
    {
        $this->database();
        $xpath = $this->page('/');
        $presets = $xpath->query('//dialog[@id="heart-vote-modal"]//*[@data-heart-preset]');
        $this->assertSame(8, $presets->length);
        $this->assertSame('5000', $presets->item(6)->getAttribute('data-heart-preset'));
        $this->assertSame('5,000', trim($presets->item(6)->textContent));
        $this->assertSame('10000', $presets->item(7)->getAttribute('data-heart-preset'));
        $this->assertSame('10,000', trim($presets->item(7)->textContent));
        $this->assertSame(2, $xpath->query('//dialog[@id="heart-vote-modal"]//span[contains(concat(" ", normalize-space(@class), " "), " heart-vote-icon-column ")]')->length);
        $this->assertSame('1', $xpath->query('//input[@id="heart-vote-amount"]')->item(0)->getAttribute('value'));
    }

    public function test_modal_script_updates_only_elements_rendered_by_the_shared_component(): void
    {
        $this->database();
        $xpath = $this->page('/');
        $script = file_get_contents(resource_path('js/heart-vote-modal.js'));

        $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]//*[@data-heart-vote-balance]')->length);
        $this->assertStringContainsString("[data-heart-vote-balance]", $script);
        $this->assertStringNotContainsString('[data-heart-vote-limit]', $script);
    }

    public function test_random_dummy_vote_modal_does_not_render_or_apply_the_admin_balance_limit(): void
    {
        $html = view('components.heart-vote-modal', [
            'myHearts' => 40000,
            'signedIn' => true,
            'randomDummyMode' => true,
        ])->render();
        $script = file_get_contents(resource_path('js/heart-vote-modal.js'));

        $this->assertStringContainsString('data-my-hearts="0"', $html);
        $this->assertStringContainsString('data-random-dummy-mode="true"', $html);
        $this->assertStringContainsString(__('ui.random_dummy_vote_mode'), $html);
        $this->assertStringContainsString(__('ui.random_dummy_vote_notice'), $html);
        $this->assertStringNotContainsString('40,000', $html);
        $this->assertStringNotContainsString('data-heart-vote-balance', $html);
        $this->assertStringContainsString("!randomDummyMode && value > maximum", $script);
        $this->assertStringContainsString("if (!randomDummyMode) {", $script);
    }

    public function test_public_pages_include_one_reusable_notification_region(): void
    {
        $this->database();
        $xpath = $this->page('/');
        $notification = $xpath->query('//*[@data-global-notification]');

        $this->assertSame(1, $notification->length);
        $this->assertSame('status', $notification->item(0)->getAttribute('role'));
        $this->assertSame('polite', $notification->item(0)->getAttribute('aria-live'));
    }
}
