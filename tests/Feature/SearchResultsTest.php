<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchResultsTest extends TestCase
{
    public function test_empty_search_renders_with_noindex_and_preserves_the_common_header(): void
    {
        $response = $this->get('/search');

        $response->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('Search Results')
            ->assertSee('Enter a keyword to search.')
            ->assertDontSee('id="search-results-query"', false)
            ->assertSee('id="idol-search"', false)
            ->assertSee('id="main-navigation"', false);
    }

    public function test_unknown_search_type_and_sort_are_normalized(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The database search test requires pdo_sqlite.');
        }

        $response = $this->get('/search?q=unlikely-search-value-928374&type=invalid&sort=invalid');

        $response->assertOk()
            ->assertSee('No results found for', false)
            ->assertSee('unlikely-search-value-928374', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('id="search-results-query"', false);
    }
}
