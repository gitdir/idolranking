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

        // Optional visitor tracking can be skipped when isolated tests omit its tables.
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
