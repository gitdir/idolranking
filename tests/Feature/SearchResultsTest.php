<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchResultsTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated search tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, english_name TEXT, image_path TEXT, content TEXT, status TEXT DEFAULT 'active')");
        DB::statement('CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY, idol_idx INTEGER, language_code TEXT, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))');
        DB::statement('CREATE TABLE idol_name_aliases (idx INTEGER PRIMARY KEY, idol_idx INTEGER, language_code TEXT, alias_name TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, tag TEXT)');
    }

    public function test_empty_search_renders_with_noindex_and_preserves_the_common_header(): void
    {
        $response = $this->get('/search');

        $response->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('Search Results')
            ->assertSee('Enter a keyword to search.')
            ->assertSee('id="search-results-query"', false)
            ->assertSee('id="idol-search"', false)
            ->assertSee('id="site-search-dialog"', false)
            ->assertSee('aria-modal="true"', false)
            ->assertSee('class="search-dialog-header"', false)
            ->assertSee('class="search-dialog-input-wrap"', false)
            ->assertSee('data-search-open', false)
            ->assertSee('name="q"', false)
            ->assertSee('action="'.route('search').'"', false)
            ->assertDontSee('class="site-search"', false)
            ->assertSee('id="main-navigation"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'id="idol-search"'));
        $this->assertLessThan(
            strpos($response->getContent(), 'class="header-search-trigger"'),
            strpos($response->getContent(), 'class="drawer-category-link header-attendance"')
        );
    }

    public function test_unknown_search_type_and_sort_are_normalized(): void
    {
        $this->database();

        $response = $this->get('/search?q=unlikely-search-value-928374&type=invalid&sort=invalid');

        $response->assertOk()
            ->assertSee('No results found for', false)
            ->assertSee('unlikely-search-value-928374', false)
            ->assertSee('id="search-results-query" name="q" type="search" value="unlikely-search-value-928374"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('class="search-results-form"', false);
    }
}
