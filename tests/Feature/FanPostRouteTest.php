<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

class FanPostRouteTest extends TestCase
{
    public function test_get_fan_post_redirects_to_listing_and_post_route_remains_registered(): void
    {
        $this->get('/fan-post')
            ->assertStatus(302)
            ->assertRedirect(route('fan-posts.index'));

        $routes = app('router')->getRoutes();
        $getRoute = $routes->match(Request::create('/fan-post', 'GET'));
        $postRoute = $routes->match(Request::create('/fan-post', 'POST'));

        $this->assertSame('fan-post', $getRoute->uri());
        $this->assertSame(['GET', 'HEAD'], $getRoute->methods());
        $this->assertSame('fan-post.store', $postRoute->getName());
        $this->assertSame(['POST'], $postRoute->methods());
    }
}
