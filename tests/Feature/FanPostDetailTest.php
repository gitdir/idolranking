<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FanPostDetailTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT UNIQUE, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, password_hash TEXT, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, english_name TEXT, agency TEXT, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, gender TEXT DEFAULT 'male', type TEXT DEFAULT 'individual', status TEXT DEFAULT 'active', view_count INTEGER DEFAULT 0)");
        DB::statement("CREATE TABLE idol_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, country_code))");
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))');
        DB::statement('CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, request_key TEXT, created_at TEXT)');
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, title TEXT, media_type TEXT DEFAULT 'text', language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT DEFAULT 'published', deleted_at TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement('CREATE TABLE fan_post_tags (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, tag TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, created_at TEXT, PRIMARY KEY (fan_post_idx, member_idx))');
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, request_key TEXT, content TEXT, language_code TEXT, status TEXT DEFAULT 'published', deleted_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE (actor_member_idx, request_key))");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
        DB::statement("CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, min_heart_amount INTEGER NULL, max_heart_amount INTEGER NULL, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE fan_post_reports (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reason TEXT, details TEXT, status TEXT DEFAULT 'pending', created_at TEXT, updated_at TEXT, UNIQUE (fan_post_idx, member_idx))");
        DB::statement("CREATE TABLE fan_post_comment_reports (idx INTEGER PRIMARY KEY AUTOINCREMENT, comment_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reason TEXT, details TEXT, status TEXT DEFAULT 'pending', created_at TEXT, updated_at TEXT, UNIQUE (comment_idx, member_idx))");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE admin_dummy_activity_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, member_idx INTEGER, action_code TEXT, request_key TEXT, payload_hash TEXT, result_type TEXT, result_idx INTEGER, created_at TEXT, completed_at TEXT, updated_at TEXT, UNIQUE(actor_member_idx, action_code, request_key))");
        DB::statement("CREATE TABLE fan_post_translations (idx INTEGER PRIMARY KEY AUTOINCREMENT, fan_post_idx INTEGER, language_code TEXT, translated_title TEXT NOT NULL, translated_content TEXT, source_hash TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE (fan_post_idx, language_code))");
        DB::statement("CREATE TABLE fan_post_comment_translations (idx INTEGER PRIMARY KEY AUTOINCREMENT, comment_idx INTEGER, language_code TEXT, translated_content TEXT NOT NULL, source_hash TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE (comment_idx, language_code))");
        DB::table('members')->insert([
            'idx' => 1, 'member_code' => 'memberCodeA1', 'email' => 'member@example.test', 'nickname' => 'member_one', 'country_code' => 'KR',
            'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('idols')->insert(['idx' => 1, 'name' => 'Demo Idol', 'status' => 'active']);
        DB::table('heart_reward_settings')->insert([
            ['action_code' => 'fan_post_create', 'heart_amount' => 30, 'daily_limit' => 20, 'is_enabled' => 1],
            ['action_code' => 'comment_create', 'heart_amount' => 10, 'daily_limit' => 20, 'is_enabled' => 1],
            ['action_code' => 'fan_post_recommend_received', 'heart_amount' => 1, 'daily_limit' => 100, 'is_enabled' => 1],
        ]);
    }

    private function createTranslatableComment(string $content = 'Original comment', ?string $language = 'en'): array
    {
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Public post',
            'language_code' => 'en', 'content' => 'Post body', 'status' => 'published',
        ]);
        $commentIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => $postIdx, 'member_idx' => 1, 'actor_member_idx' => 1,
            'content' => $content, 'language_code' => $language, 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$postIdx, $commentIdx];
    }

    private function insertOtherMember(int $idx = 2): void
    {
        DB::table('members')->insert([
            'idx' => $idx, 'member_code' => 'memberCode'.str_pad((string) $idx, 4, '0', STR_PAD_LEFT),
            'email' => 'member'.$idx.'@example.test', 'nickname' => 'member_'.$idx,
        ]);
    }

    public function test_random_dummy_fan_post_reward_is_atomic_attributed_and_idempotent(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode02', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 3, 'member_code' => 'dummyCode03', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 5, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null],
        ]);
        $payload = [
            'request_key' => str_repeat('a', 64), 'idol_idx' => 1, 'title' => 'Random mode post',
            'content' => 'A media post created in random mode.', 'language_code' => 'en', 'media_type' => 'image',
            'image' => UploadedFile::fake()->image('post.png'), 'tags' => 'test',
        ];

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->post(route('fan-post.store'), $payload)->assertRedirect();
        $post = DB::table('fan_posts')->first();
        $this->assertSame(3, (int) $post->member_idx);
        $this->assertSame(2, (int) $post->actor_member_idx);
        $this->assertSame('ko', $post->language_code);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => 30,
            'operation_key' => 'fan_post_reward:'.$post->idx,
        ]);
        $this->assertSame(35, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));

        $this->post(route('fan-post.store'), $payload)->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $this->assertSame(1, DB::table('fan_posts')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'fan_post_reward:'.$post->idx)->count());
        $this->assertSame(35, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
    }

    public function test_random_dummy_comment_uses_korean_and_gets_only_one_eligible_reward(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode02', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 3, 'member_code' => 'dummyCode03', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 5, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null],
        ]);
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Public post',
            'language_code' => 'en', 'content' => 'Post body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['request_key' => 'random-comment-action-key-01', 'content' => 'A useful comment.', 'language_code' => 'en'];

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson(route('fan-post.comments.store', ['idx' => $postIdx]), $payload)
            ->assertCreated()->assertJsonPath('data.reward_recorded', true)->assertJsonPath('data.rewarded_hearts', 10);
        $comment = DB::table('fan_post_comments')->first();
        $this->assertSame(3, (int) $comment->member_idx);
        $this->assertSame(2, (int) $comment->actor_member_idx);
        $this->assertSame('ko', $comment->language_code);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 2, 'amount' => 10,
            'operation_key' => 'comment_reward:'.$comment->idx,
        ]);

        $this->postJson(route('fan-post.comments.store', ['idx' => $postIdx]), $payload)
            ->assertOk()->assertJsonPath('data.reward_recorded', true)->assertJsonPath('data.rewarded_hearts', 10);
        $this->assertSame(1, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'comment_reward:'.$comment->idx)->count());
    }

    public function test_random_dummy_recommendation_is_attributed_and_keeps_per_member_duplicate_protection(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode02', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 3, 'member_code' => 'dummyCode03', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 5, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null],
        ]);
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Recommendation target',
            'language_code' => 'en', 'content' => 'Post body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 2, \App\Support\RandomDummyActivity::SESSION_KEY => true])
            ->postJson(route('fan-post.recommendation', ['idx' => $postIdx]), ['request_key' => str_repeat('b', 64)])
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertDatabaseHas('fan_post_likes', [
            'fan_post_idx' => $postIdx, 'member_idx' => 3, 'actor_member_idx' => 2,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => 1,
            'operation_key' => 'fan_post_recommend_received:'.$postIdx.':3',
        ]);

        $this->postJson(route('fan-post.recommendation', ['idx' => $postIdx]), ['request_key' => str_repeat('b', 64)])
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('admin_dummy_activity_requests')->where('action_code', 'fan_post_recommend')->count());
        $this->assertSame(1, DB::table('fan_post_likes')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());

        $this->postJson(route('fan-post.recommendation', ['idx' => $postIdx]), ['request_key' => str_repeat('c', 64)])
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());
        $this->assertSame(2, DB::table('admin_dummy_activity_requests')->where('action_code', 'fan_post_recommend')->count());
    }

    public function test_detail_main_image_opens_only_an_available_original_image_in_a_new_tab(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('fan-posts/full-resolution.png', 'image-bytes');
        $withImageIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Image post',
            'media_type' => 'image', 'image_path' => 'storage/fan-posts/full-resolution.png',
            'content' => 'Full size upload', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('fan-post.show', ['idx' => $withImageIdx]))->assertOk()
            ->assertSee('class="fan-detail-image-link" href="'.e(asset('storage/fan-posts/full-resolution.png')).'" target="_blank" rel="noopener noreferrer"', false);

        $withoutImageIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Missing image post',
            'media_type' => 'image', 'image_path' => 'storage/fan-posts/missing.png',
            'content' => 'Missing upload', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get(route('fan-post.show', ['idx' => $withoutImageIdx]))->assertOk()
            ->assertDontSee('class="fan-detail-image-link"', false);
    }

    public function test_comment_translation_uses_valid_cache_without_calling_google(): void
    {
        $this->database();
        Http::fake();
        [$postIdx, $commentIdx] = $this->createTranslatableComment("  Original\ncomment  ");
        DB::table('fan_post_comment_translations')->insert([
            'comment_idx' => $commentIdx, 'language_code' => 'ko', 'translated_content' => '캐시??번역',
            'source_hash' => hash('sha256', "  Original\ncomment  "), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.content', '캐시??번역');
        Http::assertNothingSent();
    }

    public function test_comment_translation_returns_original_when_source_matches_target(): void
    {
        $this->database();
        Http::fake();
        [$postIdx, $commentIdx] = $this->createTranslatableComment("  Original\ntext  ", 'ko');

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.content', "  Original\ntext  ");
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('fan_post_comment_translations')->count());
    }

    public function test_comment_translation_generates_and_caches_plain_text_using_exact_source_hash(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        $source = "  Original\ncomment  ";
        [$postIdx, $commentIdx] = $this->createTranslatableComment($source);
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'data' => ['translations' => [['translatedText' => '<b>Translated</b>']]],
        ])]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.content', '<b>Translated</b>');

        Http::assertSent(fn ($request) => $request['q'] === [$source]
            && $request['source'] === 'en' && $request['target'] === 'ko' && $request['format'] === 'text');
        $this->assertDatabaseHas('fan_post_comment_translations', [
            'comment_idx' => $commentIdx,
            'language_code' => 'ko',
            'translated_content' => '<b>Translated</b>',
            'source_hash' => hash('sha256', $source),
        ]);
    }

    public function test_comment_translation_ignores_stale_cache_and_does_not_save_after_source_edit(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        [$postIdx, $commentIdx] = $this->createTranslatableComment('Before edit');
        DB::table('fan_post_comment_translations')->insert([
            'comment_idx' => $commentIdx, 'language_code' => 'ko', 'translated_content' => 'Stale translation',
            'source_hash' => hash('sha256', 'Old source'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        Http::fake(function () use ($commentIdx) {
            DB::table('fan_post_comments')->where('idx', $commentIdx)->update(['content' => 'Edited during request']);

            return Http::response(['data' => ['translations' => [['translatedText' => 'New translation']]]]);
        });

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        $this->assertSame('Stale translation', DB::table('fan_post_comment_translations')->where('comment_idx', $commentIdx)->value('translated_content'));
    }

    public function test_comment_translation_handles_api_failure_and_missing_key_without_saving(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        [$postIdx, $commentIdx] = $this->createTranslatableComment();
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response(['error' => ['code' => 403]], 403)]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        $this->assertSame(0, DB::table('fan_post_comment_translations')->count());

        Config::set('services.google_translate.api_key', null);
        Http::fake();
        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        Http::assertNothingSent();
    }

    public function test_comment_translation_rejects_unknown_language_invalid_target_and_hidden_comments(): void
    {
        $this->database();
        Http::fake();
        [$postIdx, $unknownCommentIdx] = $this->createTranslatableComment('Unknown language', null);
        [$secondPostIdx, $hiddenCommentIdx] = $this->createTranslatableComment('Hidden comment');
        DB::table('fan_post_comments')->where('idx', $hiddenCommentIdx)->update(['status' => 'hidden', 'deleted_at' => now()]);
        [$hiddenPostIdx, $hiddenPostCommentIdx] = $this->createTranslatableComment('On hidden post');
        DB::table('fan_posts')->where('idx', $hiddenPostIdx)->update(['status' => 'hidden']);
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'inactive@example.test',
            'nickname' => 'inactive_member', 'password_hash' => Hash::make('Password1!'), 'status' => 'inactive',
        ]);
        $inactiveAuthorCommentIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => $postIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'content' => 'Hidden author comment', 'language_code' => 'en', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $unknownCommentIdx, 'language' => 'ko',
        ]))->assertNotFound()->assertJsonPath('code', 'translation_unavailable');
        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $secondPostIdx, 'commentIdx' => $hiddenCommentIdx, 'language' => 'ko',
        ]))->assertNotFound()->assertJsonPath('code', 'comment_not_found');
        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $hiddenPostIdx, 'commentIdx' => $hiddenPostCommentIdx, 'language' => 'ko',
        ]))->assertNotFound()->assertJsonPath('code', 'comment_not_found');
        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $inactiveAuthorCommentIdx, 'language' => 'ko',
        ]))->assertNotFound()->assertJsonPath('code', 'comment_not_found');
        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
            'idx' => $postIdx, 'commentIdx' => $unknownCommentIdx, 'language' => 'en',
        ]))->assertUnprocessable()->assertJsonPath('code', 'translation_target_invalid');
        Http::assertNothingSent();
    }

    public function test_comment_translation_lock_contention_returns_retryable_response_without_google_call(): void
    {
        $this->database();
        Http::fake();
        [$postIdx, $commentIdx] = $this->createTranslatableComment();
        $sourceHash = hash('sha256', 'Original comment');
        $lock = Cache::lock('fan-post-comment-translation:'.hash('sha256', $commentIdx.'|ko|'.$sourceHash), 60);
        $this->assertTrue($lock->get());

        try {
            $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.comments.translation', [
                'idx' => $postIdx, 'commentIdx' => $commentIdx, 'language' => 'ko',
            ]))->assertStatus(503)->assertJsonPath('code', 'translation_retry');
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    public function test_comment_translation_control_is_rendered_only_for_known_foreign_source_language(): void
    {
        $this->database();
        [$postIdx, $knownCommentIdx] = $this->createTranslatableComment('English source', 'en');
        $unknownCommentIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => $postIdx, 'member_idx' => 1, 'actor_member_idx' => 1,
            'content' => 'Unknown source', 'language_code' => null, 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withSession(['locale' => 'ko'])->get(route('fan-post.show', ['idx' => $postIdx]))->assertOk();
        $response->assertSee(route('fan-post.comments.translation', ['idx' => $postIdx, 'commentIdx' => $knownCommentIdx]), false)
            ->assertDontSee(route('fan-post.comments.translation', ['idx' => $postIdx, 'commentIdx' => $unknownCommentIdx]), false);
        $document = new DOMDocument();
        @$document->loadHTML($response->getContent());
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//h1[.//span[@data-fan-post-title] and .//button[@data-translation-toggle]]')->length);
        $this->assertSame(1, $xpath->query('//p[contains(@class, "fan-post-comment-body")][.//span and .//button[@data-comment-translation-toggle]]')->length);
        $this->assertStringContainsString('comment.textContent = translated;', file_get_contents(resource_path('js/fan-post-comment-translation.js')));
    }

    public function test_authenticated_member_can_create_a_youtube_fan_post(): void
    {
        $this->database();

        $response = $this->withSession(['member_idx' => 1])->post('/fan-post', [
            'idol_idx' => 1,
            'title' => 'A new fan post',
            'content' => 'A thoughtful post for this idol.',
            'media_type' => 'youtube',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'tags' => 'concert,comeback',
        ]);

        $post = DB::table('fan_posts')->first();
        $response->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $response->assertSessionHas('global_notification.message', 'Fan Post created! +30 Hearts');
        $this->assertSame(1, $post->member_idx);
        $this->assertSame(1, $post->actor_member_idx);
        $this->assertSame('dQw4w9WgXcQ', $post->youtube_video_id);
        $this->assertSame('youtube', $post->media_type);
        $this->assertSame('en', $post->language_code);
        $this->assertNull($post->image_path);
        $this->assertSame(['concert', 'comeback'], DB::table('fan_post_tags')->orderBy('idx')->pluck('tag')->all());
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => 30, 'reason' => 'fan_post_reward',
            'operation_key' => 'fan_post_reward:'.$post->idx,
        ]);
        $this->get(route('fan-post.show', ['idx' => $post->idx]))->assertOk()
            ->assertSee('A new fan post')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false)
            ->assertDontSee('data-fan-post-translation', false);

        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$post->idx, [
            'idol_idx' => 1, 'title' => 'Text only now', 'content' => 'No media remains.',
            'media_type' => 'text', 'tags' => '',
        ])->assertSessionHasErrors('media_type');
        $post = DB::table('fan_posts')->where('idx', $post->idx)->first();
        $this->assertSame('youtube', $post->media_type);
        $this->assertSame('en', $post->language_code, 'Editing without a language change preserves the original language.');
        $this->assertNull($post->image_path);

        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$post->idx, [
            'idol_idx' => 1, 'title' => 'Shorts now', 'content' => 'A Shorts video.',
            'media_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=abcdefghijk', 'tags' => '',
        ])->assertSessionHasErrors('media_type');
        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$post->idx, [
            'title' => 'Edited video post', 'content' => 'The video remains attached.',
        ])->assertRedirect(route('fan-post.show', ['idx' => $post->idx]));
        $post = DB::table('fan_posts')->where('idx', $post->idx)->first();
        $this->assertSame('youtube', $post->media_type);
        $this->assertNull($post->image_path);
        $this->assertSame('dQw4w9WgXcQ', $post->youtube_video_id);
        $this->assertSame('Edited video post', $post->title);
        $this->assertSame(['concert', 'comeback'], DB::table('fan_post_tags')->where('fan_post_idx', $post->idx)->orderBy('idx')->pluck('tag')->all());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_reward')->count());
    }

    public function test_fan_post_reward_failure_is_logged_and_post_remains_saved(): void
    {
        $this->database();
        Log::spy();
        DB::statement("CREATE TRIGGER fail_fan_post_reward BEFORE INSERT ON heart_histories
            WHEN NEW.reason = 'fan_post_reward'
            BEGIN SELECT RAISE(ABORT, 'simulated fan post reward failure'); END");

        $this->withSession(['member_idx' => 1])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Saved without optional reward', 'content' => 'The post must remain available.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => 'saved',
        ])->assertRedirect();

        $post = DB::table('fan_posts')->where('title', 'Saved without optional reward')->first();
        $this->assertNotNull($post);
        $this->assertSame(['saved'], DB::table('fan_post_tags')->where('fan_post_idx', $post->idx)->pluck('tag')->all());
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($post): bool {
            return $message === 'Automatic Heart reward processing failed.'
                && $context['reward_action'] === 'fan_post_create'
                && $context['recipient_member_id'] === 1
                && $context['actor_member_id'] === 1
                && $context['fan_post_id'] === (int) $post->idx
                && $context['comment_id'] === null
                && $context['operation_key'] === 'fan_post_reward:'.$post->idx
                && $context['exception_class'] === \Illuminate\Database\QueryException::class
                && str_contains($context['exception_message'], 'simulated fan post reward failure');
        });
    }

    public function test_japanese_content_badge_is_language_only_and_author_flag_precedes_nickname(): void
    {
        $this->database();
        app()->setLocale('en');
        $idx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => '?ζ?�沃?�겗?뺟㉮',
            'language_code' => 'ja', 'media_type' => 'text', 'content' => 'Original text',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('fan-post.show', ['idx' => $idx]))->assertOk()
            ->assertSee('title="JA" aria-label="JA"', false)
            ->assertDontSee('title="South Korea · JA"', false)
            ->assertSee('src="'.asset('images/flags/kr.svg').'"', false)
            ->assertSee('data-fan-post-translation', false);
    }

    public function test_comment_author_flag_uses_the_comment_members_country_code(): void
    {
        $this->database();
        [$postIdx] = $this->createTranslatableComment('Comment with member flag');

        $response = $this->get(route('fan-post.show', ['idx' => $postIdx]))->assertOk();
        $document = new DOMDocument();
        $previousErrorMode = libxml_use_internal_errors(true);
        $document->loadHTML($response->getContent());
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);
        $xpath = new DOMXPath($document);
        $authorLinks = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fan-detail-author-name ")]');

        $this->assertSame(2, $authorLinks->length);
        $commentAuthor = $authorLinks->item(1);
        $this->assertSame('member_one', trim($commentAuthor->textContent));
        $this->assertSame(asset('images/flags/kr.svg'), $xpath->query('.//img[contains(@class, "country-flag")]', $commentAuthor)->item(0)->getAttribute('src'));
        $flag = $xpath->query('.//img[contains(@class, "country-flag")]', $commentAuthor)->item(0);
        $this->assertSame('18', $flag->getAttribute('width'));
        $this->assertSame('13', $flag->getAttribute('height'));
        $stylesheet = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.fan-detail-comment-list img:not(.country-flag) { width: 38px; height: 38px;', $stylesheet);
    }

    public function test_cached_translation_is_returned_only_when_the_source_hash_matches(): void
    {
        $this->database();
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Original title',
            'language_code' => 'en', 'media_type' => 'text', 'content' => 'Original content',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fan_post_translations')->insert([
            'fan_post_idx' => $postIdx, 'language_code' => 'ko', 'translated_title' => '踰덉�???�ぉ',
            'translated_content' => '踰덉�?蹂몃Ц',
            'source_hash' => \App\Support\FanPostSourceHash::make('Original title', 'Original content'),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['locale' => 'ko'])->get(route('fan-post.translation', ['idx' => $postIdx, 'language' => 'ko']))
            ->assertOk()->assertJsonPath('data.title', '踰덉�???�ぉ')->assertJsonPath('data.content', '踰덉�?蹂몃Ц');
        $this->assertDatabaseHas('fan_posts', ['idx' => $postIdx, 'title' => 'Original title', 'content' => 'Original content']);
        $this->assertSame(0, (int) DB::table('fan_posts')->where('idx', $postIdx)->value('view_count'));

        DB::table('fan_posts')->where('idx', $postIdx)->update(['content' => 'Updated original content']);
        Config::set('services.google_translate.api_key', 'test-secret-key');
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'data' => ['translations' => [
                ['translatedText' => 'Updated translated title'],
                ['translatedText' => 'Updated translated content'],
            ]],
        ])]);
        $this->withSession(['locale' => 'ko'])->get(route('fan-post.translation', ['idx' => $postIdx, 'language' => 'ko']))
            ->assertOk()->assertJsonPath('data.content', 'Updated translated content');
        $this->assertDatabaseHas('fan_post_translations', [
            'fan_post_idx' => $postIdx,
            'source_hash' => \App\Support\FanPostSourceHash::make('Original title', 'Updated original content'),
        ]);
    }

    public function test_translation_endpoint_rejects_non_ui_targets_and_non_public_posts(): void
    {
        $this->database();
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Public title',
            'language_code' => 'en', 'media_type' => 'text', 'content' => 'Public content',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['locale' => 'ko'])->get(route('fan-post.translation', ['idx' => $postIdx, 'language' => 'fr']))
            ->assertUnprocessable()->assertJsonPath('code', 'translation_target_invalid');

        DB::table('fan_posts')->where('idx', $postIdx)->update(['status' => 'hidden']);
        $this->withSession(['locale' => 'ko'])->get(route('fan-post.translation', ['idx' => $postIdx, 'language' => 'ko']))
            ->assertNotFound()->assertJsonPath('code', 'fan_post_not_found');
    }

    public function test_translation_cache_hit_does_not_call_google(): void
    {
        $this->database();
        Http::fake();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Cached title',
            'language_code' => 'en', 'content' => 'Cached body', 'status' => 'published',
        ]);
        DB::table('fan_post_translations')->insert([
            'fan_post_idx' => $postIdx, 'language_code' => 'ko', 'translated_title' => '???λ�???�ぉ',
            'translated_content' => '???λ�?蹂몃Ц',
            'source_hash' => \App\Support\FanPostSourceHash::make('Cached title', 'Cached body'),
        ]);

        $response = $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.title', '???λ�???�ぉ');

        $this->assertStringNotContainsString('test-secret-key', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_translation_cache_miss_calls_google_and_saves_plain_text_translation(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'data' => ['translations' => [
                ['translatedText' => '<b>踰덉�???�ぉ</b>'],
                ['translatedText' => '<script>踰덉�?蹂몃Ц</script>'],
            ]],
        ])]);
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Source title',
            'language_code' => 'en', 'content' => 'Source body', 'status' => 'published',
        ]);

        $response = $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.title', '<b>踰덉�???�ぉ</b>')
            ->assertJsonPath('data.content', '<script>踰덉�?蹂몃Ц</script>');

        Http::assertSent(fn ($request) => $request->url() === 'https://translation.googleapis.com/language/translate/v2?key=test-secret-key'
            && $request['q'] === ['Source title', 'Source body']
            && $request['source'] === 'en' && $request['target'] === 'ko'
            && $request['format'] === 'text');
        $this->assertStringNotContainsString('test-secret-key', $response->getContent());
        $this->assertDatabaseHas('fan_post_translations', [
            'fan_post_idx' => $postIdx, 'language_code' => 'ko',
            'translated_title' => '<b>踰덉�???�ぉ</b>', 'translated_content' => '<script>踰덉�?蹂몃Ц</script>',
            'source_hash' => \App\Support\FanPostSourceHash::make('Source title', 'Source body'),
        ]);
    }

    public function test_translation_api_failure_is_retryable_and_does_not_save_cache(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        Log::spy();
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'error' => [
                'code' => 403,
                'status' => 'PERMISSION_DENIED',
                'message' => 'Key test-secret-key denied; https://private.example/?key=test-secret-key',
            ],
        ], 403)]);
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Source title',
            'language_code' => 'en', 'content' => 'Source body', 'status' => 'published',
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        $this->assertSame(0, DB::table('fan_post_translations')->count());
        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context): bool {
            $safeContext = json_encode($context);

            return $context['http_status'] === 403
                && $context['google_error_status'] === 'PERMISSION_DENIED'
                && !str_contains($safeContext, 'test-secret-key')
                && !str_contains($safeContext, 'https://');
        });
    }

    public function test_translation_lock_contention_returns_retryable_response_without_google_call(): void
    {
        $this->database();
        Http::fake();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Source title',
            'language_code' => 'en', 'content' => 'Source body', 'status' => 'published',
        ]);
        $hash = \App\Support\FanPostSourceHash::make('Source title', 'Source body');
        $lock = Cache::lock('fan-post-translation:'.hash('sha256', $postIdx.'|ko|'.$hash), 60);
        $this->assertTrue($lock->get());

        try {
            $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
                'idx' => $postIdx, 'language' => 'ko',
            ]))->assertStatus(503)->assertJsonPath('code', 'translation_retry');
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
    }

    public function test_source_edit_during_translation_prevents_stale_cache_save(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Source title',
            'language_code' => 'en', 'content' => 'Source body', 'status' => 'published',
        ]);
        Http::fake(function () use ($postIdx) {
            DB::table('fan_posts')->where('idx', $postIdx)->update(['content' => 'Edited while translating']);

            return Http::response(['data' => ['translations' => [
                ['translatedText' => 'Translated title'], ['translatedText' => 'Translated body'],
            ]]]);
        });

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        $this->assertSame(0, DB::table('fan_post_translations')->count());
    }

    public function test_missing_translation_api_key_does_not_call_google_or_save_translation(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', null);
        Http::fake();
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Source title',
            'language_code' => 'en', 'content' => 'Source body', 'status' => 'published',
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertStatus(503)->assertJsonPath('code', 'translation_error');
        Http::assertNothingSent();
        $this->assertSame(0, DB::table('fan_post_translations')->count());
    }

    public function test_null_content_is_preserved_without_an_extra_google_translation_item(): void
    {
        $this->database();
        Config::set('services.google_translate.api_key', 'test-secret-key');
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'data' => ['translations' => [['translatedText' => '踰덉�???�ぉ']]],
        ])]);
        $postIdx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Title only',
            'language_code' => 'en', 'content' => null, 'status' => 'published',
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('fan-post.translation', [
            'idx' => $postIdx, 'language' => 'ko',
        ]))->assertOk()->assertJsonPath('data.content', null);
        Http::assertSent(fn ($request) => $request['q'] === ['Title only']);
        $this->assertDatabaseHas('fan_post_translations', [
            'fan_post_idx' => $postIdx, 'translated_content' => null,
        ]);
    }

    public function test_editing_a_post_does_not_replace_or_remove_its_original_image(): void
    {
        $this->database();
        $idx = DB::table('fan_posts')->insertGetId([
            'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Image post',
            'language_code' => 'ko', 'media_type' => 'image', 'image_path' => 'storage/fan-posts/original.png',
            'content' => 'Original image remains stored.', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->put('/fan-post/'.$idx, [
            'idol_idx' => 1, 'title' => 'Edited post', 'content' => 'Keep its image.',
            'media_type' => 'text', 'tags' => '',
        ])->assertSessionHasErrors('media_type');

        $post = DB::table('fan_posts')->where('idx', $idx)->first();
        $this->assertSame('storage/fan-posts/original.png', $post->image_path);
        $this->assertSame('image', $post->media_type);
    }

    public function test_admin_and_dummy_can_create_and_comment_without_automatic_rewards(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode0001', 'email' => 'admin@example.test', 'nickname' => 'admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummyCode0001', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
        ]);

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));

        $response = $this->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Delegated post', 'content' => 'Posted as a dummy member.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect();
        $post = DB::table('fan_posts')->where('title', 'Delegated post')->first();
        $this->assertSame(3, (int) $post->member_idx);
        $this->assertSame(2, (int) $post->actor_member_idx);
        $this->assertDatabaseMissing('heart_histories', ['member_idx' => 3, 'reason' => 'fan_post_reward']);

        $this->postJson('/fan-post/'.$post->idx.'/comments', [
            'content' => 'Comment as dummy', 'request_key' => 'delegated-comment-self-01',
        ])->assertCreated()->assertJsonPath('data.comment.nickname', 'dummy')
            ->assertJsonPath('data.rewarded_hearts', 0);
        $this->assertDatabaseHas('fan_post_comments', [
            'fan_post_idx' => $post->idx, 'member_idx' => 3, 'actor_member_idx' => 2,
            'content' => 'Comment as dummy',
        ]);
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'comment_reward')->count());

        DB::table('fan_posts')->insert([
            'idx' => 51, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2,
            'title' => 'Admin-authored post', 'content' => 'Post body', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->postJson('/fan-post/51/comments', [
            'content' => 'Comment as dummy on admin post', 'request_key' => 'delegated-comment-other-01',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);
        $this->assertDatabaseMissing('heart_histories', ['member_idx' => 3, 'reason' => 'comment_reward']);
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(2, (int) session('member_idx'));

        $this->withSession(['member_idx' => 2, 'acting_dummy_member_idx' => null])->postJson('/fan-post/51/comments', [
            'content' => 'Administrator self comment', 'request_key' => 'admin-comment-self-0001',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'comment_reward')->count());

        $this->withSession(['member_idx' => 3])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Direct dummy post', 'content' => 'Posted while signed in directly.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect();
        $directPost = DB::table('fan_posts')->where('title', 'Direct dummy post')->first();
        $this->assertSame(3, (int) $directPost->member_idx);
        $this->assertSame(3, (int) $directPost->actor_member_idx);
        $this->assertDatabaseMissing('heart_histories', [
            'member_idx' => 3, 'operation_key' => 'fan_post_reward:'.$directPost->idx,
        ]);

        $this->withSession(['member_idx' => 2, 'acting_dummy_member_idx' => null])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Direct admin post', 'content' => 'Posted by the administrator account.',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/dQw4w9WgXcQ', 'tags' => '',
        ])->assertRedirect();
        $adminPost = DB::table('fan_posts')->where('title', 'Direct admin post')->first();
        $this->assertSame(2, (int) $adminPost->member_idx);
        $this->assertDatabaseMissing('heart_histories', [
            'member_idx' => 2, 'operation_key' => 'fan_post_reward:'.$adminPost->idx,
        ]);
    }

    public function test_text_posts_and_media_posts_past_the_daily_limit_are_not_rewarded(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'fan_post_create')
            ->update(['heart_amount' => 45, 'daily_limit' => 1]);

        $this->withSession(['member_idx' => 1])->post('/fan-post', [
            'idol_idx' => 1, 'title' => 'Text post', 'content' => 'No eligible media.',
            'media_type' => 'text', 'tags' => '',
        ])->assertRedirect()->assertSessionMissing('global_notification');

        foreach (['First video', 'Second video'] as $title) {
            $this->withSession(['member_idx' => 1])->post('/fan-post', [
                'idol_idx' => 1, 'title' => $title, 'content' => 'Eligible video post.',
                'media_type' => 'youtube',
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'tags' => '',
            ])->assertRedirect();
        }

        $this->assertSame(3, DB::table('fan_posts')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_reward')->count());
        $this->assertSame(45, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_shared_cards_link_content_and_known_member_authors_but_not_statistics(): void
    {
        $this->database();
        $this->get('/fan-post/1')->assertNotFound();

        foreach (range(1, 14) as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
                'title' => 'Post '.$idx, 'content' => 'A sample post body.', 'media_type' => 'text',
                'status' => 'published', 'created_at' => now()->subMinutes($idx),
            ]);
        }

        foreach (['/', '/idol/1'] as $url) {
            $response = $this->get($url)->assertOk();
            $document = new DOMDocument;
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML($response->getContent());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($document);
            $cards = $xpath->query('//article[contains(@class,"fan-post-card")]');
            $this->assertSame(14, $cards->length);
            foreach ($cards as $card) {
                $expected = $xpath->query('./a[img]', $card)->item(0)->getAttribute('href');
                $this->assertMatchesRegularExpression('~/fan-post/\d+$~', $expected);
                $this->assertSame($expected, $xpath->query('.//p[@class="post-preview"]/a', $card)->item(0)->getAttribute('href'));
                $authorLinks = $xpath->query('./div[@class="post-body"]/div[@class="post-author"]/a', $card);
                $this->assertContains($authorLinks->length, [0, 2]);
                if ($authorLinks->length === 2) {
                    $this->assertSame($authorLinks->item(0)->getAttribute('href'), $authorLinks->item(1)->getAttribute('href'));
                    $this->assertMatchesRegularExpression('~/member/[A-Za-z0-9]{12}/fan-posts$~', $authorLinks->item(0)->getAttribute('href'));
                }
                $this->assertSame(0, $xpath->query('./div[@class="post-statistics"]//a | ./a//div[@class="post-statistics"]', $card)->length);
            }
        }

    }

    public function test_fixture_fan_post_ids_are_not_public_without_database_records(): void
    {
        $this->database();

        foreach ([1, 8, 14] as $idx) {
            $this->get(route('fan-post.show', ['idx' => $idx]))->assertNotFound();
        }
    }

    public function test_database_detail_lists_real_public_posts_for_the_same_idol_and_highlights_current(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'author@example.test',
            'nickname' => 'real_author', 'profile_image_path' => null,
        ]);
        DB::table('idols')->insert(['idx' => 2, 'name' => 'Other Idol', 'status' => 'active']);
        DB::table('fan_posts')->insert([
            ['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Current post', 'content' => 'Current body', 'media_type' => 'text', 'view_count' => 10, 'status' => 'published', 'created_at' => '2026-09-01 10:00:00'],
            ['idx' => 51, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Related post', 'content' => 'Related body', 'media_type' => 'text', 'view_count' => 20, 'status' => 'published', 'created_at' => '2026-09-02 10:00:00'],
            ['idx' => 52, 'idol_idx' => 2, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Other Idol post', 'content' => 'Other body', 'media_type' => 'text', 'view_count' => 0, 'status' => 'published', 'created_at' => '2026-09-03 10:00:00'],
            ['idx' => 53, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Hidden related post', 'content' => 'Hidden body', 'media_type' => 'text', 'view_count' => 0, 'status' => 'hidden', 'created_at' => '2026-09-04 10:00:00'],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 50, 'member_idx' => 1],
            ['fan_post_idx' => 50, 'member_idx' => 2],
        ]);
        DB::table('fan_post_comments')->insert([
            'fan_post_idx' => 51, 'member_idx' => 1, 'actor_member_idx' => 1,
            'request_key' => 'detail-related-comment-0001', 'content' => 'Related comment',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->withSession(['member_idx' => 1])->get('/fan-post/50?sort=liked')->assertOk()
            ->assertViewHas('posts', fn (array $posts): bool =>
                array_column($posts, 'fan_post_idx') === [50, 51]
                && $posts[0][4] === 2
                && $posts[1][5] === 1
                && $posts[1]['member_code'] === 'memberCodeB2'
            )
            ->assertSee(route('fan-post.create', ['idol_idx' => 1]), false)
            ->assertDontSee('Hidden related post');

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $this->assertSame(2, $xpath->query('//article[contains(@class,"fan-post-card")]')->length);
        $this->assertSame(1, $xpath->query('//article[contains(@class,"fan-post-card--current")]')->length);
        $this->assertSame(
            route('member.fan-posts', ['member_code' => 'memberCodeB2']),
            $xpath->query('//article[contains(@class,"fan-post-card")][2]//div[@class="post-author"]/a')->item(0)->getAttribute('href')
        );
    }

    public function test_existing_lists_have_no_current_card_and_unknown_posts_are_not_found(): void
    {
        $this->database();

        foreach (['/', '/idol/1'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('fan-post-card--current');
        }
        $this->get('/fan-post/99999')->assertNotFound();
        $this->get('/fan-post/not-an-idx')->assertNotFound();
        foreach (range(1, 14) as $idx) {
            DB::table('fan_posts')->insert([
                'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
                'title' => 'Database post '.$idx, 'content' => 'Real database detail.', 'status' => 'published',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (range(1, 14) as $idx) {
            $this->get('/fan-post/'.$idx)->assertOk();
        }
    }

    public function test_mobile_detail_keeps_comments_before_the_remaining_sidebar_panels(): void
    {
        $this->database();
        DB::table('fan_posts')->insert([
            'idx' => 1, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Real mobile detail', 'content' => 'Actual database post.', 'media_type' => 'text',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.fan-detail-main, .fan-detail-sidebar { display: contents; }', $css);
        $this->assertStringContainsString('.fan-detail-comments { order: 3; }', $css);
        $this->assertStringContainsString('.fan-detail-sidebar > :nth-child(1) { order: 4; }', $css);
        $this->assertStringContainsString('.fan-detail-sidebar > :nth-child(2) { order: 5; }', $css);

        $response = $this->get('/fan-post/1')->assertOk();
        $this->assertSame(0, substr_count($response->getContent(), 'id="author-info-title"'));
    }

    public function test_database_comments_are_paginated_ten_per_page_with_dedicated_query_parameter(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'commenter@example.test', 'nickname' => 'commenter',
            'country_code' => 'US', 'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('fan_posts')->insert(['idx' => 5, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        foreach (range(1, 25) as $commentIdx) {
            DB::table('fan_post_comments')->insert([
                'idx' => $commentIdx,
                'fan_post_idx' => 5,
                'member_idx' => 2,
                'content' => 'Database comment '.$commentIdx,
                'status' => 'published',
                'created_at' => sprintf('2026-09-%02d 12:00:00', $commentIdx),
            ]);
        }

        $response = $this->get('/fan-post/5?filter=popular&comments_page=2')->assertOk();
        $response->assertViewHas('comments', fn ($comments) =>
            $comments->currentPage() === 2 && $comments->perPage() === 10
            && $comments->total() === 25 && $comments->count() === 10
        );
        $response->assertSee('Database comment 15')->assertDontSee('Database comment 16');
        $response->assertSee('comments_page=1', false)->assertSee('filter=popular', false);
        $this->assertSame(20, substr_count($response->getContent(), 'href="'.route('member.fan-posts', ['member_code' => 'memberCodeB2']).'"'));

        $this->get('/fan-post/5?comments_page=999')->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->currentPage() === 3 && $comments->count() === 5);
        $this->get('/fan-post/5?comments_page=invalid')->assertOk()
            ->assertViewHas('comments', fn ($comments) => $comments->currentPage() === 1);
    }

    public function test_detail_recommendation_statistics_show_monthly_and_lifetime_counts(): void
    {
        $this->database();
        $this->travelTo(now()->startOfMonth()->addDays(10));
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'member2@example.test', 'nickname' => 'member_two'],
            ['idx' => 3, 'member_code' => 'memberCodeC3', 'email' => 'member3@example.test', 'nickname' => 'member_three'],
        ]);
        DB::table('fan_posts')->insert([
            ['idx' => 70, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Counted post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
            ['idx' => 71, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'No likes post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 70, 'member_idx' => 1, 'actor_member_idx' => 1, 'created_at' => now()],
            ['fan_post_idx' => 70, 'member_idx' => 2, 'actor_member_idx' => 2, 'created_at' => now()->startOfMonth()],
            ['fan_post_idx' => 70, 'member_idx' => 3, 'actor_member_idx' => 3, 'created_at' => now()->startOfMonth()->subDay()],
        ]);

        $english = $this->get('/fan-post/70')->assertOk();
        $english->assertSee('<span data-monthly-recommendation-count>2</span> (<span data-recommendation-count>3</span>)</span> <span>Recommendations', false);

        $korean = $this->withSession(['locale' => 'ko'])->get('/fan-post/71')->assertOk();
        $korean->assertSee('<span data-monthly-recommendation-count>0</span> (<span data-recommendation-count>0</span>)</span> <span>추천', false);
    }

    public function test_self_comment_is_saved_without_reward_and_can_still_be_deleted(): void
    {
        $this->database();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'A meaningful comment on my own post',
            'language_code' => 'en',
            'request_key' => 'self-comment-request-0001',
        ])->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Comment posted.')
            ->assertJsonPath('data.rewarded_hearts', 0)
            ->assertJsonPath('data.reward_recorded', false)
            ->assertJsonPath('data.comment.language_code', 'en');

        $commentIdx = $response->json('data.comment.idx');
        $this->assertDatabaseHas('fan_post_comments', [
            'idx' => $commentIdx, 'fan_post_idx' => 50, 'member_idx' => 1,
            'actor_member_idx' => 1, 'content' => 'A meaningful comment on my own post',
        ]);
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
        $this->get('/fan-post/50')->assertOk()->assertDontSee('class="fan-detail-comment-reward"', false);
    }

    public function test_consecutive_comments_are_saved_after_reward_limit_and_duplicate_checks(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2,
            'title' => 'Post', 'content' => 'Body', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('heart_reward_settings')->where('action_code', 'comment_create')->update(['daily_limit' => 1]);

        foreach ([
            ['An eligible comment to receive Hearts', 'consecutive-comment-0001', 10],
            ['An eligible comment to receive Hearts', 'consecutive-comment-0002', 0],
            ['short', 'consecutive-comment-0003', 0],
            ['Another eligible comment beyond the daily limit', 'consecutive-comment-0004', 0],
        ] as $index => [$content, $requestKey, $expectedReward]) {
            $response = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
                'content' => $content, 'request_key' => $requestKey,
            ])->assertCreated()->assertJsonPath('data.rewarded_hearts', $expectedReward);
            $response->assertJsonPath('data.reward_recorded', $index === 0);
        }

        $this->assertSame(4, DB::table('fan_post_comments')->where('fan_post_idx', 50)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
    }

    public function test_member_can_receive_comment_reward_on_another_members_post_and_soft_delete_comment(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => "  First line\nSecond line  ",
            'language_code' => 'ja',
            'request_key' => 'comment-request-0001',
        ])->assertCreated()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Comment posted! +10 Hearts')
            ->assertJsonPath('data.rewarded_hearts', 10);

        $commentIdx = $response->json('data.comment.idx');
        $this->assertSame("  First line\nSecond line  ", DB::table('fan_post_comments')->where('idx', $commentIdx)->value('content'));
        $this->assertSame('ja', DB::table('fan_post_comments')->where('idx', $commentIdx)->value('language_code'));
        $this->assertSame(10, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $history = DB::table('heart_histories')->first();
        $this->assertSame('comment_reward', $history->reason);
        $this->assertSame('comment_reward:'.$commentIdx, $history->operation_key);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => "  First line\nSecond line  ",
            'language_code' => 'ja',
            'request_key' => 'comment-request-0001',
        ])->assertOk()->assertJsonPath('data.rewarded_hearts', 10)
            ->assertJsonPath('data.reward_recorded', true)
            ->assertJsonPath('data.created', false);
        $this->assertSame(1, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->count());

        $this->withSession(['member_idx' => 1])->deleteJson('/fan-post/50/comments/'.$commentIdx)
            ->assertOk()->assertJsonPath('data.comment_count', 0);
        $deleted = DB::table('fan_post_comments')->where('idx', $commentIdx)->first();
        $this->assertSame('hidden', $deleted->status);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame(1, DB::table('heart_histories')->count());
    }

    public function test_comment_reward_failure_is_logged_and_comment_remains_saved(): void
    {
        $this->database();
        Log::spy();
        DB::table('fan_posts')->insert([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2,
            'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement("CREATE TRIGGER fail_comment_reward BEFORE INSERT ON heart_histories
            WHEN NEW.reason = 'comment_reward'
            BEGIN SELECT RAISE(ABORT, 'simulated comment reward failure'); END");

        $response = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'An eligible comment that should remain saved.', 'request_key' => 'comment-reward-failure-0001',
        ])->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.rewarded_hearts', 0)
            ->assertJsonPath('data.reward_recorded', false);

        $commentIdx = (int) $response->json('data.comment.idx');
        $this->assertDatabaseHas('fan_post_comments', [
            'idx' => $commentIdx, 'fan_post_idx' => 50, 'member_idx' => 1,
            'content' => 'An eligible comment that should remain saved.',
        ]);
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($commentIdx): bool {
            return $message === 'Automatic Heart reward processing failed.'
                && $context['reward_action'] === 'comment_create'
                && $context['recipient_member_id'] === 1
                && $context['actor_member_id'] === 1
                && $context['fan_post_id'] === 50
                && $context['comment_id'] === $commentIdx
                && $context['operation_key'] === 'comment_reward:'.$commentIdx
                && $context['exception_class'] === \Illuminate\Database\QueryException::class
                && str_contains($context['exception_message'], 'simulated comment reward failure');
        });
    }

    public function test_comment_rewards_use_inclusive_range_and_equal_bounds_are_fixed(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['min_heart_amount' => 0, 'max_heart_amount' => 10]);

        $ranged = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Ranged reward', 'request_key' => 'comment-range-test-0001',
        ])->assertCreated()->json('data.rewarded_hearts');
        $this->assertGreaterThanOrEqual(0, $ranged);
        $this->assertLessThanOrEqual(10, $ranged);
        $this->assertDatabaseHas('heart_histories', [
            'amount' => $ranged, 'reason' => 'comment_reward', 'operation_key' => 'comment_reward:1',
        ]);

        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['min_heart_amount' => 6, 'max_heart_amount' => 6]);
        $fixed = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Fixed reward', 'request_key' => 'comment-range-test-0002',
        ])->assertCreated()->json('data.rewarded_hearts');
        $this->assertSame(6, $fixed);
        $this->assertDatabaseHas('heart_histories', [
            'amount' => 6, 'reason' => 'comment_reward', 'operation_key' => 'comment_reward:2',
        ]);

        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['min_heart_amount' => 0, 'max_heart_amount' => 0]);
        $zero = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'A valid comment with a zero Heart reward', 'request_key' => 'comment-range-zero-0001',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0)
            ->assertJsonPath('data.reward_recorded', true);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'comment_idx' => $zero->json('data.comment.idx'), 'amount' => 0,
            'reason' => 'comment_reward',
        ]);
    }

    public function test_short_and_repeated_character_comments_are_saved_without_rewards(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([
            '1234',
            str_repeat('a', 10),
            str_repeat('7', 10),
            str_repeat('ㅋ', 10),
            str_repeat("\u{1F602}", 10),
        ] as $index => $content) {
            $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
                'content' => $content, 'request_key' => 'comment-ineligible-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
            ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);
        }

        $this->assertSame(5, DB::table('fan_post_comments')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_comment_reward_minimum_is_five_non_whitespace_unicode_graphemes(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => ' 안 녕 하 세 ', 'request_key' => 'comment-five-grapheme-0001',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => ' 안 녕 하 세 요 ', 'request_key' => 'comment-five-grapheme-0002',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 10);

        $this->assertSame(2, DB::table('fan_post_comments')->where('fan_post_idx', 50)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
    }

    public function test_normalized_duplicate_comment_content_is_rewarded_only_once_per_member(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['min_heart_amount' => 7, 'max_heart_amount' => 7]);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Meaningful useful comment', 'request_key' => 'comment-duplicate-0001',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 7);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => '  MEANINGFUL   USEFUL COMMENT  ', 'request_key' => 'comment-duplicate-0002',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);

        $this->assertSame(2, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
        $this->assertSame(7, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_public_comments_show_recorded_reward_amounts_including_zero_only_when_awarded(): void
    {
        $this->database();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        $rewardedIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => 50, 'member_idx' => 1, 'actor_member_idx' => 1, 'content' => 'Rewarded comment',
            'language_code' => 'en', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $zeroRewardIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => 50, 'member_idx' => 1, 'actor_member_idx' => 1, 'content' => 'Zero reward comment',
            'language_code' => 'en', 'status' => 'published', 'created_at' => now()->subSecond(), 'updated_at' => now(),
        ]);
        $unrewardedIdx = DB::table('fan_post_comments')->insertGetId([
            'fan_post_idx' => 50, 'member_idx' => 1, 'actor_member_idx' => 1, 'content' => 'Unrewarded comment',
            'language_code' => 'en', 'status' => 'published', 'created_at' => now()->subSeconds(2), 'updated_at' => now(),
        ]);
        foreach ([[$rewardedIdx, 7], [$zeroRewardIdx, 0]] as [$commentIdx, $amount]) {
            DB::table('heart_histories')->insert([
                'member_idx' => 1, 'amount' => $amount, 'balance_after' => 7, 'reason' => 'comment_reward',
                'comment_idx' => $commentIdx, 'operation_key' => 'comment_reward:'.$commentIdx,
                'created_at' => now(),
            ]);
        }

        $response = $this->get('/fan-post/50')->assertOk()
            ->assertSee('class="fan-detail-comment-reward">+7 Heart</span>', false)
            ->assertSee('class="fan-detail-comment-reward">+0 Heart</span>', false);
        preg_match('~<li data-comment-idx="'.$unrewardedIdx.'">(.*?)</li>~s', $response->getContent(), $unrewardedMarkup);
        $this->assertNotEmpty($unrewardedMarkup);
        $this->assertStringNotContainsString('fan-detail-comment-reward', $unrewardedMarkup[1]);
    }

    public function test_new_comment_defaults_to_current_locale_and_rejects_unknown_language_codes(): void
    {
        $this->database();
        DB::table('fan_posts')->insert([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1, 'locale' => 'ko'])->postJson('/fan-post/50/comments', [
            'content' => '?�국???��?', 'request_key' => 'comment-language-default-01',
        ])->assertCreated();
        $this->assertDatabaseHas('fan_post_comments', [
            'fan_post_idx' => 50, 'content' => '?�국???��?', 'language_code' => 'ko',
        ]);

        $this->withSession(['member_idx' => 1, 'locale' => 'ko'])->postJson('/fan-post/50/comments', [
            'content' => 'Unsupported language', 'language_code' => 'zz',
            'request_key' => 'comment-language-invalid-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('language_code');
    }

    public function test_comment_creation_continues_when_reward_is_disabled_or_daily_limit_is_reached(): void
    {
        $this->database();
        $this->insertOtherMember();
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 2, 'actor_member_idx' => 2, 'title' => 'Post', 'content' => 'Body', 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('heart_reward_settings')->where('action_code', 'comment_create')
            ->update(['heart_amount' => 17, 'daily_limit' => 1]);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'First rewarded comment', 'request_key' => 'comment-limit-test-01',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 17);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Comment after limit', 'request_key' => 'comment-limit-test-02',
        ])->assertCreated()->assertJsonPath('message', 'Comment posted.')
            ->assertJsonPath('data.rewarded_hearts', 0);

        DB::table('heart_reward_settings')->where('action_code', 'comment_create')->update(['is_enabled' => 0]);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', [
            'content' => 'Comment while disabled', 'request_key' => 'comment-limit-test-03',
        ])->assertCreated()->assertJsonPath('data.rewarded_hearts', 0);

        $this->assertSame(3, DB::table('fan_post_comments')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'comment_reward')->count());
        $this->assertSame(17, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_comment_endpoints_enforce_authorship_and_published_post(): void
    {
        $this->database();
        DB::table('members')->insert(['idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'other@example.test', 'nickname' => 'other', 'is_dummy' => 0, 'status' => 'active']);
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Hidden', 'content' => 'Body', 'status' => 'hidden', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/fan-post/50/comments', ['content' => 'No', 'request_key' => 'comment-request-0002'])->assertUnauthorized();
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', ['content' => 'No', 'request_key' => 'comment-request-0002'])->assertNotFound();

        DB::table('fan_posts')->where('idx', 50)->update(['status' => 'published']);
        $comment = $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments', ['content' => 'Mine', 'request_key' => 'comment-request-0003'])->json('data.comment');
        $this->withSession(['member_idx' => 2])->deleteJson('/fan-post/50/comments/'.$comment['idx'])->assertForbidden();
    }

    public function test_recommendations_are_irreversible_and_reward_the_post_author_once(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'recommender@example.test',
            'nickname' => 'recommender', 'country_code' => 'US', 'status' => 'active',
        ]);
        DB::table('fan_posts')->insert(['idx' => 5, 'idol_idx' => 1, 'member_idx' => 1, 'title' => 'Real post', 'content' => 'Database content', 'status' => 'published']);

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/5/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1)
            ->assertJsonPath('data.monthly_recommendations', 1);
        $this->get('/fan-post/5')->assertOk()->assertSee('Recommend')->assertDontSee('Recommended');
        $this->postJson('/fan-post/5/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        DB::table('heart_reward_settings')->where('action_code', 'fan_post_recommend_received')
            ->update(['heart_amount' => 7, 'daily_limit' => 1]);
        DB::table('fan_posts')->insert(['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);

        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->assertSame(7, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1,
            'reason' => 'fan_post_recommend_received',
            'amount' => 7,
            'operation_key' => 'fan_post_recommend_received:50:2',
            'actor_member_idx' => 2,
        ]);

        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/recommendation')
            ->assertOk()->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());

        DB::table('fan_posts')->insert(['idx' => 51, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/51/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 51)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());

        DB::table('fan_posts')->insert(['idx' => 52, 'idol_idx' => 1, 'member_idx' => 2, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/52/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 2)
            ->where('reason', 'fan_post_recommend_received')->count());

        DB::table('heart_reward_settings')->where('action_code', 'fan_post_recommend_received')
            ->update(['is_enabled' => 0]);
        DB::table('fan_posts')->insert(['idx' => 53, 'idol_idx' => 1, 'member_idx' => 1, 'status' => 'published']);
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/53/recommendation')
            ->assertOk()->assertJsonPath('data.recommendations', 1);
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 53)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'fan_post_recommend_received')->count());
    }

    public function test_admin_recommendation_as_dummy_is_owned_and_audited_separately(): void
    {
        $this->database();
        DB::table('members')->insert([
            ['idx' => 2, 'member_code' => 'adminCode0001', 'email' => 'admin@example.test', 'nickname' => 'admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 3, 'member_code' => 'dummyCode0001', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'authorCode001', 'email' => 'author@example.test', 'nickname' => 'author', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active'],
        ]);
        DB::table('fan_posts')->insert([
            'idx' => 60, 'idol_idx' => 1, 'member_idx' => 4, 'title' => 'Delegated recommendation target',
            'content' => 'Public post', 'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 2])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));
        $this->postJson('/fan-post/60/recommendation')->assertOk()
            ->assertJsonPath('data.recommended', true)->assertJsonPath('data.recommendations', 1);

        $this->assertDatabaseHas('fan_post_likes', [
            'fan_post_idx' => 60, 'member_idx' => 3, 'actor_member_idx' => 2,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 4, 'actor_member_idx' => 2, 'reason' => 'fan_post_recommend_received',
            'operation_key' => 'fan_post_recommend_received:60:3',
        ]);
        $this->assertSame(1, (int) DB::table('members')->where('idx', 4)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
    }

    public function test_first_public_view_increments_once_and_sets_a_protected_cookie(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);

        $response = $this->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'fan_post_view_times');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertFalse($cookie->isSecure());
        $this->assertFalse(str_contains($cookie->getValue(), '"50"'));
        $this->assertSame([50], array_keys(json_decode($this->viewCookiePayload($response), true)));
    }

    public function test_view_cookie_is_secure_over_https(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);
        $response = $this->get(\Illuminate\Support\Uri::of('https://idolranking.test/fan-post/50'))->assertOk();
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'fan_post_view_times');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
    }

    public function test_repeat_view_does_not_increment_or_reset_the_window(): void
    {
        $this->database();
        $this->travelTo(now()->startOfMinute());
        $this->insertPublicViewPost(50);

        $first = $this->get('/fan-post/50')->assertOk();
        $payload = $this->viewCookiePayload($first);
        $this->travel(59)->minutes();
        $this->withCookie('fan_post_view_times', $payload)->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));

        $this->travel(1)->minutes();
        $this->withCookie('fan_post_view_times', $payload)->get('/fan-post/50')->assertOk();
        $this->assertSame(2, $this->postViewCount(50));
    }

    public function test_view_tracking_is_independent_per_post(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);
        $this->insertPublicViewPost(51);

        $first = $this->get('/fan-post/50')->assertOk();
        $second = $this->withCookie('fan_post_view_times', $this->viewCookiePayload($first))
            ->get('/fan-post/51')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));
        $this->assertSame(1, $this->postViewCount(51));

        $this->withCookie('fan_post_view_times', $this->viewCookiePayload($second))
            ->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));
    }

    public function test_view_cookie_is_independent_of_login_and_logout(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);

        $first = $this->get('/fan-post/50')->assertOk();
        $payload = $this->viewCookiePayload($first);
        $this->withCookie('fan_post_view_times', $payload)->withSession(['member_idx' => 1])
            ->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));

        $this->post('/logout')->assertRedirect();
        $this->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));
    }

    public function test_invalid_view_cookies_are_ignored_and_replaced(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);

        $response = $this->withUnencryptedCookie('fan_post_view_times', 'not-a-valid-encrypted-cookie')
            ->get('/fan-post/50')->assertOk();
        $this->assertSame(1, $this->postViewCount(50));
        $this->assertNotNull(collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'fan_post_view_times'));

        $this->withCookie('fan_post_view_times', json_encode(['50' => now()->addHour()->timestamp]))
            ->get('/fan-post/50')->assertOk();
        $this->assertSame(2, $this->postViewCount(50));
    }

    public function test_view_cookie_capacity_does_not_evict_active_entries(): void
    {
        $this->database();
        $this->insertPublicViewPost(50);
        $activeViews = array_fill_keys(range(100, 139), now()->timestamp);

        $response = $this->withCookie('fan_post_view_times', json_encode($activeViews))
            ->get('/fan-post/50')->assertOk();

        $this->assertSame(0, $this->postViewCount(50));
        $this->assertFalse(collect($response->headers->getCookies())
            ->contains(fn ($cookie) => $cookie->getName() === 'fan_post_view_times'));
    }

    private function insertPublicViewPost(int $idx): void
    {
        DB::table('fan_posts')->insert([
            'idx' => $idx, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'View test '.$idx, 'content' => 'Public view-count test.', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function postViewCount(int $idx): int
    {
        return (int) DB::table('fan_posts')->where('idx', $idx)->value('view_count');
    }

    private function viewCookiePayload($response): string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'fan_post_view_times');
        $this->assertNotNull($cookie);
        $decrypted = app('encrypter')->decrypt($cookie->getValue(), false);
        $payload = CookieValuePrefix::validate('fan_post_view_times', $decrypted, app('encrypter')->getAllKeys());
        $this->assertIsString($payload);

        return $payload;
    }

    public function test_member_can_report_another_published_post_once_with_valid_details(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 2, 'member_code' => 'memberCodeB2', 'email' => 'reporter@example.test',
            'nickname' => 'reporter', 'country_code' => 'US', 'password_hash' => Hash::make('Password1!'),
        ]);
        DB::table('fan_posts')->insert([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Reportable post', 'content' => 'Post body', 'status' => 'published',
        ]);

        $this->postJson('/fan-post/50/report', ['reason' => 'spam'])->assertUnauthorized();
        $this->withSession(['member_idx' => 2])->postJson('/fan-post/50/report', [
            'reason' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->postJson('/fan-post/50/report', [
            'reason' => 'other', 'details' => 'Additional context for review.',
        ])->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('fan_post_reports', [
            'fan_post_idx' => 50, 'member_idx' => 2, 'reason' => 'other',
            'details' => 'Additional context for review.', 'status' => 'pending',
        ]);
        $this->postJson('/fan-post/50/report', ['reason' => 'spam'])
            ->assertStatus(409);
        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/report', ['reason' => 'spam'])
            ->assertUnprocessable()->assertJsonPath('message', 'You cannot report your own Fan Post.');
    }

    private function createCommentReportFixture(int $commentMemberIdx = 1, array $postChanges = [], array $commentChanges = []): int
    {
        $post = array_replace([
            'idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1,
            'title' => 'Reportable post', 'content' => 'Public post body', 'status' => 'published',
            'created_at' => now(), 'updated_at' => now(),
        ], $postChanges);
        if (!DB::table('fan_posts')->where('idx', $post['idx'])->exists()) {
            DB::table('fan_posts')->insert($post);
        }

        return (int) DB::table('fan_post_comments')->insertGetId(array_replace([
            'fan_post_idx' => $post['idx'], 'member_idx' => $commentMemberIdx, 'actor_member_idx' => $commentMemberIdx,
            'content' => 'A public comment to review.', 'language_code' => 'en', 'status' => 'published',
            'deleted_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ], $commentChanges));
    }

    private function insertCommentReportMember(int $idx, int $level = 1, int $dummy = 0): void
    {
        DB::table('members')->insert([
            'idx' => $idx, 'member_code' => 'reportMember'.str_pad((string) $idx, 3, '0', STR_PAD_LEFT),
            'email' => 'report'.$idx.'@example.test', 'nickname' => 'reporter_'.$idx,
            'member_level' => $level, 'is_dummy' => $dummy, 'status' => 'active',
        ]);
    }

    public function test_comment_reports_require_login_prevent_duplicates_and_keep_dummy_actor_attribution(): void
    {
        $this->database();
        $this->insertCommentReportMember(2, 1, 1);
        $this->insertCommentReportMember(3, 10);
        $commentIdx = $this->createCommentReportFixture();

        $this->postJson('/fan-post/50/comments/'.$commentIdx.'/report', ['reason' => 'spam'])->assertUnauthorized();
        $session = ['member_idx' => 3, 'acting_dummy_member_idx' => 2];
        $this->withSession($session)->postJson('/fan-post/50/comments/'.$commentIdx.'/report', ['reason' => 'other'])
            ->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->withSession($session)->postJson('/fan-post/50/comments/'.$commentIdx.'/report', [
            'reason' => 'harassment_hate', 'details' => 'Please review this comment.',
        ])->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('fan_post_comment_reports', [
            'comment_idx' => $commentIdx, 'member_idx' => 2, 'actor_member_idx' => 3,
            'reason' => 'harassment_hate', 'status' => 'pending',
        ]);
        $this->withSession($session)->postJson('/fan-post/50/comments/'.$commentIdx.'/report', ['reason' => 'spam'])
            ->assertStatus(409);
        $this->assertSame('published', DB::table('fan_post_comments')->where('idx', $commentIdx)->value('status'));
    }

    public function test_comment_authors_cannot_report_their_own_comments(): void
    {
        $this->database();
        $commentIdx = $this->createCommentReportFixture();

        $this->withSession(['member_idx' => 1])->postJson('/fan-post/50/comments/'.$commentIdx.'/report', [
            'reason' => 'spam',
        ])->assertUnprocessable()->assertJsonPath('message', 'You cannot report your own comment.');
        $this->assertDatabaseMissing('fan_post_comment_reports', ['comment_idx' => $commentIdx]);
    }

    public function test_comment_report_button_is_only_shown_for_other_members_and_guests_get_sign_in_trigger(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $this->createCommentReportFixture(2);

        $this->get('/fan-post/50')->assertOk()
            ->assertSee('data-comment-report-open', false)
            ->assertSee('data-signed-in="false"', false)
            ->assertSee('data-sign-in-open', false);
        $this->withSession(['member_idx' => 1])->get('/fan-post/50')->assertOk()
            ->assertSee('data-comment-report-open', false)
            ->assertSee('data-signed-in="true"', false);
        $this->withSession(['member_idx' => 2])->get('/fan-post/50')->assertOk()
            ->assertSee('data-comment-delete', false)
            ->assertDontSee('data-comment-report-open', false);
    }

    public function test_comment_reports_reject_hidden_or_deleted_comments_and_inaccessible_posts(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $session = ['member_idx' => 2];

        $hiddenComment = $this->createCommentReportFixture(1, [], ['status' => 'hidden']);
        $deletedComment = $this->createCommentReportFixture(1, [], ['deleted_at' => now()]);
        $hiddenPostComment = $this->createCommentReportFixture(1, ['idx' => 51, 'status' => 'hidden']);

        foreach ([$hiddenComment, $deletedComment, $hiddenPostComment] as $commentIdx) {
            $postIdx = $commentIdx === $hiddenPostComment ? 51 : 50;
            $this->withSession($session)->postJson('/fan-post/'.$postIdx.'/comments/'.$commentIdx.'/report', [
                'reason' => 'spam',
            ])->assertNotFound();
        }
        $this->assertSame(0, DB::table('fan_post_comment_reports')->count());
    }

    public function test_comment_report_admin_permissions_status_and_soft_hide_preserve_heart_history(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $this->insertCommentReportMember(3, 10);
        $commentIdx = $this->createCommentReportFixture();
        DB::table('fan_post_comment_reports')->insert([
            'comment_idx' => $commentIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'reason' => 'spam', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $historyCount = DB::table('heart_histories')->count();

        $this->get('/admin/fan-post-comment-reports')->assertRedirect('/');
        $this->withSession(['member_idx' => 2])->get('/admin/fan-post-comment-reports')->assertForbidden();
        $this->withSession(['member_idx' => 3])->get('/admin/fan-post-comment-reports')
            ->assertOk()->assertSee('reporter_2')->assertSee('A public comment to review.');
        $this->withSession(['member_idx' => 3])->patch('/admin/fan-post-comment-reports/1/status', [
            'status' => 'reviewing', 'reason' => '검토 시작',
        ])->assertRedirect();
        $this->assertDatabaseHas('fan_post_comment_reports', ['idx' => 1, 'status' => 'reviewing']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'fan_post_comment_report_status_changed']);

        $this->withSession(['member_idx' => 3])->patch('/admin/fan-post-comments/'.$commentIdx.'/visibility', [
            'reason' => '운영정책 위반',
        ])->assertRedirect();
        $this->assertDatabaseHas('fan_post_comments', ['idx' => $commentIdx, 'status' => 'hidden']);
        $this->assertNotNull(DB::table('fan_post_comments')->where('idx', $commentIdx)->value('deleted_at'));
        $this->assertSame($historyCount, DB::table('heart_histories')->count());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'fan_post_comment_hidden']);
    }

    public function test_admin_can_restore_moderator_hidden_comment_without_changing_related_records(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $this->insertCommentReportMember(3, 10);
        $commentIdx = $this->createCommentReportFixture();
        DB::table('fan_post_comment_reports')->insert([
            'comment_idx' => $commentIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'reason' => 'spam', 'status' => 'reviewing', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('fan_post_comment_translations')->insert([
            'comment_idx' => $commentIdx, 'language_code' => 'ko', 'translated_content' => '번역 보존',
            'source_hash' => hash('sha256', 'A public comment to review.'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $originalContent = DB::table('fan_post_comments')->where('idx', $commentIdx)->value('content');
        $heartHistoryCount = DB::table('heart_histories')->count();
        $adminSession = ['member_idx' => 3];

        $this->withSession($adminSession)->patch('/admin/fan-post-comments/'.$commentIdx.'/visibility', [
            'reason' => '운영 정책 위반',
        ])->assertRedirect();
        $this->assertDatabaseHas('fan_post_comments', ['idx' => $commentIdx, 'status' => 'hidden']);

        $this->withSession($adminSession)->patch('/admin/fan-post-comments/'.$commentIdx.'/restore', [
            'reason' => '재검토 결과 공개',
        ])->assertRedirect();

        $this->assertDatabaseHas('fan_post_comments', [
            'idx' => $commentIdx, 'status' => 'published', 'deleted_at' => null, 'content' => $originalContent,
        ]);
        $this->assertDatabaseHas('fan_post_comment_translations', [
            'comment_idx' => $commentIdx, 'translated_content' => '번역 보존',
        ]);
        $this->assertDatabaseHas('fan_post_comment_reports', ['comment_idx' => $commentIdx, 'status' => 'reviewing']);
        $this->assertSame($heartHistoryCount, DB::table('heart_histories')->count());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'fan_post_comment_restored', 'target_idx' => $commentIdx]);
    }

    public function test_comment_restore_requires_admin_and_refuses_author_deleted_comments(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $this->insertCommentReportMember(3, 10);
        $moderatorHiddenIdx = $this->createCommentReportFixture();
        $authorDeletedIdx = $this->createCommentReportFixture(1, ['idx' => 51], [
            'status' => 'hidden', 'deleted_at' => now(),
        ]);
        DB::table('admin_activity_logs')->insert([
            'actor_member_idx' => 3, 'action' => 'fan_post_comment_hidden',
            'target_type' => 'fan_post_comment', 'target_idx' => $moderatorHiddenIdx,
            'reason' => 'moderation', 'summary' => 'hidden', 'created_at' => now(),
        ]);
        DB::table('fan_post_comments')->where('idx', $moderatorHiddenIdx)->update([
            'status' => 'hidden', 'deleted_at' => now(),
        ]);
        foreach ([$moderatorHiddenIdx, $authorDeletedIdx] as $commentIdx) {
            DB::table('fan_post_comment_reports')->insert([
                'comment_idx' => $commentIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
                'reason' => 'spam', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->withSession(['member_idx' => 2])->patch('/admin/fan-post-comments/'.$moderatorHiddenIdx.'/restore', [
            'reason' => 'unauthorized',
        ])->assertForbidden();
        $this->withSession(['member_idx' => 3])->get('/admin/fan-post-comment-reports')
            ->assertOk()
            ->assertSee(route('admin.fan-post-comments.restore', $moderatorHiddenIdx), false)
            ->assertDontSee(route('admin.fan-post-comments.restore', $authorDeletedIdx), false)
            ->assertSee('작성자 삭제');
        $this->withSession(['member_idx' => 3])->patch('/admin/fan-post-comments/'.$authorDeletedIdx.'/restore', [
            'reason' => 'must remain deleted',
        ])->assertStatus(409);
        $this->assertDatabaseHas('fan_post_comments', [
            'idx' => $authorDeletedIdx, 'status' => 'hidden',
        ]);
        $this->assertNotNull(DB::table('fan_post_comments')->where('idx', $authorDeletedIdx)->value('deleted_at'));
    }

    public function test_comment_report_received_time_is_converted_from_utc_to_kst(): void
    {
        $this->database();
        $this->insertCommentReportMember(2);
        $this->insertCommentReportMember(3, 10);
        $commentIdx = $this->createCommentReportFixture();
        DB::table('fan_post_comment_reports')->insert([
            'comment_idx' => $commentIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'reason' => 'spam', 'status' => 'pending',
            'created_at' => '2026-10-16 15:30:00', 'updated_at' => '2026-10-16 15:30:00',
        ]);

        $this->withSession(['member_idx' => 3])->get('/admin/fan-post-comment-reports')
            ->assertOk()->assertSee('2026-10-17 00:30')
            ->assertSee('datetime="2026-10-17T00:30:00+09:00"', false);
    }

    public function test_admin_hidden_fan_post_redirects_to_public_list_with_localized_one_time_message(): void
    {
        $this->database();
        $this->createCommentReportFixture(1, [
            'title' => 'Hidden from public', 'status' => 'hidden',
        ]);

        $this->withSession(['locale' => 'en'])->get('/fan-post/50?sort=liked&page=4')
            ->assertStatus(302)
            ->assertRedirect(route('fan-posts.index', ['sort' => 'liked', 'page' => 4]))
            ->assertSessionHas('global_notification', fn (array $notification) =>
                $notification['message'] === 'This Fan Post is no longer publicly available.'
            );

        $referer = route('fan-posts.index', ['sort' => 'monthly_liked', 'page' => 2]);
        $this->withSession(['locale' => 'ko'])->withHeader('referer', $referer)
            ->get('/fan-post/50')
            ->assertStatus(302)
            ->assertRedirect($referer)
            ->assertSessionHas('global_notification', fn (array $notification) =>
                $notification['message'] === '비공개 처리된 팬포스트입니다.'
            );
    }

    public function test_missing_deleted_and_inactive_fan_posts_keep_their_404_behavior(): void
    {
        $this->database();
        $this->createCommentReportFixture(1, ['title' => 'Deleted hidden post', 'status' => 'hidden']);
        DB::table('fan_posts')->where('idx', 50)->update(['deleted_at' => now()]);
        $this->get('/fan-post/50')->assertNotFound()
            ->assertSee('We can’t find this page')
            ->assertSee('name="robots" content="noindex, nofollow"', false);
        $this->get('/fan-post/999999')->assertNotFound()
            ->assertSee('We can’t find this page');

        DB::table('fan_posts')->where('idx', 50)->update(['deleted_at' => null]);
        DB::table('members')->where('idx', 1)->update(['status' => 'suspended']);
        $this->get('/fan-post/50')->assertNotFound();
    }

    public function test_author_can_edit_fan_post_text_without_changing_image_or_likes(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('fan-posts/original.jpg', 'original-image');
        $this->createCommentReportFixture(1, [
            'media_type' => 'image', 'image_path' => 'storage/fan-posts/original.jpg', 'language_code' => 'ko', 'view_count' => 9,
        ]);
        DB::table('idols')->insert(['idx' => 2, 'name' => 'Another Idol', 'status' => 'active']);
        DB::table('fan_post_tags')->insert(['fan_post_idx' => 50, 'tag' => 'original-tag']);
        DB::table('fan_post_likes')->insert(['fan_post_idx' => 50, 'member_idx' => 1, 'actor_member_idx' => 1]);
        DB::table('fan_post_comments')->insert([
            'fan_post_idx' => 50, 'member_idx' => 1, 'actor_member_idx' => 1,
            'request_key' => 'edit-preserve-comment', 'content' => 'Existing comment', 'language_code' => 'ko',
            'status' => 'published', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $editResponse = $this->withSession(['member_idx' => 1])->get('/fan-post/50/edit')->assertOk();
        $editHtml = $editResponse->getContent();
        $sectionPositions = array_map(fn (string $marker): int|false => strpos($editHtml, $marker), [
            'for="fan-post-idol"', 'for="fan-post-title"', 'for="fan-post-language"',
            'id="fan-post-media-label"', 'for="fan-post-content"', 'for="fan-post-tag-entry"',
        ]);
        $this->assertNotContains(false, $sectionPositions);
        $sortedPositions = $sectionPositions;
        sort($sortedPositions);
        $this->assertSame($sortedPositions, $sectionPositions);

        $editResponse
            ->assertSee('name="title"', false)
            ->assertSee('name="content"', false)
            ->assertSee('name="tags"', false)
            ->assertDontSee('name="idol_idx"', false)
            ->assertDontSee('name="language_code"', false)
            ->assertSee('Demo Idol')
            ->assertSee('(KO)')
            ->assertDontSee('data-media-panel', false)
            ->assertSee('disabled aria-disabled="true"', false)
            ->assertSee(asset('storage/fan-posts/original.jpg'), false)
            ->assertSee(__('ui.fan_post_media_immutable'))
            ->assertDontSee('data-image-input', false)
            ->assertDontSee('Media Guidelines')
            ->assertDontSee('이미지를 자르거나 편집할 수 있습니다.')
            ->assertDontSee('data-crop-dialog', false);

        $this->put('/fan-post/50', [
            'title' => 'Updated title', 'content' => 'Updated body', 'tags' => 'updated-tag,second_tag',
        ])->assertRedirect(route('fan-post.show', ['idx' => 50]));

        $this->assertDatabaseHas('fan_posts', [
            'idx' => 50, 'title' => 'Updated title', 'content' => 'Updated body',
            'idol_idx' => 1, 'language_code' => 'ko', 'media_type' => 'image',
            'image_path' => 'storage/fan-posts/original.jpg', 'view_count' => 9,
        ]);
        $this->assertSame(['updated-tag', 'second_tag'], DB::table('fan_post_tags')->where('fan_post_idx', 50)->orderBy('idx')->pluck('tag')->all());
        $this->assertSame(1, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->assertSame(2, DB::table('fan_post_comments')->where('fan_post_idx', 50)->count());
    }

    public function test_fan_post_text_edit_is_allowed_with_zero_likes(): void
    {
        $this->database();
        $this->createCommentReportFixture(1, ['title' => 'No likes yet']);

        $this->withSession(['member_idx' => 1])->put('/fan-post/50', [
            'title' => 'Edited without likes', 'content' => 'Updated text',
        ])->assertRedirect(route('fan-post.show', ['idx' => 50]));

        $this->assertSame('Edited without likes', DB::table('fan_posts')->where('idx', 50)->value('title'));
    }

    public function test_fan_post_edit_shows_youtube_media_as_a_read_only_preview(): void
    {
        $this->database();
        $this->createCommentReportFixture(1, [
            'media_type' => 'youtube', 'youtube_video_id' => 'abcdefghijk', 'language_code' => 'en',
        ]);

        $this->withSession(['member_idx' => 1])->get('/fan-post/50/edit')->assertOk()
            ->assertSee('https://www.youtube.com/watch?v=abcdefghijk', false)
            ->assertSee(__('ui.youtube_video'))
            ->assertSee(__('ui.fan_post_media_immutable'))
            ->assertDontSee('name="youtube_url"', false)
            ->assertDontSee('name="media_type"', false);
    }

    public function test_fan_post_edit_rejects_protected_field_mutation_attempts(): void
    {
        $this->database();
        Storage::fake('public');
        $this->createCommentReportFixture(1, [
            'media_type' => 'image', 'image_path' => 'storage/fan-posts/original.jpg', 'language_code' => 'ko',
        ]);
        $session = $this->withSession(['member_idx' => 1]);

        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'idol_idx' => 1, 'title' => 'Attempted replacement', 'content' => 'Body', 'language_code' => 'en',
            'media_type' => 'image', 'tags' => '', 'image' => \Illuminate\Http\UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHasErrors('image');
        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'idol_idx' => 1, 'title' => 'Attempted removal', 'content' => 'Body', 'language_code' => 'en',
            'media_type' => 'youtube', 'youtube_url' => 'https://youtu.be/abcdefghijk', 'tags' => '',
        ])->assertSessionHasErrors('media_type');
        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'idol_idx' => 1, 'title' => 'Attempted path edit', 'content' => 'Body', 'language_code' => 'en',
            'media_type' => 'image', 'image_path' => 'storage/fan-posts/other.jpg', 'tags' => '',
        ])->assertSessionHasErrors('image_path');
        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'idol_idx' => 1, 'title' => 'Attempted reorder', 'content' => 'Body', 'language_code' => 'en',
            'media_type' => 'image', 'image_order' => [1], 'tags' => '',
        ])->assertSessionHasErrors('image_order');

        $this->createCommentReportFixture(1, ['idx' => 51, 'media_type' => 'text']);
        $session->from('/fan-post/51/edit')->put('/fan-post/51', [
            'idol_idx' => 1, 'title' => 'Attempted image addition', 'content' => 'Body', 'language_code' => 'en',
            'media_type' => 'image', 'tags' => '',
        ])->assertSessionHasErrors('media_type');

        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'title' => 'Attempted idol change', 'content' => 'Body', 'idol_idx' => 2,
        ])->assertSessionHasErrors('idol_idx');
        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'title' => 'Attempted language change', 'content' => 'Body', 'language_code' => 'en',
        ])->assertSessionHasErrors('language_code');
        $session->from('/fan-post/50/edit')->put('/fan-post/50', [
            'title' => 'Allowed tag change', 'content' => 'Body', 'tags' => 'new-tag',
        ])->assertRedirect(route('fan-post.show', ['idx' => 50]));

        $this->assertSame('storage/fan-posts/original.jpg', DB::table('fan_posts')->where('idx', 50)->value('image_path'));
        $this->assertSame(1, (int) DB::table('fan_posts')->where('idx', 50)->value('idol_idx'));
        $this->assertSame('ko', DB::table('fan_posts')->where('idx', 50)->value('language_code'));
        $this->assertSame(['new-tag'], DB::table('fan_post_tags')->where('fan_post_idx', 50)->pluck('tag')->all());
    }

    public function test_author_can_delete_a_fan_post_and_representative_images_fall_back_in_order(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('fan-posts/first.jpg', 'first-image');
        Storage::disk('public')->put('fan-posts/second.jpg', 'second-image');
        DB::table('idols')->where('idx', 1)->update(['image_path' => 'storage/idols/profile.jpg']);
        DB::table('fan_posts')->insert([
            ['idx' => 50, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'First image', 'media_type' => 'image', 'image_path' => 'storage/fan-posts/first.jpg', 'content' => 'Body', 'status' => 'published', 'view_count' => 0, 'created_at' => '2026-10-01 10:00:00', 'updated_at' => '2026-10-01 10:00:00'],
            ['idx' => 51, 'idol_idx' => 1, 'member_idx' => 1, 'actor_member_idx' => 1, 'title' => 'Second image', 'media_type' => 'image', 'image_path' => 'storage/fan-posts/second.jpg', 'content' => 'Body', 'status' => 'published', 'view_count' => 0, 'created_at' => '2026-10-02 10:00:00', 'updated_at' => '2026-10-02 10:00:00'],
        ]);
        DB::table('fan_post_likes')->insert([
            ['fan_post_idx' => 50, 'member_idx' => 1], ['fan_post_idx' => 50, 'member_idx' => 2],
            ['fan_post_idx' => 51, 'member_idx' => 2],
        ]);
        $ranking = app(\App\Support\PublicRanking::class);
        $this->assertStringContainsString('first.jpg', $ranking->representativeImages(1, 'profile.jpg')[0]);

        $this->withSession(['member_idx' => 1])->delete('/fan-post/50')
            ->assertRedirect(route('fan-posts.index'));
        $this->assertNotNull(DB::table('fan_posts')->where('idx', 50)->value('deleted_at'));
        $this->assertSame(2, DB::table('fan_post_likes')->where('fan_post_idx', 50)->count());
        $this->get('/fan-post/50')->assertNotFound();
        $this->get(route('fan-posts.index'))->assertOk()->assertDontSee('First image');
        $remaining = $ranking->representativeImages(1, 'profile.jpg');
        $this->assertCount(1, $remaining);
        $this->assertStringContainsString('second.jpg', $remaining[0]);

        $this->withSession(['member_idx' => 1])->delete('/fan-post/51')->assertRedirect(route('fan-posts.index'));
        $this->assertSame(['profile.jpg'], $ranking->representativeImages(1, 'profile.jpg'));
        $this->assertSame([\App\Support\ManagedImageStorage::defaultUrl()], $ranking->representativeImages(1, null));
    }

    public function test_only_the_fan_post_author_can_edit_or_delete(): void
    {
        $this->database();
        $this->insertOtherMember(2);
        $this->createCommentReportFixture();

        $this->withSession(['member_idx' => 2])->get('/fan-post/50/edit')->assertNotFound();
        $this->withSession(['member_idx' => 2])->put('/fan-post/50', [])->assertNotFound();
        $this->withSession(['member_idx' => 2])->delete('/fan-post/50')->assertNotFound();
        $this->assertNull(DB::table('fan_posts')->where('idx', 50)->value('deleted_at'));
    }

    public function test_unknown_url_renders_localized_custom_404_without_redirecting(): void
    {
        $this->database();

        $this->withSession(['locale' => 'en'])->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('We can’t find this page')
            ->assertSee('Go Home')
            ->assertSee('href="'.route('home').'"', false)
            ->assertSee('name="robots" content="noindex, nofollow"', false);

        $this->withSession(['locale' => 'ko'])->get('/also-not-a-real-page')
            ->assertNotFound()
            ->assertSee('페이지를 찾을 수 없습니다')
            ->assertSee('홈으로 이동');
    }
}
