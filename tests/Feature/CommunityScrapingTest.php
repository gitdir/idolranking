<?php

namespace Tests\Feature;

use App\Support\CommunityScraper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CommunityScrapingTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) $this->markTestSkipped('pdo_sqlite is required.');
        DB::statement('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
        DB::statement('CREATE TABLE scrape_sources (idx INTEGER PRIMARY KEY, name TEXT, listing_url TEXT, collection_method TEXT DEFAULT "http", post_link_selector TEXT, title_selector TEXT, content_selector TEXT, image_selector TEXT, video_selector TEXT, is_enabled INTEGER, redistribution_permitted INTEGER, attribution_required INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE scrape_days (idx INTEGER PRIMARY KEY AUTOINCREMENT, source_idx INTEGER, collect_date TEXT, scheduled_at TEXT, finished_at TEXT, fan_post_idx INTEGER, status TEXT, failure_reason TEXT, UNIQUE(source_idx, collect_date))');
        DB::statement('CREATE TABLE scrape_imports (idx INTEGER PRIMARY KEY AUTOINCREMENT, source_idx INTEGER, original_url TEXT, normalized_identifier TEXT UNIQUE, collected_at TEXT, status TEXT, fan_post_idx INTEGER, dummy_member_idx INTEGER, failure_reason TEXT)');
        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER, is_dummy INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, title TEXT, content TEXT, language_code TEXT, media_type TEXT, image_path TEXT, youtube_video_id TEXT, status TEXT, published_at TEXT, view_count INTEGER, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
        DB::table('site_settings')->insert(['setting_key' => 'scraping_enabled', 'setting_value' => '1']);
        DB::table('members')->insert(['idx' => 7, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active']);
        DB::table('scrape_sources')->insert([
            'idx' => 1, 'name' => '테스트', 'listing_url' => 'https://example.com/board',
            'post_link_selector' => 'a.post', 'title_selector' => 'h1', 'content_selector' => 'article',
            'image_selector' => 'article img', 'video_selector' => 'article a.video',
            'is_enabled' => 1, 'redistribution_permitted' => 1, 'attribution_required' => 0,
        ]);
    }

    private function fakeScraper(): CommunityScraper
    {
        return new class extends CommunityScraper {
            protected function get(string $url, int $limit): string
            {
                if (str_ends_with($url, '/board')) {
                    return '<a class="post notice" href="/notice">공지</a><a class="post" href="/text">텍스트</a><a class="post" href="/video">영상</a>';
                }
                if (str_ends_with($url, '/text')) return '<h1>텍스트 글</h1><article><p>내용만 있습니다.</p></article>';
                if (str_ends_with($url, '/video')) return '<h1>영상 글</h1><article><p>영상 설명</p><a class="video" href="https://www.youtube.com/shorts/AbCdEfGhI12">영상</a></article>';
                throw new \RuntimeException('접근 실패');
            }
        };
    }

    public function test_media_only_import_and_daily_and_deleted_post_deduplication(): void
    {
        $this->database();
        $scraper = $this->fakeScraper();
        $this->app->instance(CommunityScraper::class, $scraper);
        $this->travelTo(Carbon::parse('2026-11-30 23:59:00', 'Asia/Seoul'));

        $preview = $scraper->preview(DB::table('scrape_sources')->first());
        $this->assertCount(2, $preview['candidates']);
        $this->assertSame('youtube', $preview['eligible']['media_type']);
        $this->assertDatabaseCount('fan_posts', 0);
        $this->assertDatabaseCount('scrape_imports', 0);
        $this->assertDatabaseCount('scrape_days', 0);

        Artisan::call('fan-posts:collect-communities');
        Artisan::call('fan-posts:collect-communities');
        $this->assertDatabaseCount('fan_posts', 1);
        $post = DB::table('fan_posts')->first();
        $this->assertNull($post->idol_idx);
        $this->assertSame('published', $post->status);
        $this->assertSame('youtube', $post->media_type);
        $this->assertSame('AbCdEfGhI12', $post->youtube_video_id);
        $this->assertSame(7, (int) $post->member_idx);
        $this->assertSame(1, DB::table('scrape_imports')->where('status', 'imported')->count());

        DB::table('fan_posts')->where('idx', $post->idx)->update(['deleted_at' => now()]);
        DB::table('scrape_sources')->where('idx', 1)->update(['deleted_at' => now(), 'is_enabled' => 0]);
        $copy = (array) DB::table('scrape_sources')->first();
        $copy['idx'] = 2;
        $copy['deleted_at'] = null;
        $copy['is_enabled'] = 1;
        DB::table('scrape_sources')->insert($copy);
        $this->travelTo(Carbon::parse('2026-12-01 23:59:00', 'Asia/Seoul'));
        Artisan::call('fan-posts:collect-communities');
        $this->assertDatabaseCount('fan_posts', 1);
        $this->assertSame(1, DB::table('scrape_imports')->where('status', 'imported')->count());
    }

    public function test_valid_image_is_stored_and_daily_schedule_is_persisted(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('scrape_sources')->where('idx', 1)->update(['video_selector' => null]);
        $image = imagecreatetruecolor(300, 300);
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $scraper = new class($bytes) extends CommunityScraper {
            public function __construct(private string $imageBytes) {}
            protected function get(string $url, int $limit): string
            {
                if (str_ends_with($url, '/board')) return '<a class="post" href="/image">사진 글</a>';
                if (str_ends_with($url, '/image')) return '<h1>사진 글</h1><article><script>unsafe()</script><p>안전한 본문</p><img class="logo" src="/logo.png"><img src="/photo.png"></article>';
                if (str_ends_with($url, '/photo.png')) return $this->imageBytes;
                throw new \RuntimeException('접근 실패');
            }
        };
        $this->app->instance(CommunityScraper::class, $scraper);
        $this->travelTo(Carbon::parse('2026-11-30 23:59:00', 'Asia/Seoul'));

        Artisan::call('fan-posts:collect-communities');
        $post = DB::table('fan_posts')->first();
        $this->assertSame('image', $post->media_type);
        $this->assertSame('안전한 본문', $post->content);
        Storage::disk('public')->assertExists(substr($post->image_path, strlen('storage/')));
        $tomorrow = DB::table('scrape_days')->where('collect_date', '2026-12-01')->value('scheduled_at');
        Artisan::call('fan-posts:collect-communities');
        $this->assertSame($tomorrow, DB::table('scrape_days')->where('collect_date', '2026-12-01')->value('scheduled_at'));
        $this->assertDatabaseCount('fan_posts', 1);
    }

    public function test_private_and_non_http_addresses_are_rejected(): void
    {
        foreach (['http://127.0.0.1/private', 'http://169.254.169.254/latest', 'file:///etc/passwd'] as $url) {
            try {
                (new CommunityScraper())->assertPublicUrl($url);
                $this->fail('Expected the address to be rejected: '.$url);
            } catch (\RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_failed_source_does_not_stop_another_source(): void
    {
        $this->database();
        DB::table('scrape_sources')->where('idx', 1)->update(['listing_url' => 'https://example.com/broken']);
        $copy = (array) DB::table('scrape_sources')->first();
        $copy['idx'] = 2;
        $copy['listing_url'] = 'https://example.com/board';
        DB::table('scrape_sources')->insert($copy);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $this->travelTo(Carbon::parse('2026-11-30 23:59:00', 'Asia/Seoul'));

        Artisan::call('fan-posts:collect-communities');
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'status' => 'failed']);
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 2, 'status' => 'imported']);
        $this->assertDatabaseCount('fan_posts', 1);
        $this->assertDatabaseHas('scrape_imports', ['source_idx' => 1, 'status' => 'failed']);
    }

    public function test_collection_waits_for_persisted_time(): void
    {
        $this->database();
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));
        DB::table('scrape_days')->insert([
            'source_idx' => 1, 'collect_date' => '2026-11-30',
            'scheduled_at' => '2026-11-30 14:59:00', 'status' => 'pending',
        ]);

        Artisan::call('fan-posts:collect-communities');
        $this->assertDatabaseCount('fan_posts', 0);
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'collect_date' => '2026-11-30', 'status' => 'pending']);
    }

    public function test_manual_collection_ignores_global_switch_and_schedule_but_respects_daily_limit(): void
    {
        $this->database();
        DB::table('site_settings')->where('setting_key', 'scraping_enabled')->update(['setting_value' => '0']);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));
        DB::table('scrape_days')->insert([
            'source_idx' => 1, 'collect_date' => '2026-11-30',
            'scheduled_at' => '2026-11-30 14:59:00', 'status' => 'pending',
        ]);

        Artisan::call('fan-posts:collect-communities', ['--source' => '1', '--now' => true]);
        Artisan::call('fan-posts:collect-communities', ['--source' => '1', '--now' => true]);
        $this->assertDatabaseCount('fan_posts', 1);
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'collect_date' => '2026-11-30', 'status' => 'imported']);
        $this->assertSame(1, DB::table('scrape_imports')->where('status', 'imported')->count());
    }

    public function test_manual_collection_does_not_take_an_active_schedule_claim(): void
    {
        $this->database();
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $this->travelTo(Carbon::parse('2026-11-30 23:59:00', 'Asia/Seoul'));
        DB::table('scrape_days')->insert([
            'source_idx' => 1, 'collect_date' => '2026-11-30',
            'scheduled_at' => '2026-11-30 14:58:00', 'status' => 'processing',
            'finished_at' => now('UTC'),
        ]);

        Artisan::call('fan-posts:collect-communities', ['--source' => '1', '--now' => true]);
        $this->assertDatabaseCount('fan_posts', 0);
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'status' => 'processing']);
    }

    public function test_only_admin_can_use_collection_endpoint_and_daily_message_is_returned(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        DB::table('site_settings')->where('setting_key', 'scraping_enabled')->update(['setting_value' => '0']);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));

        $this->withSession(['member_idx' => 7])->post(route('admin.scraping.sources.collect', 1))->assertForbidden();
        $this->withSession(['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false])->post(route('admin.scraping.sources.collect', 1))
            ->assertRedirect()->assertSessionHas('admin_success', '팬포스트 1건을 수집해 바로 등록했습니다.');
        $this->withSession(['member_idx' => 1])->post(route('admin.scraping.sources.collect', 1))
            ->assertRedirect()->assertSessionHas('admin_success', '오늘 수집이 이미 완료되었습니다.');
        $this->assertDatabaseCount('fan_posts', 1);
    }

    public function test_form_cannot_grant_redistribution_permission(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        DB::table('scrape_sources')->where('idx', 1)->update(['redistribution_permitted' => 0, 'attribution_required' => 1, 'is_enabled' => 0]);
        $form = [
            'name' => 'Test', 'listing_url' => 'https://example.com/board',
            'post_link_selector' => 'a.post', 'title_selector' => 'h1', 'content_selector' => 'article',
            'image_selector' => 'article img', 'video_selector' => '', 'is_enabled' => '1',
            'redistribution_permitted' => '1', 'attribution_required' => '0',
        ];
        $this->withSession(['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false])
            ->put(route('admin.scraping.sources.update', 1), $form)->assertRedirect();
        $this->assertDatabaseHas('scrape_sources', ['idx' => 1, 'is_enabled' => 1, 'redistribution_permitted' => 0, 'attribution_required' => 1]);
    }

    public function test_selector_validation_accepts_css_syntax_and_reports_its_field(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        $form = [
            '_scraping_source_idx' => '1', 'name' => 'Test', 'listing_url' => 'https://example.com/board',
            'post_link_selector' => 'article:not(.notice) > a[href], .posts > a.item',
            'title_selector' => 'h1.title', 'content_selector' => 'article.body',
            'image_selector' => 'article > img[src]', 'video_selector' => '', 'is_enabled' => '0',
        ];
        $session = ['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false];
        $this->withSession($session)->put(route('admin.scraping.sources.update', 1), $form)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scrape_sources', ['idx' => 1, 'post_link_selector' => $form['post_link_selector']]);

        $form['post_link_selector'] = 'a[';
        $this->withSession($session)->put(route('admin.scraping.sources.update', 1), $form)
            ->assertSessionHasErrors('post_link_selector')->assertSessionDoesntHaveErrors('listing_url');
    }

    public function test_community_url_is_saved_without_connectivity_check(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        $form = [
            'name' => 'Offline board', 'listing_url' => 'https://unreachable.invalid/board',
            'post_link_selector' => 'a.post', 'title_selector' => 'h1', 'content_selector' => 'article',
            'image_selector' => 'article img', 'video_selector' => '', 'is_enabled' => '1',
        ];
        $session = ['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false];
        $this->withSession($session)->post(route('admin.scraping.sources.store'), $form)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scrape_sources', ['listing_url' => $form['listing_url'], 'is_enabled' => 1, 'redistribution_permitted' => 0, 'attribution_required' => 1]);

        $form['listing_url'] = 'http://another-unreachable.invalid/board';
        $this->withSession($session)->put(route('admin.scraping.sources.update', 1), $form)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('scrape_sources', ['idx' => 1, 'listing_url' => $form['listing_url']]);

        $form['listing_url'] = 'file:///etc/passwd';
        $this->withSession($session)->put(route('admin.scraping.sources.update', 1), $form)->assertSessionHasErrors('listing_url');
    }

    public function test_collection_test_uses_session_admin_and_remains_read_only(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());

        $this->withSession(['member_idx' => 7])->post(route('admin.scraping.sources.test', 1))->assertForbidden();
        $this->withSession(['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false])
            ->post(route('admin.scraping.sources.test', 1))->assertRedirect()->assertSessionHas('scraping_diagnostic');
        $this->assertDatabaseCount('fan_posts', 0);
        $this->assertDatabaseCount('scrape_imports', 0);
    }

    public function test_unverified_source_can_be_tested_but_cannot_publish_manually_or_on_schedule(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        DB::table('scrape_sources')->where('idx', 1)->update(['redistribution_permitted' => 0, 'attribution_required' => 1, 'is_enabled' => 0]);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $session = ['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false];
        $this->withSession($session)->post(route('admin.scraping.sources.test', 1))->assertSessionHas('scraping_diagnostic');
        $this->assertDatabaseCount('fan_posts', 0);

        DB::table('scrape_sources')->where('idx', 1)->update(['is_enabled' => 1]);
        DB::table('site_settings')->where('setting_key', 'scraping_enabled')->update(['setting_value' => '0']);
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));
        $this->withSession($session)->post(route('admin.scraping.sources.collect', 1))
            ->assertSessionHasErrors('scraping');
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'status' => 'failed', 'failure_reason' => '이 커뮤니티의 콘텐츠 재배포 허용 여부가 확인되지 않았습니다.']);
        $this->assertDatabaseCount('fan_posts', 0);

        DB::table('site_settings')->where('setting_key', 'scraping_enabled')->update(['setting_value' => '1']);
        $this->travelTo(Carbon::parse('2026-12-01 23:59:00', 'Asia/Seoul'));
        Artisan::call('fan-posts:collect-communities');
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'collect_date' => '2026-12-01', 'status' => 'failed']);
        $this->assertDatabaseCount('fan_posts', 0);
    }

    public function test_diagnostic_resolves_relative_and_protocol_relative_media_urls(): void
    {
        $this->database();
        $scraper = new class extends CommunityScraper {
            protected function get(string $url, int $limit): string
            {
                if (str_ends_with($url, '/board')) return '<title>Board</title><a class="post" href="/post/1">Post</a>';
                return '<h1>Title</h1><article><img src="/images/a.jpg"><img src="//cdn.example.com/b.jpg"><a class="video" href="//www.youtube.com/watch?v=AbCdEfGhI12">Video</a></article>';
            }
        };
        $diagnostic = $scraper->diagnose(DB::table('scrape_sources')->first());
        $this->assertSame(['https://example.com/post/1'], $diagnostic['candidates']);
        $this->assertSame(['https://example.com/images/a.jpg', 'https://cdn.example.com/b.jpg'], $diagnostic['details'][0]['image_urls']);
        $this->assertSame(['https://www.youtube.com/watch?v=AbCdEfGhI12'], $diagnostic['details'][0]['video_urls']);
        $this->assertDatabaseCount('fan_posts', 0);
    }

    public function test_ajax_feedback_returns_diagnostics_without_redirecting(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        $this->app->instance(CommunityScraper::class, $this->fakeScraper());
        $session = ['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false];
        $this->withSession($session)->postJson(route('admin.scraping.sources.test', 1))
            ->assertOk()->assertJsonPath('diagnostic.board_url', 'https://example.com/board');
        $this->assertDatabaseCount('fan_posts', 0);

        DB::table('scrape_sources')->where('idx', 1)->update(['is_enabled' => 0]);
        $this->withSession($session)->postJson(route('admin.scraping.sources.collect', 1))
            ->assertStatus(422)->assertJsonPath('status', 'error');
    }

    public function test_saved_html_uses_existing_daily_import_pipeline(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active']);
        DB::table('site_settings')->where('setting_key', 'scraping_enabled')->update(['setting_value' => '0']);
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));
        $html = '<h1>Saved post</h1><article><p>Content</p><a class="video" href="https://www.youtube.com/shorts/AbCdEfGhI12">Video</a></article>';
        $session = ['member_idx' => 1, 'acting_dummy_member_actor_idx' => 1, 'acting_dummy_member_enabled' => false];
        $form = ['post_url' => 'https://example.com/post/1', 'html_file' => UploadedFile::fake()->createWithContent('post.html', $html)];
        $this->withSession($session)->post(route('admin.scraping.sources.import-html', 1), $form)
            ->assertRedirect()->assertSessionHas('admin_success');
        $this->assertDatabaseHas('fan_posts', ['title' => '[운영 편집] Saved post', 'idol_idx' => null, 'status' => 'published']);
        $this->assertDatabaseHas('scrape_days', ['source_idx' => 1, 'status' => 'imported']);
        $this->assertDatabaseCount('scrape_imports', 1);

        $form['html_file'] = UploadedFile::fake()->createWithContent('post.html', $html);
        $this->withSession($session)->post(route('admin.scraping.sources.import-html', 1), $form)
            ->assertSessionHas('admin_success', '오늘 수집이 이미 완료되었습니다.');
        $this->assertDatabaseCount('fan_posts', 1);
    }

    public function test_browser_method_uses_rendered_html_with_existing_selectors(): void
    {
        $this->database();
        DB::table('scrape_sources')->where('idx', 1)->update(['collection_method' => 'browser']);
        $scraper = new class extends CommunityScraper {
            protected function getPage(object $source, string $url): string
            {
                if (str_ends_with($url, '/board')) return '<a class="post" href="/post/1">Post</a>';
                return '<h1>Rendered title</h1><article><a class="video" href="https://youtu.be/AbCdEfGhI12">Video</a></article>';
            }
        };
        $result = $scraper->preview(DB::table('scrape_sources')->first());
        $this->assertSame(['https://example.com/post/1'], $result['candidates']);
        $this->assertSame('Rendered title', $result['eligible']['title']);
        $this->assertSame('youtube', $result['eligible']['media_type']);
        $this->assertDatabaseCount('fan_posts', 0);
    }
}
