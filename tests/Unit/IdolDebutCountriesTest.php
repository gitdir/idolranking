<?php

namespace Tests\Unit;

use App\Support\IdolDebutCountries;
use App\Support\IdolDebutDates;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdolDebutCountriesTest extends TestCase
{
    public function test_options_are_limited_to_four_countries_in_the_requested_order_and_locale(): void
    {
        $this->assertSame(['KR', 'JP', 'US', 'CN'], IdolDebutCountries::codes());
        $this->assertSame(
            ['대한민국', '일본', '미국', '중국'],
            array_column(IdolDebutCountries::records('ko'), 'display')
        );
        $this->assertSame(
            ['South Korea', 'Japan', 'United States', 'China'],
            array_column(IdolDebutCountries::records('en'), 'display')
        );
    }

    public function test_debut_date_validation_accepts_allowed_countries_and_an_empty_optional_row(): void
    {
        $this->assertSame([], IdolDebutDates::validate([['country_code' => '', 'debut_date' => '']]));
        $this->assertSame(
            [['country_code' => 'JP', 'debut_date' => '2020-02-03']],
            IdolDebutDates::validate([['country_code' => 'JP', 'debut_date' => '2020-02-03']])
        );
    }

    public function test_debut_date_validation_rejects_countries_outside_the_debut_allowlist(): void
    {
        $this->expectException(ValidationException::class);

        IdolDebutDates::validate([['country_code' => 'GB', 'debut_date' => '2020-02-03']]);
    }
}
