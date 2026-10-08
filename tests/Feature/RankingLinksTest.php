<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class RankingLinksTest extends TestCase
{
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

    public function test_main_ranking_images_and_names_share_the_correct_detail_link(): void
    {
        $xpath = $this->page('/');
        $rankings = require resource_path('data/rankings.php');
        foreach ($rankings as $category => $ranking) {
            $rows = $xpath->query('//section[@id="ranking-'.$category.'"]/ol/li');
            $this->assertSame(5, $rows->length);
            foreach ($rows as $index => $row) {
                $expected = route('idol.show', ['idx' => $ranking['idols'][$index]['idx']]);
                $this->assertSame($expected, $xpath->query('./a', $row)->item(0)->getAttribute('href'));
                $this->assertSame($expected, $xpath->query('./div/h3/a', $row)->item(0)->getAttribute('href'));
                $this->assertSame($index === 0 ? 3 : 1, $xpath->query('./a/img', $row)->length);
                $this->assertSame(0, $xpath->query('.//a//button', $row)->length);
            }
        }
        $this->assertSame(0, $xpath->query('//article[@class="fan-post-card"]//a[contains(@href,"/idol/")]')->length);
    }

    public function test_full_ranking_cards_link_by_idx_and_keep_vote_buttons_separate(): void
    {
        $rankings = require resource_path('data/rankings.php');
        foreach ([
            'boy' => ['route' => 'idol', 'gender' => 'boy'],
            'girl' => ['route' => 'idol', 'gender' => 'girl'],
            'boy-group' => ['route' => 'group', 'gender' => 'boy'],
            'girl-group' => ['route' => 'group', 'gender' => 'girl'],
        ] as $category => $state) {
            $ranking = $rankings[$category];
            $xpath = $this->page('/ranking/'.$state['route'].'?gender='.$state['gender']);
            $cards = $xpath->query('//article[@class="idol-ranking-card"]');
            $this->assertSame(count($ranking['idols']) - 1, $cards->length);
            $featuredUrl = route('idol.show', ['idx' => $ranking['idols'][0]['idx']]);
            $featuredImages = $xpath->query('//a[@class="ranking-featured-images"]/img');
            $this->assertSame(3, $featuredImages->length);
            foreach ($featuredImages as $image) {
                $this->assertSame(asset('images/no-image.png'), $image->getAttribute('src'));
            }
            $this->assertSame($featuredUrl, $featuredImages->item(0)->parentNode->getAttribute('href'));
            $this->assertSame(0, $xpath->query('//section[@class="ranking-featured"]//a//button')->length);
            $this->assertSame(3, $xpath->query('//li[@class="ranking-voter"]')->length);
            $fixtureCodes = ['demoMemberA1', 'demoMemberB2', 'demoMemberC3'];
            foreach ($xpath->query('//li[@class="ranking-voter"]') as $index => $voter) {
                $memberUrl = route('member.fan-posts', ['member_code' => $fixtureCodes[$index]]);
                $this->assertSame($memberUrl, $xpath->query('./a[contains(@class,"ranking-voter-avatar-link")]', $voter)->item(0)->getAttribute('href'));
                $this->assertSame($memberUrl, $xpath->query('.//a[contains(@class,"ranking-voter-name-link")]', $voter)->item(0)->getAttribute('href'));
                $this->assertSame(0, $xpath->query('.//p/a | .//progress/ancestor::a', $voter)->length);
            }
            $this->assertSame('2', $xpath->query('//ol[@class="idol-ranking-grid"]')->item(0)->getAttribute('start'));
            foreach ($cards as $index => $card) {
                $idol = $ranking['idols'][$index + 1];
                $this->assertSame((string) ($index + 2), $xpath->query('.//span[contains(@class,"idol-ranking-badge")]', $card)->item(0)->textContent);
                $expected = route('idol.show', ['idx' => $idol['idx']]);
                $this->assertSame($expected, $xpath->query('./a', $card)->item(0)->getAttribute('href'));
                $this->assertSame($expected, $xpath->query('.//h2/a', $card)->item(0)->getAttribute('href'));
                $this->assertSame(0, $xpath->query('.//a//button', $card)->length);
                if (isset($idol['group_idx'])) {
                    $this->assertSame(route('idol.show', ['idx' => $idol['group_idx']]), $xpath->query('.//div[@class="idol-ranking-name"]/span/a', $card)->item(0)->getAttribute('href'));
                }
            }
        }
    }

    public function test_real_top_voter_avatar_and_nickname_link_to_the_member_page(): void
    {
        $html = view('components.ranking-top-voters', [
            'idolName' => 'Test Idol',
            'voters' => [[
                'member_code' => 'memberCodeD4',
                'nickname' => 'real_member',
                'image' => 'portrait-rose',
                'bio' => 'A public profile.',
                'hearts' => 100,
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
        $expected = route('member.fan-posts', ['member_code' => 'memberCodeD4']);

        $this->assertSame($expected, $xpath->query('//a[contains(@class,"ranking-voter-avatar-link")]')->item(0)->getAttribute('href'));
        $this->assertSame($expected, $xpath->query('//a[contains(@class,"ranking-voter-name-link")]')->item(0)->getAttribute('href'));
        $this->assertSame(0, $xpath->query('//p/a | //progress/ancestor::a')->length);
    }

    public function test_all_linked_idols_and_groups_resolve_to_their_own_detail(): void
    {
        $idols = require resource_path('data/idols.php');
        foreach ($idols as $idol) {
            $xpath = $this->page(route('idol.show', ['idx' => $idol['idx']]));
            $this->assertSame($idol['name'], $xpath->query('//h1')->item(0)->textContent);
            $this->assertSame($idol['type'], $xpath->query('//tr[th="Type"]/td')->item(0)->textContent);
        }
    }
}
