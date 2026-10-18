<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdolDebutDates
{
    /** @return list<array{country_code: string, debut_date: string}> */
    public static function validate(mixed $input, string $attribute = 'debut_dates'): array
    {
        $validator = Validator::make([$attribute => $input], [
            $attribute => ['nullable', 'array', 'max:249'],
            $attribute.'.*' => ['array:country_code,debut_date'],
            $attribute.'.*.country_code' => ['nullable', 'string', Rule::in(IdolDebutCountries::codes())],
            $attribute.'.*.debut_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            $attribute.'.*.country_code.in' => __('ui.debut_date_country_invalid'),
            $attribute.'.*.debut_date.date_format' => __('ui.debut_date_invalid'),
            $attribute.'.*.debut_date.before_or_equal' => __('ui.debut_date_future'),
        ]);

        $validator->after(function ($validator) use ($input, $attribute): void {
            if (!is_array($input)) {
                return;
            }

            $seenCountries = [];
            foreach ($input as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $countryCode = is_string($row['country_code'] ?? null) ? strtoupper(trim($row['country_code'])) : '';
                $date = is_string($row['debut_date'] ?? null) ? trim($row['debut_date']) : '';
                if ($countryCode === '' && $date === '') {
                    continue;
                }
                if ($countryCode === '') {
                    $validator->errors()->add($attribute.'.'.$index.'.country_code', __('ui.debut_date_country_required'));
                }
                if ($date === '') {
                    $validator->errors()->add($attribute.'.'.$index.'.debut_date', __('ui.debut_date_required'));
                }
                if ($countryCode !== '' && in_array($countryCode, IdolDebutCountries::codes(), true)) {
                    if (isset($seenCountries[$countryCode])) {
                        $validator->errors()->add($attribute.'.'.$index.'.country_code', __('ui.debut_date_country_duplicate'));
                    }
                    $seenCountries[$countryCode] = true;
                }
            }
        });

        $rows = $validator->validate()[$attribute] ?? [];
        $normalized = [];
        foreach ($rows as $row) {
            $countryCode = strtoupper(trim((string) ($row['country_code'] ?? '')));
            $date = trim((string) ($row['debut_date'] ?? ''));
            if ($countryCode !== '' && $date !== '') {
                $normalized[] = ['country_code' => $countryCode, 'debut_date' => $date];
            }
        }

        return $normalized;
    }

    /** @return list<array{country_code: string, debut_date: string}> */
    public static function forIdol(int $idolIdx): array
    {
        return DB::table('idol_debuts')->where('idol_idx', $idolIdx)
            ->orderBy('debut_date')->orderBy('country_code')
            ->get(['country_code', 'debut_date'])
            ->map(fn (object $row): array => ['country_code' => $row->country_code, 'debut_date' => $row->debut_date])
            ->all();
    }

    /** @param list<array{country_code: string, debut_date: string}> $rows */
    public static function replaceForIdol(int $idolIdx, array $rows): void
    {
        $current = DB::table('idol_debuts')->where('idol_idx', $idolIdx)->get()->keyBy('country_code');
        $submitted = collect($rows)->keyBy('country_code');

        foreach ($current as $countryCode => $row) {
            if (!$submitted->has($countryCode)) {
                DB::table('idol_debuts')->where('idx', $row->idx)->delete();
            } elseif ($row->debut_date !== $submitted->get($countryCode)['debut_date']) {
                DB::table('idol_debuts')->where('idx', $row->idx)->update([
                    'debut_date' => $submitted->get($countryCode)['debut_date'], 'updated_at' => now(),
                ]);
            }
        }

        $now = now();
        foreach ($submitted as $countryCode => $row) {
            if (!$current->has($countryCode)) {
                DB::table('idol_debuts')->insert($row + [
                    'idol_idx' => $idolIdx, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    /** @param list<array{country_code: string, debut_date: string}> $rows */
    public static function insertForRequest(int $requestIdx, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $now = now();
        DB::table('idol_registration_request_debuts')->insert(array_map(
            static fn (array $row): array => $row + [
                'request_idx' => $requestIdx, 'created_at' => $now, 'updated_at' => $now,
            ],
            $rows
        ));
    }

    public static function copyRequestToIdol(int $requestIdx, int $idolIdx): void
    {
        $rows = DB::table('idol_registration_request_debuts')->where('request_idx', $requestIdx)
            ->orderBy('country_code')->get(['country_code', 'debut_date'])
            ->map(fn (object $row): array => ['country_code' => $row->country_code, 'debut_date' => $row->debut_date])
            ->all();

        self::replaceForIdol($idolIdx, $rows);
    }
}
