<?php

namespace Tests\Unit;

use App\Support\SocialLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SocialLinksTest extends TestCase
{
    #[DataProvider('platformHosts')]
    public function test_platform_detection_uses_hostname_boundaries(string $url, string $expected): void
    {
        $entries = SocialLinks::entries(json_encode([$url]), null);

        $this->assertSame($expected, $entries[0]['platform']);
    }

    public static function platformHosts(): array
    {
        return [
            'instagram subdomain' => ['https://www.instagram.com/example', 'instagram'],
            'youtube subdomain' => ['https://music.youtube.com/channel/example', 'youtube'],
            'short youtube hostname' => ['https://youtu.be/example', 'youtube'],
            'tiktok subdomain' => ['https://vm.tiktok.com/example', 'tiktok'],
            'x hostname' => ['https://x.com/example', 'x'],
            'twitter hostname' => ['https://mobile.twitter.com/example', 'x'],
            'facebook hostname' => ['https://m.facebook.com/example', 'facebook'],
            'other known platform' => ['https://www.linkedin.com/in/example', 'linkedin'],
            'lookalike suffix is generic' => ['https://instagram.com.evil.test/example', 'external'],
            'lookalike prefix is generic' => ['https://evil-instagram.com/example', 'external'],
            'x lookalike is generic' => ['https://x.com.evil.test/example', 'external'],
        ];
    }

    public function test_legacy_single_sns_url_remains_available_and_renderable(): void
    {
        $this->assertSame(['https://youtube.com/@example'], SocialLinks::inputUrls(null, 'https://youtube.com/@example'));
        $this->assertSame('youtube', SocialLinks::entries(null, 'https://youtube.com/@example')[0]['platform']);
    }
}
