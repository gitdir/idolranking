<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        config(['services.google_translate.api_key' => null]);
        Http::preventStrayRequests();

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT UNIQUE, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement('CREATE TABLE daily_check_ins (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER NOT NULL, actor_member_idx INTEGER NOT NULL, language_code TEXT, content TEXT NOT NULL, awarded_hearts INTEGER NOT NULL, check_in_date TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE (member_idx, check_in_date))');
        DB::statement('CREATE TABLE daily_check_in_translations (idx INTEGER PRIMARY KEY AUTOINCREMENT, daily_check_in_idx INTEGER NOT NULL, source_language_code TEXT NOT NULL, target_language_code TEXT NOT NULL, translated_content TEXT NOT NULL, source_hash TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE (daily_check_in_idx, source_language_code, target_language_code))');
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, min_heart_amount INTEGER, max_heart_amount INTEGER, daily_limit INTEGER, is_enabled INTEGER)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT UNIQUE, actor_member_idx INTEGER, memo TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_path TEXT, target_url TEXT, heart_reward INTEGER DEFAULT 10, reward_enabled INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, display_order INTEGER DEFAULT 0, starts_at TEXT, ends_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE partner_banner_clicks (idx INTEGER PRIMARY KEY AUTOINCREMENT, banner_idx INTEGER NOT NULL, member_idx INTEGER NULL, clicked_at TEXT NOT NULL)');
        DB::table('heart_reward_settings')->insert([
            'action_code' => 'daily_attendance', 'heart_amount' => 10, 'min_heart_amount' => 10,
            'max_heart_amount' => 10, 'daily_limit' => 1, 'is_enabled' => 1,
        ]);
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'PublicMemberCode1', 'email' => 'one@example.test', 'nickname' => 'public_fan', 'country_code' => 'KR', 'status' => 'active'],
            ['idx' => 2, 'member_code' => 'HiddenMemberCode2', 'email' => 'two@example.test', 'nickname' => 'hidden_fan', 'country_code' => 'US', 'status' => 'inactive'],
        ]);
    }

    private function addCheckIn(int $memberIdx, string $content, string $language = 'en', ?string $date = null, ?string $createdAt = null): int
    {
        $checkInIdx = (int) DB::table('daily_check_ins')->insertGetId([
            'member_idx' => $memberIdx,
            'actor_member_idx' => $memberIdx,
            'language_code' => $language,
            'content' => $content,
            'awarded_hearts' => 17,
            'check_in_date' => $date ?? '2026-10-16',
            'created_at' => $createdAt ?? now('UTC'),
            'updated_at' => $createdAt ?? now('UTC'),
        ]);
        DB::table('heart_histories')->insert([
            'member_idx' => $memberIdx, 'amount' => 17, 'balance_after' => 17,
            'reason' => 'daily_attendance', 'operation_key' => 'daily_attendance:'.$memberIdx.':'.($date ?? '2026-10-16'),
            'actor_member_idx' => $memberIdx, 'created_at' => $createdAt ?? now('UTC'),
        ]);

        return $checkInIdx;
    }

    public function test_public_feed_hides_unavailable_members_and_paginates_newest_first(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->addCheckIn(1, 'Public message '.$i, 'en', now('UTC')->subDays($i)->toDateString(), now('UTC')->subDays($i));
        }
        $this->addCheckIn(2, 'Private member message', 'en', '2025-01-01');

        $feedResponse = $this->get(route('attendance'));
        $feedResponse
            ->assertOk()->assertSee('Community Check-ins')->assertSee('Public message 0')
            ->assertSee('<html lang="en">', false)
            ->assertSee('<link rel="canonical" href="'.route('attendance').'">', false)
            ->assertSee('class="drawer-category-link header-attendance"', false)
            ->assertDontSee('Private member message')->assertSee('+17 Heart')
            ->assertSee('title="EN" aria-label="EN"', false)
            ->assertDontSee('title="South Korea · EN"', false)
            ->assertSee('src="'.asset('images/flags/kr.svg').'"', false)
            ->assertDontSee('attendance-card-country')
            ->assertSee('href="'.route('attendance').'?page=2"', false)
            ->assertDontSee('member_idx')->assertDontSee('actor_member_idx');
        $this->assertMatchesRegularExpression(
            '~<a class="drawer-category-link header-attendance" href="'.preg_quote(route('attendance'), '~').'"\s+aria-current="page"~',
            $feedResponse->getContent()
        );

        $this->get(route('attendance', ['page' => 2]))
            ->assertOk()->assertSee('Public message 24')->assertDontSee('Public message 0');
    }

    public function test_feed_renders_check_in_composer_and_links_to_existing_reward_endpoint(): void
    {
        $response = $this->withSession(['member_idx' => 1])->get(route('attendance'))->assertOk()
            ->assertSee('data-attendance-check-in-form', false)
            ->assertSee('maxlength="200"', false)
            ->assertSee('data-endpoint="'.route('get-hearts.daily-check-in').'"', false);
        $this->assertSame(2, substr_count($response->getContent(), 'src="'.asset('images/flags/kr.svg').'" width="18" height="13"'));

        DB::table('members')->where('idx', 1)->update(['country_code' => 'ZZ']);
        $this->withSession(['member_idx' => 1])->get(route('attendance'))->assertOk()
            ->assertDontSee('src="'.asset('images/flags/zz.svg').'"', false);

        $this->withSession(['member_idx' => 1])->get(route('get-hearts'))->assertOk()
            ->assertSee('href="'.route('attendance').'"', false)
            ->assertDontSee('data-daily-check-in-form', false);
    }

    public function test_completed_utc_day_shows_the_actual_awarded_heart_amount(): void
    {
        DB::table('daily_check_ins')->insert([
            'member_idx' => 1, 'actor_member_idx' => 1, 'language_code' => 'en', 'content' => 'Today <script>alert(1)</script>',
            'awarded_hearts' => 17, 'check_in_date' => now('UTC')->toDateString(), 'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        DB::table('heart_histories')->insert([
            'member_idx' => 1, 'amount' => 17, 'balance_after' => 17, 'reason' => 'daily_attendance',
            'operation_key' => 'daily_attendance:1:'.now('UTC')->toDateString(), 'actor_member_idx' => 1,
            'created_at' => now('UTC'),
        ]);

        $this->withSession(['member_idx' => 1])->get(route('attendance'))->assertOk()
            ->assertSee('You have checked in today')->assertSee('+17 Heart')
            ->assertSee('Today &lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('data-attendance-check-in-form', false);
    }

    public function test_valid_zero_reward_is_recorded_and_displayed(): void
    {
        DB::table('heart_reward_settings')->where('action_code', 'daily_attendance')
            ->update(['min_heart_amount' => 0, 'max_heart_amount' => 0]);
        $zeroReward = $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.daily-check-in'), [
            'content' => 'A valid zero reward check-in', 'language_code' => 'en',
        ]);
        $zeroReward->assertOk()->assertJsonPath('data.reward_awarded', true)
            ->assertJsonPath('data.amount', 0)->assertJsonPath('message', __('messages.daily_awarded', ['amount' => '0']));
        $this->assertStringContainsString('+0 Heart', $zeroReward->json('data.card_html'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'amount' => 0, 'reason' => 'daily_attendance',
            'operation_key' => 'daily_attendance:1:'.now('UTC')->toDateString(),
        ]);
        $this->withSession(['member_idx' => 1])->get(route('attendance'))->assertSee('+0 Heart');
    }

    public function test_successful_check_in_response_drives_the_completed_page_state_and_retry_uses_saved_data(): void
    {
        $submittedMessage = 'My community check-in for today.';
        $session = ['member_idx' => 1];

        $response = $this->withSession($session)->postJson(route('get-hearts.daily-check-in'), [
            'content' => $submittedMessage,
            'language_code' => 'en',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reward_awarded', true)
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('data.content', $submittedMessage)
            ->assertJsonPath('data.language_code', 'en');
        $cardHtml = $response->json('data.card_html');
        $this->assertStringContainsString('id="attendance-card-1"', $cardHtml);
        $this->assertStringContainsString('data-check-in-id="1"', $cardHtml);
        $this->assertStringContainsString('+10 Heart', $cardHtml);
        $this->assertStringContainsString(e($submittedMessage), $cardHtml);

        $this->withSession($session)->get(route('attendance'))->assertOk()
            ->assertDontSee('data-attendance-check-in-form', false)
            ->assertSee('data-attendance-completed', false)
            ->assertSee('+10 Heart')
            ->assertSee($submittedMessage);

        $retry = $this->withSession($session)->postJson(route('get-hearts.daily-check-in'), [
            'content' => 'A different retry message',
            'language_code' => 'ko',
        ]);
        $retry->assertStatus(409)
            ->assertJsonPath('already_claimed', true)
            ->assertJsonPath('data.reward_awarded', true)
            ->assertJsonPath('data.amount', 10)
            ->assertJsonPath('data.content', $submittedMessage)
            ->assertJsonPath('data.language_code', 'en')
            ->assertJsonPath('data.check_in_id', 1);
        $this->assertSame($cardHtml, $retry->json('data.card_html'));

        $this->assertSame(1, DB::table('daily_check_ins')->count());
        $this->assertSame(1, DB::table('heart_histories')->count());
    }

    public function test_admin_dummy_mode_receives_check_in_reward_but_direct_dummy_does_not(): void
    {
        DB::table('members')->insert([
            ['idx' => 3, 'member_code' => 'AdminAttendance3', 'email' => 'admin@example.test', 'nickname' => 'admin', 'country_code' => 'KR', 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active'],
            ['idx' => 4, 'member_code' => 'DummyAttendance4', 'email' => 'dummy@example.test', 'nickname' => 'dummy', 'country_code' => 'KR', 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 5, 'member_code' => 'DummyAttendance5', 'email' => 'dummy2@example.test', 'nickname' => 'dummy2', 'country_code' => 'KR', 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active'],
        ]);

        $this->withSession(['member_idx' => 3])->postJson(route('get-hearts.daily-check-in'), [
            'content' => 'Admin check-in as selected dummy', 'language_code' => 'en',
        ])->assertOk()->assertJsonPath('data.reward_awarded', true)->assertJsonPath('data.amount', 10);

        $this->withSession(['member_idx' => 5])->postJson(route('get-hearts.daily-check-in'), [
            'content' => 'Direct dummy check-in', 'language_code' => 'en',
        ])->assertOk()->assertJsonPath('data.reward_awarded', false)->assertJsonPath('data.amount', null)
            ->assertJsonPath('message', __('messages.daily_checked_in'))->assertDontSee('Heart');

        $this->assertSame(1, DB::table('heart_histories')->count());
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 4, 'actor_member_idx' => 3, 'reason' => 'daily_attendance', 'amount' => 10,
        ]);
        $this->assertSame(10, (int) DB::table('members')->where('idx', 4)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->whereIn('idx', [3, 5])->sum('heart_balance'));
    }

    public function test_reward_history_failure_rolls_back_check_in_instead_of_recording_a_zero_reward(): void
    {
        DB::statement("CREATE TRIGGER fail_attendance_reward BEFORE INSERT ON heart_histories BEGIN SELECT RAISE(ABORT, 'forced reward insert failure'); END");

        $this->withSession(['member_idx' => 1])->postJson(route('get-hearts.daily-check-in'), [
            'content' => 'Reward failure must not look like zero', 'language_code' => 'en',
        ])->assertServerError();

        $this->assertSame(0, DB::table('daily_check_ins')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_attendance_card_shows_member_flag_by_nickname_and_language_code_only(): void
    {
        $this->addCheckIn(1, 'Japanese message', 'ja');

        $this->withSession(['locale' => 'ko'])->get(route('attendance'))->assertOk()
            ->assertSee('title="JA" aria-label="JA"', false)
            ->assertSee('src="'.asset('images/flags/kr.svg').'"', false);
    }

    public function test_long_attendance_messages_remain_in_the_card_message_flow(): void
    {
        $message = str_repeat('LongAttendanceWord', 30);
        $this->addCheckIn(1, $message);

        $this->get(route('attendance'))->assertOk()->assertSee($message, false);
        $stylesheet = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.attendance-card-message { margin: 0; overflow-wrap: anywhere; white-space: pre-wrap; }', $stylesheet);
        $this->assertStringContainsString('.attendance-card { display: block; width: 100%;', $stylesheet);
    }

    public function test_card_translation_returns_only_a_matching_cached_translation(): void
    {
        Http::fake();
        $checkInIdx = $this->addCheckIn(1, "  Original\nmessage  ", 'en');
        DB::table('daily_check_in_translations')->insert([
            'daily_check_in_idx' => $checkInIdx, 'source_language_code' => 'en', 'target_language_code' => 'ko',
            'translated_content' => '번역된 메시지', 'source_hash' => hash('sha256', "  Original\nmessage  "),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $checkInIdx, 'language' => 'ko']))
            ->assertOk()->assertJsonPath('data.content', '번역된 메시지');
        Http::assertNothingSent();

        DB::table('daily_check_in_translations')->update(['source_hash' => str_repeat('0', 64)]);
        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $checkInIdx, 'language' => 'ko']))
            ->assertStatus(503)->assertJsonPath('code', 'translation_error');
        Http::assertNothingSent();
    }

    public function test_translation_cache_miss_uses_plain_text_google_and_stores_exact_source_hash(): void
    {
        config(['services.google_translate.api_key' => 'test-translation-key']);
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'data' => ['translations' => [['translatedText' => '안녕하세요']]],
        ])]);
        $checkInIdx = $this->addCheckIn(1, "Hi\nthere", 'en');

        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $checkInIdx, 'language' => 'ko']))
            ->assertOk()->assertJsonPath('data.content', '안녕하세요');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://translation.googleapis.com/language/translate/v2?key=test-translation-key'
            && $request['q'] === "Hi\nthere" && $request['source'] === 'en'
            && $request['target'] === 'ko' && $request['format'] === 'text');
        $this->assertDatabaseHas('daily_check_in_translations', [
            'daily_check_in_idx' => $checkInIdx,
            'source_language_code' => 'en',
            'target_language_code' => 'ko',
            'source_hash' => hash('sha256', "Hi\nthere"),
            'translated_content' => '안녕하세요',
        ]);
    }

    public function test_translation_rejects_hidden_check_ins_and_noncurrent_targets(): void
    {
        $hiddenIdx = $this->addCheckIn(2, 'Hidden message', 'en');
        $publicIdx = $this->addCheckIn(1, 'Public message', 'en', '2026-10-15');

        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $hiddenIdx, 'language' => 'ko']))
            ->assertNotFound();
        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $publicIdx, 'language' => 'en']))
            ->assertUnprocessable()->assertJsonPath('code', 'translation_target_invalid');
        Http::assertNothingSent();
    }

    public function test_failed_google_response_is_not_cached(): void
    {
        config(['services.google_translate.api_key' => 'test-translation-key']);
        Http::fake(['*translation.googleapis.com/language/translate/v2*' => Http::response([
            'error' => ['code' => 429, 'message' => 'quota exceeded'],
        ], 429)]);
        $checkInIdx = $this->addCheckIn(1, 'Hello from the community', 'en');

        $this->withSession(['locale' => 'ko'])->getJson(route('attendance.translation', ['idx' => $checkInIdx, 'language' => 'ko']))
            ->assertStatus(503)->assertJsonPath('code', 'translation_error');
        $this->assertDatabaseCount('daily_check_in_translations', 0);
    }
}
