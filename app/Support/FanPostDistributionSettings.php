<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class FanPostDistributionSettings
{
    public const DEFAULTS = [
        'interval_minutes' => 10,
        'max_posts' => 2,
        'start_time' => '09:00',
        'end_time' => '23:00',
    ];

    public static function current(): array
    {
        $values = DB::table('site_settings')
            ->whereIn('setting_key', [
                'fan_post_distribution_interval_minutes',
                'fan_post_distribution_max_posts',
                'fan_post_distribution_start_time',
                'fan_post_distribution_end_time',
            ])->pluck('setting_value', 'setting_key');

        $interval = filter_var($values->get('fan_post_distribution_interval_minutes'), FILTER_VALIDATE_INT);
        $maxPosts = filter_var($values->get('fan_post_distribution_max_posts'), FILTER_VALIDATE_INT);
        $start = $values->get('fan_post_distribution_start_time');
        $end = $values->get('fan_post_distribution_end_time');

        $settings = [
            'interval_minutes' => $interval !== false && $interval >= 1 && $interval <= 1440 ? $interval : self::DEFAULTS['interval_minutes'],
            'max_posts' => $maxPosts !== false && $maxPosts >= 1 && $maxPosts <= 100 ? $maxPosts : self::DEFAULTS['max_posts'],
            'start_time' => is_string($start) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start) ? $start : self::DEFAULTS['start_time'],
            'end_time' => is_string($end) && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end) ? $end : self::DEFAULTS['end_time'],
        ];

        if ($settings['start_time'] >= $settings['end_time']) {
            $settings['start_time'] = self::DEFAULTS['start_time'];
            $settings['end_time'] = self::DEFAULTS['end_time'];
        }

        return $settings;
    }
}
