<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\DashboardController;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_default_range_is_seven_calendar_days_including_today(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00');

        [$start, $end] = $this->resolve(Request::create('/admin', 'GET'));

        $this->assertSame('2026-09-21 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-27 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_valid_custom_range_is_used_and_invalid_range_falls_back(): void
    {
        CarbonImmutable::setTestNow('2026-09-27 12:00:00');

        [$start, $end] = $this->resolve(Request::create('/admin?start_date=2026-09-01&end_date=2026-09-15', 'GET'));
        $this->assertSame('2026-09-01', $start->toDateString());
        $this->assertSame('2026-09-15', $end->toDateString());

        [$fallbackStart, $fallbackEnd] = $this->resolve(Request::create('/admin?start_date=2026-09-30&end_date=2026-09-01', 'GET'));
        $this->assertSame('2026-09-21', $fallbackStart->toDateString());
        $this->assertSame('2026-09-27', $fallbackEnd->toDateString());
    }

    private function resolve(Request $request): array
    {
        $controller = new DashboardController();
        $method = new ReflectionMethod($controller, 'resolveDateRange');

        return $method->invoke($controller, $request);
    }
}
