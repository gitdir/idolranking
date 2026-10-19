<?php

namespace App\Console\Commands;

use App\Support\FanPostDistributionSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PublishQueuedFanPosts extends Command
{
    protected $signature = 'fan-posts:publish-queued';

    protected $description = '관리자 설정에 따라 예약된 팬 게시물을 공개합니다.';

    public function handle(): int
    {
        $settings = FanPostDistributionSettings::current();
        $timezone = 'Asia/Seoul';
        $publishedCount = 0;

        while ($publishedCount < $settings['max_posts']) {
            $publishedIdx = DB::transaction(function () use ($settings, $timezone): ?int {
                $nowKst = now($timezone);
                $currentTime = $nowKst->format('H:i');
                if ($currentTime < $settings['start_time'] || $currentTime >= $settings['end_time']) {
                    return null;
                }

                // Lock the oldest queued row so overlapping scheduler runs cannot publish it twice.
                $post = DB::table('fan_posts')->where('status', 'queued')->whereNull('deleted_at')
                    ->orderBy('queued_at')->orderBy('idx')->lockForUpdate()->first(['idx']);
                if ($post === null) {
                    return null;
                }

                $nowUtc = now('UTC');
                $recentCount = DB::table('fan_posts')->where('status', 'published')
                    ->whereNotNull('queued_at')
                    ->where('published_at', '>', $nowUtc->copy()->subMinutes($settings['interval_minutes']))
                    ->where('published_at', '<=', $nowUtc)->count();
                if ($recentCount >= $settings['max_posts']) {
                    return null;
                }

                DB::table('fan_posts')->where('idx', $post->idx)->where('status', 'queued')->update([
                    'status' => 'published', 'published_at' => $nowUtc, 'updated_at' => $nowUtc,
                ]);

                return (int) $post->idx;
            }, 3);

            if ($publishedIdx === null) {
                break;
            }

            $publishedCount++;
        }

        if ($publishedCount > 0) {
            $this->info("공개된 팬 게시물: {$publishedCount}건");
        }

        return self::SUCCESS;
    }
}
