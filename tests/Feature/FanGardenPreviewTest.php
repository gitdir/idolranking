<?php

namespace Tests\Feature;

use Tests\TestCase;

class FanGardenPreviewTest extends TestCase
{
    public function test_fan_garden_preview_route_is_unavailable_to_public_visitors(): void
    {
        $this->get(route('fan-garden.index'))->assertNotFound();
        $this->assertFileExists(public_path('images/fan-garden/garden-scene.svg'));
    }

    public function test_fan_garden_route_remains_unavailable_for_each_site_locale(): void
    {
        $this->withSession(['locale' => 'ko'])->get(route('fan-garden.index'))->assertNotFound();
        $this->withSession(['locale' => 'en'])->get(route('fan-garden.index'))->assertNotFound();
    }
}
