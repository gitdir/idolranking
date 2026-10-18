<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Closure;

/** Shared duplicate matching for member discovery, submission, and approval. */
class IdolDuplicateMatcher
{
    /**
     * Find active records in one category whose canonical, localized, or alias name
     * exactly matches or has matching word prefixes for any supplied name.
     */
    public function matchingActive(string $type, string $gender, array $searchNames, bool $lockForUpdate = false): Collection
    {
        return $this->matchingActiveCategories([[$type, $gender]], $searchNames, $lockForUpdate);
    }

    /** Find matches across the supplied type/gender pairs with the same matching rules. */
    public function matchingActiveCategories(array $categories, array $searchNames, bool $lockForUpdate = false): Collection
    {
        $terms = collect($searchNames)
            ->map(fn ($name): array => [
                'word' => self::normalizeWords((string) $name),
                'exact' => self::normalize((string) $name),
                'similarity' => self::normalizeForSimilarity((string) $name),
            ])
            ->filter(fn (array $name): bool => $name['exact'] !== '')
            ->unique(fn (array $name): string => $name['exact'])
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        $idolQuery = DB::table('idols')
            ->where('status', 'active')
            ->where(function ($query) use ($categories): void {
                foreach ($categories as [$type, $gender]) {
                    $query->orWhere(function ($categoryQuery) use ($type, $gender): void {
                        $categoryQuery->where('type', $type)->where('gender', $gender);
                    });
                }
            })
            ->orderBy('idx');
        if ($lockForUpdate) {
            $idolQuery->lockForUpdate();
        }
        $idols = $idolQuery->get(['idx', 'type', 'gender', 'name', 'english_name', 'image_path']);

        if ($idols->isEmpty()) {
            return collect();
        }

        $ids = $idols->pluck('idx')->all();
        $namesById = [];
        foreach ($idols as $idol) {
            $namesById[$idol->idx] = array_filter([
                (string) ($idol->english_name ?? ''),
                (string) ($idol->name ?? ''),
            ], static fn (string $name): bool => trim($name) !== '');
        }

        foreach (DB::table('idol_localizations')->whereIn('idol_idx', $ids)->get(['idol_idx', 'localized_name']) as $row) {
            if (is_string($row->localized_name) && trim($row->localized_name) !== '') {
                $namesById[$row->idol_idx][] = $row->localized_name;
            }
        }

        foreach (DB::table('idol_name_aliases')->whereIn('idol_idx', $ids)->get(['idol_idx', 'alias_name']) as $row) {
            if (is_string($row->alias_name) && trim($row->alias_name) !== '') {
                $namesById[$row->idol_idx][] = $row->alias_name;
            }
        }

        $matches = [];
        foreach ($idols as $idol) {
            $entityNames = collect($namesById[$idol->idx] ?? [])
                ->map(fn (string $name): array => [
                    'word' => self::normalizeWords($name),
                    'exact' => self::normalize($name),
                    'similarity' => self::normalizeForSimilarity($name),
                ])
                ->filter(fn (array $name): bool => $name['exact'] !== '')
                ->unique(fn (array $name): string => $name['exact']);
            $matchedTerms = $terms->filter(fn (array $term): bool => $entityNames->contains(
                fn (array $candidate): bool => $this->isExact($candidate['exact'], $term['exact'])
                    || ($candidate['similarity'] !== '' && $candidate['similarity'] === $term['similarity'])
                    || $this->hasWordPrefixMatch($candidate['word'], $term['word'])
            ));

            if ($matchedTerms->isEmpty()) {
                continue;
            }

            $exact = $matchedTerms->contains(fn (array $term): bool => $entityNames->contains(
                fn (array $candidate): bool => $this->isExact($candidate['exact'], $term['exact'])
            ));
            $matches[] = (object) array_merge((array) $idol, ['exact_match' => $exact]);
        }

        return collect($matches)->sortBy([
            ['exact_match', 'desc'],
            ['english_name', 'asc'],
            ['name', 'asc'],
            ['idx', 'asc'],
        ])->values();
    }

    public static function normalize(string $value): string
    {
        $value = self::trimName($value);
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }
        $value = mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');
        $value = preg_replace('/\p{White_Space}+/u', '', $value) ?? $value;

        return $value;
    }

    /** Keep the submitted spelling intact apart from surrounding Unicode whitespace. */
    public static function trimName(string $value): string
    {
        return preg_replace('/\A\p{White_Space}+|\p{White_Space}+\z/u', '', $value) ?? trim($value);
    }

    /** Validation rule shared by member submission, admin approval, and canonical edits. */
    public static function englishNameRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (!is_string($value) || !self::isValidEnglishName($value)) {
                $fail(__('ui.idol_english_name_invalid'));
            }
        };
    }

    public static function isValidEnglishName(string $value): bool
    {
        return preg_match('/\A[A-Za-z0-9\p{Zs}().&-]+\z/u', $value) === 1;
    }

    private static function normalizeWords(string $value): string
    {
        $value = self::trimName($value);
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }

        return mb_convert_case(preg_replace('/\p{White_Space}+/u', ' ', $value) ?? $value, MB_CASE_FOLD, 'UTF-8');
    }

    private static function normalizeForSimilarity(string $value): string
    {
        $value = self::trimName($value);
        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        }
        $value = mb_convert_case($value, MB_CASE_FOLD, 'UTF-8');

        return preg_replace('/[\p{P}\p{S}\p{White_Space}]+/u', '', $value) ?? $value;
    }

    private function isExact(string $candidate, string $term): bool
    {
        return $candidate === $term;
    }

    /** Match whole words/prefixes, not arbitrary interior substrings. */
    private function hasWordPrefixMatch(string $candidate, string $term): bool
    {
        $termWords = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidateWords = preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($termWords === [] || $candidateWords === []) {
            return false;
        }

        usort($termWords, static fn (string $left, string $right): int => mb_strlen($right, 'UTF-8') <=> mb_strlen($left, 'UTF-8'));
        $unmatchedCandidateWords = $candidateWords;
        foreach ($termWords as $termWord) {
            $foundIndex = null;
            foreach ($unmatchedCandidateWords as $index => $candidateWord) {
                if (str_starts_with($candidateWord, $termWord)) {
                    $foundIndex = $index;
                    break;
                }
            }

            if ($foundIndex === null) {
                return false;
            }
            unset($unmatchedCandidateWords[$foundIndex]);
        }

        return true;
    }
}
