<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabaseAvailability
{
    public static function isAvailable(): bool
    {
        if (! in_array(config('database.default'), \PDO::getAvailableDrivers(), true)) {
            return false;
        }

        // Test pages without a database fixture should continue to use their public demo data.
        if (app()->environment('testing')) {
            try {
                return DB::connection()->getSchemaBuilder()->hasTable('members');
            } catch (Throwable) {
                return false;
            }
        }

        return true;
    }
}
