<?php

namespace Tests\Unit;

use App\Http\Middleware\TrackVisitor;
use App\Support\BotUserAgentDetector;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use ReflectionMethod;
use Tests\TestCase;

class AdminTrafficExclusionTest extends TestCase
{
    public function test_admin_root_and_subpages_are_never_trackable(): void
    {
        $middleware = new TrackVisitor(new BotUserAgentDetector());
        $shouldTrack = new ReflectionMethod($middleware, 'shouldTrack');
        $htmlResponse = new Response('', 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        $this->assertFalse($shouldTrack->invoke($middleware, Request::create('/admin', 'GET'), $htmlResponse));
        $this->assertFalse($shouldTrack->invoke($middleware, Request::create('/admin/member-access-logs', 'GET'), $htmlResponse));
        $this->assertTrue($shouldTrack->invoke($middleware, Request::create('/ranking/boy', 'GET'), $htmlResponse));
    }
}
