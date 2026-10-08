<?php

namespace Tests\Unit;

use App\Support\MemberCodeGenerator;
use PHPUnit\Framework\TestCase;

class MemberCodeGeneratorTest extends TestCase
{
    public function test_it_generates_unique_url_safe_twelve_character_codes(): void
    {
        $generator = new MemberCodeGenerator;
        $codes = array_map(fn (): string => $generator->generate(), range(1, 500));

        $this->assertCount(500, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $code);
        }
    }
}
