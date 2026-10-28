<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BoostFanPostViews extends Command
{
    protected $signature = 'fan-posts:boost-views';

    protected $description = 'Randomly increase the view count of one published Fan Post';

    public function handle(): int
    {
        $enabled = DB::table('site_settings')
            ->where('setting_key', 'fan_post_view_boost_enabled')
            ->value('setting_value');

        if ((string) $enabled !== '1') {
            return self::SUCCESS;
        }

        $post = DB::table('fan_posts')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('idols')
                    ->whereColumn('idols.idx', 'fan_posts.idol_idx')
                    ->where('idols.status', 'active');
            })
            ->inRandomOrder()
            ->first(['idx']);

        if ($post === null) {
            return self::SUCCESS;
        }

        DB::table('fan_posts')
            ->where('idx', $post->idx)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('idols')
                    ->whereColumn('idols.idx', 'fan_posts.idol_idx')
                    ->where('idols.status', 'active');
            })
            ->increment('view_count', random_int(1, 5));

        return self::SUCCESS;
    }
}
