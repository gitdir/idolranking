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
        $this->assertFalse($shouldTrack->invoke($middleware, Request::create('/api/rankings', 'GET'), $htmlResponse));
        $this->assertTrue($shouldTrack->invoke($middleware, Request::create('/ranking/boy', 'GET'), $htmlResponse));
        $backgroundRequest = Request::create('/ranking/boy', 'GET', server: ['HTTP_SEC_FETCH_MODE' => 'cors']);
        $this->assertFalse($shouldTrack->invoke($middleware, $backgroundRequest, $htmlResponse));
    }

    public function test_only_bots_admins_and_dummy_members_are_excluded_from_visitor_aggregates(): void
    {
        $middleware = new TrackVisitor(new BotUserAgentDetector());
        $isEligibleVisitor = new ReflectionMethod($middleware, 'isEligibleVisitor');

        $guest = Request::create('/ranking/boy');
        $member = Request::create('/ranking/boy');
        $member->attributes->set('currentMember', (object) ['member_level' => 1, 'is_dummy' => 0]);
        $admin = Request::create('/ranking/boy');
        $admin->attributes->set('currentMember', (object) ['member_level' => 10, 'is_dummy' => 0]);
        $dummy = Request::create('/ranking/boy');
        $dummy->attributes->set('currentMember', (object) ['member_level' => 1, 'is_dummy' => 1]);

        $this->assertTrue($isEligibleVisitor->invoke($middleware, $guest, false));
        $this->assertTrue($isEligibleVisitor->invoke($middleware, $member, false));
        $this->assertFalse($isEligibleVisitor->invoke($middleware, $admin, false));
        $this->assertFalse($isEligibleVisitor->invoke($middleware, $dummy, false));
        $this->assertFalse($isEligibleVisitor->invoke($middleware, $guest, true));
    }
}
