<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class IdolLocalizationResolver
{
    /**
     * Resolve a list of idol/group rows in one query, keyed by the entity's internal idx.
     * Rows include legacy profile values used when no localized field is available.
     */
    public function resolveMany(iterable $idols, ?string $locale = null): array
    {
        $locale = array_key_exists($locale ?? app()->getLocale(), config('locales.public', []))
            ? ($locale ?? app()->getLocale())
            : 'en';
        $fallbacks = [];
        foreach ($idols as $idol) {
            $row = (array) $idol;
            if (!isset($row['idx'])) {
                continue;
            }
            $fallbacks[(int) $row['idx']] = [
                'name' => $row['name'] ?? null,
                'english_name' => $row['english_name'] ?? null,
                'content' => $row['content'] ?? null,
                'agency' => $row['agency'] ?? null,
                'position' => $row['position'] ?? null,
                'genre' => $row['genre'] ?? null,
                'leader' => $row['leader'] ?? null,
            ];
        }

        if ($fallbacks === []) {
            return [];
        }

        $localizations = DB::table('idol_localizations')
            ->whereIn('idol_idx', array_keys($fallbacks))
            ->whereIn('language_code', array_unique([$locale, 'en']))
            ->get(['idol_idx', 'language_code', 'localized_name', 'description', 'agency', 'position', 'genre', 'leader'])
            ->keyBy(fn (object $row): string => $row->idol_idx.':'.$row->language_code);

        $resolved = [];
        foreach ($fallbacks as $idx => $fallback) {
            $current = $localizations->get($idx.':'.$locale);
            $english = $localizations->get($idx.':en');
            $resolved[$idx] = [
                'name' => $this->firstNonEmpty($current->localized_name ?? null, $fallback['english_name'], $fallback['name']) ?? '—',
                'description' => $this->firstNonEmpty($current->description ?? null, $english->description ?? null, $fallback['content']),
                'agency' => $this->firstNonEmpty($current->agency ?? null, $english->agency ?? null, $fallback['agency']),
                'position' => $this->firstNonEmpty($current->position ?? null, $english->position ?? null, $fallback['position']),
                'genre' => $this->firstNonEmpty($current->genre ?? null, $english->genre ?? null, $fallback['genre']),
                'leader' => $this->firstNonEmpty($current->leader ?? null, $english->leader ?? null, $fallback['leader']),
            ];
        }

        return $resolved;
    }

    public function addPublicNameSearch(Builder $query, string $keyword): void
    {
        $like = '%'.$keyword.'%';

        $query->where(function (Builder $search) use ($like): void {
            $search->where('i.name', 'like', $like)
                ->orWhere('i.english_name', 'like', $like)
                ->orWhereExists(fn (Builder $localizations) => $localizations
                    ->selectRaw('1')->from('idol_localizations as il')
                    ->whereColumn('il.idol_idx', 'i.idx')->where('il.localized_name', 'like', $like))
                ->orWhereExists(fn (Builder $aliases) => $aliases
                    ->selectRaw('1')->from('idol_name_aliases as ina')
                    ->whereColumn('ina.idol_idx', 'i.idx')->where('ina.alias_name', 'like', $like));
        });
    }

    private function firstNonEmpty(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }
}
