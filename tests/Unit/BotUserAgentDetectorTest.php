<?php

namespace Tests\Unit;

use App\Support\BotUserAgentDetector;
use Tests\TestCase;

class BotUserAgentDetectorTest extends TestCase
{
    public function test_it_detects_common_crawlers_and_automation_clients(): void
    {
        $detector = new BotUserAgentDetector();

        $this->assertTrue($detector->isBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        $this->assertTrue($detector->isBot('facebookexternalhit/1.1'));
        $this->assertTrue($detector->isBot('UptimeRobot/2.0'));
        $this->assertTrue($detector->isBot('curl/8.10.1'));
        $this->assertTrue($detector->isBot(null));
    }

    public function test_it_allows_normal_browser_user_agents(): void
    {
        $detector = new BotUserAgentDetector();

        $this->assertFalse($detector->isBot('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36'));
        $this->assertFalse($detector->isBot('Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 Version/18.6 Mobile/15E148 Safari/604.1'));
    }
}
