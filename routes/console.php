<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (): void {
    if (Schema::hasTable('member_access_logs')) {
        // Detailed operational logs expire; long-term visitor aggregates remain untouched.
        DB::table('member_access_logs')
            ->where('created_at', '<', now()->subDays((int) config('tracking.access_log_retention_days', 90)))
            ->delete();
    }
})->dailyAt('03:20')->name('member-access-log-retention')->withoutOverlapping();
