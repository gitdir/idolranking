<?php

namespace Tests\Feature;

use App\Support\Countries;
use Tests\TestCase;

class CountrySelectionTest extends TestCase
{
    public function test_shared_country_source_contains_the_complete_sorted_iso_list(): void
    {
        $countries = Countries::all();

        $this->assertCount(249, $countries);
        $this->assertSame('South Korea', $countries['KR']);
        $this->assertSame('United States', $countries['US']);
        foreach (array_keys($countries) as $code) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code);
        }

        $names = array_values($countries);
        $sortedNames = $names;
        usort($sortedNames, static function (string $left, string $right): int {
            return strnatcasecmp(
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $left) ?: $left,
                iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $right) ?: $right,
            );
        });
        $this->assertSame($sortedNames, $names);

        $records = Countries::records();
        $this->assertSame('대한민국 (South Korea)', $records['KR']['display']);
        $this->assertSame('日本 (Japan)', $records['JP']['display']);
        $this->assertSame('United States', $records['US']['display']);
    }

    public function test_country_picker_preselects_the_saved_code_and_displays_its_name(): void
    {
        $html = view('components.country-select', [
            'id' => 'test-country',
            'selected' => 'kr',
        ])->render();

        $this->assertStringContainsString('name="country_code" value="KR"', $html);
        $this->assertStringContainsString('>대한민국 (South Korea)</span>', $html);
        $this->assertStringContainsString('data-native="대한민국" data-english="South Korea"', $html);
        $this->assertStringContainsString('data-code="KR"', $html);
        $this->assertSame(249, substr_count($html, 'data-country-option'));
    }
}
