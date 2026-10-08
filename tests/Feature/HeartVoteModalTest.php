<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class HeartVoteModalTest extends TestCase
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

    public function test_main_uses_one_shared_modal_and_data_driven_vote_buttons(): void
    {
        $xpath = $this->page('/');
        $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
        $this->assertSame('false', $xpath->query('//dialog[@id="heart-vote-modal"]')->item(0)->getAttribute('data-signed-in'));
        $buttons = $xpath->query('//*[@data-heart-vote-open]');
        $this->assertSame(20, $buttons->length);
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
        foreach (['/ranking/idol', '/ranking/group'] as $url) {
            $xpath = $this->page($url);
            $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
            $this->assertSame(24, $xpath->query('//*[@data-heart-vote-open]')->length);
        }

        $xpath = $this->page('/idol/1');
        $this->assertSame(1, $xpath->query('//dialog[@id="heart-vote-modal"]')->length);
        $this->assertSame('Jungkook', $xpath->query('//*[@data-heart-vote-open]')->item(0)->getAttribute('data-vote-name'));
    }

    public function test_modal_has_eight_presets_and_shared_heart_icon_column(): void
    {
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

    public function test_public_pages_include_one_reusable_notification_region(): void
    {
        $xpath = $this->page('/');
        $notification = $xpath->query('//*[@data-global-notification]');

        $this->assertSame(1, $notification->length);
        $this->assertSame('status', $notification->item(0)->getAttribute('role'));
        $this->assertSame('polite', $notification->item(0)->getAttribute('aria-live'));
    }
}
