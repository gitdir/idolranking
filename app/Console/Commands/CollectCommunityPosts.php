<?php

namespace App\Console\Commands;

use App\Support\CommunityScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CollectCommunityPosts extends Command
{
    protected $signature = 'fan-posts:collect-communities
        {--source= : Collect only this community}
        {--now : Collect the selected community immediately}
        {--html-file= : Saved public post HTML file}
        {--post-url= : Original public post URL for saved HTML}';

    protected $description = 'Collect at most one permitted media Fan Post per community per Seoul day';

    public function handle(CommunityScraper $scraper): int
    {
        $immediate = (bool) $this->option('now');
        $htmlFile = $this->option('html-file');
        $postUrl = $this->option('post-url');
        $sourceIdx = $this->option('source');
        if ($immediate && (! is_string($sourceIdx) || ! ctype_digit($sourceIdx))) {
            $this->error('즉시 수집에는 커뮤니티 ID가 필요합니다.');
            return self::FAILURE;
        }
        if (($htmlFile !== null || $postUrl !== null) && (! $immediate || ! is_string($htmlFile) || ! is_string($postUrl))) {
            $this->error('저장한 HTML을 가져오려면 커뮤니티, 게시물 URL, 파일을 모두 지정해 주세요.');
            return self::FAILURE;
        }
        if (! $immediate && DB::table('site_settings')->where('setting_key', 'scraping_enabled')->value('setting_value') !== '1') {
            return self::SUCCESS;
        }
        $today = now('Asia/Seoul')->startOfDay();
        $sources = DB::table('scrape_sources')->where('is_enabled', 1)->whereNull('deleted_at')
            ->when($sourceIdx !== null, fn ($query) => $query->where('idx', $sourceIdx))
            ->orderBy('idx')->get();
        foreach ($sources as $source) {
            try {
                foreach ($immediate ? [0] : [0, 1] as $offset) {
                    $date = $today->copy()->addDays($offset);
                    DB::table('scrape_days')->insertOrIgnore([
                        'source_idx' => $source->idx, 'collect_date' => $date->toDateString(),
                        'scheduled_at' => $date->copy()->addMinutes(random_int(0, 1439))->utc(),
                        'status' => 'pending',
                    ]);
                }
                $day = DB::table('scrape_days')->where('source_idx', $source->idx)
                    ->where('collect_date', $today->toDateString())->first();
                if ($day->status === 'imported' || (! $immediate && \Illuminate\Support\Carbon::parse($day->scheduled_at, 'UTC')->isFuture())) continue;

                // Both Scheduler and admin requests claim the same row before network access.
                $claimed = DB::table('scrape_days')->where('idx', $day->idx)
                    ->where(function ($query) use ($immediate): void {
                        $query->whereIn('status', $immediate ? ['pending', 'skipped', 'failed'] : ['pending'])
                            ->orWhere(fn ($stale) => $stale->where('status', 'processing')
                                ->where('finished_at', '<', now('UTC')->subMinutes(15)));
                    })
                    ->update(['status' => 'processing', 'finished_at' => now('UTC'), 'failure_reason' => null]);
                if (! $claimed) continue;

                if ($htmlFile !== null && ! is_readable($htmlFile)) {
                    throw new \RuntimeException('저장한 HTML 파일을 읽을 수 없습니다.');
                }
                $html = $htmlFile === null ? null : file_get_contents($htmlFile);
                if ($htmlFile !== null && ! is_string($html)) {
                    throw new \RuntimeException('저장한 HTML 파일을 읽을 수 없습니다.');
                }
                $result = $html === null ? $scraper->preview($source)
                    : $scraper->previewSavedPost($source, $postUrl, $html);
                $post = $result['eligible'];
                if ($post === null) {
                    $reason = $result['reason'] ?? '최근 후보 10건에 가져올 수 있는 미디어 게시물이 없습니다.';
                    $updated = DB::table('scrape_days')->where('idx', $day->idx)->where('status', 'processing')
                        ->update(['status' => 'skipped', 'finished_at' => now('UTC'), 'failure_reason' => $reason]);
                    if ($updated) $this->recordFailure($source, $today->toDateString(), 'skipped', $reason);
                    continue;
                }
                $scraper->assertImportAllowed($source);
                $imagePath = null;
                if ($post['media_type'] === 'image') {
                    $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'][$post['mime']];
                    $stored = 'fan-posts/'.Str::random(40).'.'.$extension;
                    if (! Storage::disk('public')->put($stored, $post['bytes'])) {
                        Storage::disk('public')->delete($stored);
                        throw new \RuntimeException('이미지를 저장하지 못했습니다.');
                    }
                    $imagePath = 'storage/'.$stored;
                }
                $created = false;
                try {
                    $created = DB::transaction(function () use ($source, $day, $post, $scraper, $imagePath): bool {
                        $locked = DB::table('scrape_days')->where('idx', $day->idx)->lockForUpdate()->first();
                        if ($locked->status !== 'processing') return false;
                        $identifier = $scraper->identifier($post['url']);
                        if (DB::table('scrape_imports')->where('normalized_identifier', $identifier)
                            ->where('status', 'imported')->exists()) return false;
                        $member = DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                            ->where('status', 'active')->whereNull('deleted_at')->inRandomOrder()->first(['idx']);
                        if ($member === null) throw new \RuntimeException('활성 더미 회원이 없습니다.');
                        $now = now('UTC');
                        $postIdx = DB::table('fan_posts')->insertGetId([
                            'idol_idx' => null, 'member_idx' => $member->idx, 'actor_member_idx' => $member->idx,
                            'title' => '[운영 편집] '.$post['title'], 'content' => $post['content'],
                            'language_code' => null, 'media_type' => $post['media_type'],
                            'image_path' => $imagePath, 'youtube_video_id' => $post['youtube_video_id'] ?? null,
                            'status' => 'published', 'published_at' => $now, 'view_count' => 0,
                            'created_at' => $now, 'updated_at' => $now,
                        ]);
                        DB::table('scrape_imports')->insert([
                            'source_idx' => $source->idx, 'original_url' => $post['url'],
                            'normalized_identifier' => $identifier, 'collected_at' => $now,
                            'status' => 'imported', 'fan_post_idx' => $postIdx,
                            'dummy_member_idx' => $member->idx,
                        ]);
                        DB::table('scrape_days')->where('idx', $day->idx)->update([
                            'status' => 'imported', 'fan_post_idx' => $postIdx, 'finished_at' => $now,
                        ]);
                        return true;
                    }, 3);
                } finally {
                    if (! ($created ?? false) && $imagePath !== null) {
                        Storage::disk('public')->delete(substr($imagePath, strlen('storage/')));
                    }
                }
                if (! $created) {
                    $reason = '이미 수집된 게시물입니다.';
                    $updated = DB::table('scrape_days')->where('idx', $day->idx)->where('status', 'processing')
                        ->update(['status' => 'skipped', 'finished_at' => now('UTC'), 'failure_reason' => $reason]);
                    if ($updated) $this->recordFailure($source, $today->toDateString(), 'skipped', $reason);
                }
            } catch (\Throwable $exception) {
                Log::warning('Community collection failed.', ['source_idx' => $source->idx, 'error' => $exception->getMessage()]);
                $reason = $exception instanceof \RuntimeException
                    ? mb_substr($exception->getMessage(), 0, 500)
                    : '커뮤니티에 연결하거나 게시물을 처리하지 못했습니다. 관리자 로그를 확인해 주세요.';
                $updated = DB::table('scrape_days')->where('source_idx', $source->idx)
                    ->where('collect_date', $today->toDateString())->where('status', 'processing')
                    ->update(['status' => 'failed', 'finished_at' => now('UTC'), 'failure_reason' => $reason]);
                if ($updated) $this->recordFailure($source, $today->toDateString(), 'failed', $reason);
            }
        }
        return self::SUCCESS;
    }

    private function recordFailure(object $source, string $date, string $status, string $reason): void
    {
        DB::table('scrape_imports')->updateOrInsert([
            'normalized_identifier' => hash('sha256', 'collection:'.$source->idx.':'.$date),
        ], [
            'source_idx' => $source->idx, 'original_url' => $source->listing_url,
            'collected_at' => now('UTC'), 'status' => $status, 'failure_reason' => $reason,
        ]);
    }
}
