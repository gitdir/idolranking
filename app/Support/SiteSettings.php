<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteSettings
{
    private const CACHE_KEY = 'site_settings.public.v1';

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            if (!in_array(config('database.default'), \PDO::getAvailableDrivers(), true) || !Schema::hasTable('site_settings')) {
                return [];
            }

            return DB::table('site_settings')->pluck('setting_value', 'setting_key')->all();
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
