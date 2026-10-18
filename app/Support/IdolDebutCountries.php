<?php

namespace App\Support;

final class IdolDebutCountries
{
    private const NAMES = [
        'KR' => ['ko' => '대한민국', 'en' => 'South Korea'],
        'JP' => ['ko' => '일본', 'en' => 'Japan'],
        'US' => ['ko' => '미국', 'en' => 'United States'],
        'CN' => ['ko' => '중국', 'en' => 'China'],
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::NAMES);
    }

    /** @return array<string, array{code: string, english: string, native: string, display: string}> */
    public static function records(string $locale): array
    {
        $language = str_starts_with(str_replace('_', '-', $locale), 'ko') ? 'ko' : 'en';
        $records = [];

        foreach (self::NAMES as $code => $names) {
            $records[$code] = [
                'code' => $code,
                'english' => $names['en'],
                'native' => $names['ko'],
                'display' => $names[$language],
            ];
        }

        return $records;
    }
}
