<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\QuizWeeklyParticipationRanking;
use Carbon\Carbon;
use Tests\TestCase;

class QuizFeatureTest extends TestCase
{
    private int $quizIdx;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Seoul']);
        Storage::fake('public');

        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT UNIQUE, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT "active", deleted_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, name TEXT, status TEXT DEFAULT "active")');
        DB::statement('ALTER TABLE idols ADD COLUMN english_name TEXT');
        DB::statement('CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, language_code TEXT, localized_name TEXT)');
        DB::statement('CREATE TABLE idol_name_aliases (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, language_code TEXT, alias_name TEXT)');
        DB::statement('CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, status TEXT, deleted_at TEXT)');
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)');
        DB::statement('CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, min_heart_amount INTEGER, max_heart_amount INTEGER, daily_limit INTEGER, is_enabled INTEGER, updated_at TEXT)');
        DB::statement('CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE(member_idx, operation_key))');
        DB::statement('CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE member_access_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, visitor_id TEXT, is_bot INTEGER DEFAULT 0, ip_address TEXT, request_path TEXT NOT NULL, user_agent TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE admin_dummy_activity_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, member_idx INTEGER, action_code TEXT, request_key TEXT, payload_hash TEXT, result_type TEXT, result_idx INTEGER, created_at TEXT, completed_at TEXT, updated_at TEXT, UNIQUE(actor_member_idx, action_code, request_key))');
        DB::statement('CREATE TABLE quizzes (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, actor_member_idx INTEGER, title TEXT, idol_idx INTEGER, cover_image_path TEXT, question_count INTEGER, quiz_version INTEGER DEFAULT 1, deleted_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE quiz_creation_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, quiz_idx INTEGER UNIQUE, created_at TEXT)');
        DB::statement('CREATE TABLE quiz_questions (idx INTEGER PRIMARY KEY AUTOINCREMENT, quiz_idx INTEGER, quiz_version INTEGER DEFAULT 1, question_order INTEGER, question_type TEXT, prompt TEXT, image_path TEXT, correct_choice_order INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(quiz_idx, quiz_version, question_order))');
        DB::statement('CREATE TABLE quiz_question_choices (question_idx INTEGER, choice_order INTEGER, choice_text TEXT, PRIMARY KEY(question_idx, choice_order))');
        DB::statement('CREATE TABLE quiz_attempts (idx INTEGER PRIMARY KEY AUTOINCREMENT, quiz_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, attempt_date TEXT, status TEXT, question_count INTEGER, quiz_version INTEGER DEFAULT 1, is_test_only INTEGER NOT NULL DEFAULT 0, score INTEGER, is_perfect INTEGER, awarded_hearts INTEGER DEFAULT 0, started_at TEXT, completed_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE quiz_attempt_answers (attempt_idx INTEGER, quiz_idx INTEGER, question_idx INTEGER, question_order INTEGER, selected_choice_order INTEGER, is_correct INTEGER, submitted_at TEXT, PRIMARY KEY(attempt_idx, question_order), UNIQUE(attempt_idx, question_idx))');
        DB::statement('CREATE TABLE quiz_likes (quiz_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, created_at TEXT, PRIMARY KEY(quiz_idx, member_idx))');
        DB::statement('CREATE TABLE quiz_weekly_settlements (idx INTEGER PRIMARY KEY AUTOINCREMENT, week_start_date TEXT UNIQUE, week_end_date TEXT, settled_at TEXT, created_at TEXT)');
        DB::statement('CREATE TABLE quiz_weekly_settlement_rewards (idx INTEGER PRIMARY KEY AUTOINCREMENT, settlement_idx INTEGER, rank_position INTEGER, member_idx INTEGER, quiz_idx INTEGER NULL, participation_count INTEGER, reached_at TEXT, reward_amount INTEGER, operation_key TEXT UNIQUE, created_at TEXT, UNIQUE(settlement_idx, rank_position))');

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'CreatorMember1', 'email' => 'creator@example.test', 'nickname' => 'Creator', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null, 'heart_balance' => 0],
            ['idx' => 2, 'member_code' => 'PlayerMember2', 'email' => 'player@example.test', 'nickname' => 'Player', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null, 'heart_balance' => 0],
            ['idx' => 3, 'member_code' => 'AdminMember003', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null, 'heart_balance' => 0],
            ['idx' => 4, 'member_code' => 'DummyMember004', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null, 'heart_balance' => 0],
            ['idx' => 5, 'member_code' => 'SuspendedMember5', 'email' => 'suspended@example.test', 'nickname' => 'Suspended', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'suspended', 'deleted_at' => null, 'heart_balance' => 0],
            ['idx' => 6, 'member_code' => 'DeletedMember6', 'email' => 'deleted@example.test', 'nickname' => 'Deleted', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => '2026-01-01 00:00:00', 'heart_balance' => 0],
        ]);
        DB::table('idols')->insert([
            ['idx' => 101, 'type' => 'individual', 'gender' => 'male', 'name' => 'Boy Idol', 'english_name' => 'Official Boy Idol', 'status' => 'active'],
            ['idx' => 102, 'type' => 'group', 'gender' => 'male', 'name' => 'Boy Group', 'english_name' => null, 'status' => 'active'],
            ['idx' => 103, 'type' => 'individual', 'gender' => 'female', 'name' => 'Girl Idol', 'english_name' => null, 'status' => 'active'],
            ['idx' => 104, 'type' => 'group', 'gender' => 'female', 'name' => 'Girl Group', 'english_name' => null, 'status' => 'active'],
            ['idx' => 105, 'type' => 'individual', 'gender' => 'female', 'name' => 'Inactive Idol', 'english_name' => null, 'status' => 'inactive'],
        ]);
        DB::table('heart_reward_settings')->insert([
            'action_code' => 'quiz_perfect', 'heart_amount' => 20, 'min_heart_amount' => 10,
            'max_heart_amount' => 30, 'daily_limit' => 0, 'is_enabled' => 1,
        ]);
        foreach ([1 => 3, 2 => 5, 3 => 10] as $count => $amount) {
            DB::table('heart_reward_settings')->insert([
                'action_code' => 'quiz_creator_reward_'.$count, 'heart_amount' => $amount,
                'min_heart_amount' => null, 'max_heart_amount' => null, 'daily_limit' => 0, 'is_enabled' => 1,
            ]);
        }
        foreach ([1 => 1000, 2 => 500, 3 => 100] as $rank => $amount) {
            DB::table('heart_reward_settings')->insert([
                'action_code' => 'quiz_weekly_rank_'.$rank, 'heart_amount' => $amount,
                'min_heart_amount' => null, 'max_heart_amount' => null, 'daily_limit' => 0, 'is_enabled' => 1,
            ]);
        }
        $this->quizIdx = $this->createQuiz([
            $this->question('choice', 'Choose blue', ['Red', 'Blue', 'Green', 'White', 'Black'], 2),
        ]);
        DB::table('heart_reward_settings')->insert([
            'action_code' => 'quiz_create', 'heart_amount' => 100, 'min_heart_amount' => null,
            'max_heart_amount' => null, 'daily_limit' => 20, 'is_enabled' => 1,
        ]);
    }

    private function question(string $type, string $prompt, array $choices, int $correct, ?UploadedFile $image = null): array
    {
        $question = ['type' => $type, 'prompt' => $prompt, 'choices' => $choices, 'correct_choice_order' => $correct];
        if ($type === 'ox') {
            unset($question['choices'], $question['correct_choice_order']);
            $question['correct_answer'] = $correct === 1 ? 'O' : 'X';
        }
        if ($image !== null) $question['image'] = $image;
        return $question;
    }

    private function quizImageUpload(string $filename): UploadedFile
    {
        $imageBytes = file_get_contents(base_path('tests/Fixtures/quiz-image.png'));
        $this->assertIsString($imageBytes);

        return UploadedFile::fake()->createWithContent($filename, $imageBytes)->mimeType('image/png');
    }

    private function createQuiz(array $questions, string $title = 'Test Quiz'): int
    {
        $response = $this->withSession(['member_idx' => 1])->post(route('quizzes.store'), [
            'title' => $title, 'idol_category' => 'boy', 'idol_idx' => 101, 'questions' => $questions,
        ]);
        $response->assertRedirect();
        return (int) DB::table('quizzes')->orderByDesc('idx')->value('idx');
    }

    private function recordCompletedAttempt(int $quizIdx, int $memberIdx, Carbon $completedAt, bool $isTestOnly = false, bool $isPerfect = false): void
    {
        $completedUtc = $completedAt->copy()->setTimezone('UTC');
        $now = $completedUtc->format('Y-m-d H:i:s.u');
        DB::table('quiz_attempts')->insert([
            'quiz_idx' => $quizIdx, 'member_idx' => $memberIdx, 'actor_member_idx' => $memberIdx,
            'attempt_date' => $completedAt->copy()->setTimezone('Asia/Seoul')->toDateString(),
            'status' => 'completed', 'question_count' => 1, 'quiz_version' => 1,
            'is_test_only' => $isTestOnly ? 1 : 0, 'score' => $isPerfect ? 1 : 0,
            'is_perfect' => $isPerfect ? 1 : 0, 'awarded_hearts' => 0,
            'started_at' => $now, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function test_creation_screen_renders_localized_staged_question_controls(): void
    {
        DB::table('heart_reward_settings')->insert([
            'action_code' => 'idol_registration_approved', 'heart_amount' => 4321,
            'min_heart_amount' => null, 'max_heart_amount' => null, 'daily_limit' => 0, 'is_enabled' => 1,
        ]);

        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.basic_info_heading'))->assertSee('name="idol_category"', false)
            ->assertSee(__('quiz.title_label'))->assertSee('name="title"', false)->assertSee('required', false)
            ->assertSee(__('ui.request_type'))->assertSee(__('ui.idol'))
            ->assertSee(__('quiz.idol_selection_help'))
            ->assertSee(__('ui.fan_post_registration_prompt'))
            ->assertSee('href="'.route('idol-registration.create').'"', false)
            ->assertSee('data-idol-registration-help', false)
            ->assertSee('data-registration-panel hidden', false)
            ->assertSee('+4,321')
            ->assertSee(__('ui.fan_post_registration_title'))
            ->assertSee(__('ui.reward_after_approval'))
            ->assertSee(__('ui.fan_post_registration_button'))
            ->assertSee('name="idol_idx"', false)->assertSee('name="cover_image"', false)
            ->assertSee('class="fan-post-create-field quiz-create-cover-field"', false)
            ->assertSee('class="quiz-cover-file-control"', false)
            ->assertSee('data-cover-filename', false)
            ->assertSee(__('quiz.choose_file'))->assertSee(__('quiz.no_file_selected'))
            ->assertSee('class="quiz-create-question-section"', false)
            ->assertSee('class="quiz-create-guide-reward"', false)
            ->assertSee(__('quiz.guide_one'))->assertSee(__('quiz.guide_two'))
            ->assertSee('id="quiz-cover-crop-dialog"', false)->assertSee('id="quiz-question-crop-dialog-2"', false)
            ->assertSee(__('quiz.type_image'))->assertSee(__('quiz.type_choice'))
            ->assertSee(__('quiz.type_ox'))->assertSee(__('quiz.add_question'))
            ->assertSee('data-question-panel="0"', false)->assertSee('data-question-panel="1"', false)
            ->assertDontSee('data-preview-quiz', false)->assertDontSee('data-quiz-preview', false)
            ->assertSee(__('quiz.creator_reward_guide_intro'))->assertSee('+3')->assertSee('+5')->assertSee('+10');
    }

    public function test_quiz_creation_quota_counts_creations_not_edits_and_survives_quiz_deletion(): void
    {
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_remaining', ['remaining' => 4, 'limit' => 5]));

        $this->withSession(['member_idx' => 1])->put(route('quizzes.update', $this->quizIdx), [
            'title' => 'Edited without using quota', 'quiz_version' => 1,
            'idol_category' => 'boy', 'idol_idx' => 101,
            'questions' => [$this->question('choice', 'Edited prompt', ['A', 'B', 'C', 'D', 'E'], 1)],
        ])->assertRedirect(route('quizzes.show', $this->quizIdx));
        $this->assertSame(1, DB::table('quiz_creation_histories')->where('member_idx', 1)->count());

        for ($index = 2; $index <= 5; $index++) {
            $this->createQuiz([$this->question('choice', 'Quiz '.$index, ['A', 'B', 'C', 'D', 'E'], 1)]);
        }

        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_limit_reached', ['limit' => 5, 'remaining' => 0]))
            ->assertSee('class="quiz-primary-button" type="submit" disabled', false);
        $this->withSession(['member_idx' => 1])->post(route('quizzes.store'), [
            'title' => 'Sixth quiz', 'idol_category' => 'boy', 'idol_idx' => 101,
            'questions' => [$this->question('choice', 'Blocked prompt', ['A', 'B', 'C', 'D', 'E'], 1)],
        ])->assertRedirect()->assertSessionHas('quiz_alert', __('quiz.daily_creation_limit_reached', ['limit' => 5, 'remaining' => 0]));
        $this->assertSame(5, DB::table('quiz_creation_histories')->where('member_idx', 1)->count());
        $this->assertSame(5, DB::table('quizzes')->where('member_idx', 1)->count());

        DB::table('quizzes')->where('idx', $this->quizIdx)->delete();
        $this->assertSame(5, DB::table('quiz_creation_histories')->where('member_idx', 1)->count());
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_limit_reached', ['limit' => 5, 'remaining' => 0]));
    }

    public function test_quiz_creation_quota_uses_asia_seoul_day_boundaries(): void
    {
        DB::table('quiz_creation_histories')->delete();
        DB::table('quiz_creation_histories')->insert([
            'member_idx' => 1, 'quiz_idx' => 9001, 'created_at' => '2026-10-24 14:59:59',
        ]);

        $this->travelTo(\Carbon\Carbon::parse('2026-10-24 23:59:59', 'Asia/Seoul'));
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_remaining', ['remaining' => 4, 'limit' => 5]));

        $this->travelTo(\Carbon\Carbon::parse('2026-10-25 00:00:00', 'Asia/Seoul'));
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_remaining', ['remaining' => 5, 'limit' => 5]));
    }

    public function test_quiz_creation_quota_messages_are_localized(): void
    {
        app()->setLocale('ko');
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_remaining', ['remaining' => 4, 'limit' => 5]));

        app()->setLocale('en');
        $this->withSession(['member_idx' => 1])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.daily_creation_remaining', ['remaining' => 4, 'limit' => 5]));
    }

    public function test_random_dummy_quiz_creation_uses_selected_dummy_quota_and_replays_idempotently(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'Asia/Seoul'));
        DB::table('members')->where('idx', 5)->update(['member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null]);
        $usedAt = now('UTC')->format('Y-m-d H:i:s');
        for ($index = 0; $index < 5; $index++) {
            DB::table('quiz_creation_histories')->insert([
                'member_idx' => 4, 'quiz_idx' => 8000 + $index, 'created_at' => $usedAt,
            ]);
        }

        $payload = [
            'title' => 'Random dummy quiz', 'idol_category' => 'boy', 'idol_idx' => 101,
            'request_key' => str_repeat('a', 64),
            'questions' => [$this->question('choice', 'Question for random dummy', ['A', 'B', 'C', 'D', 'E'], 1)],
        ];
        $session = ['member_idx' => 3];
        $this->withSession($session)->post(route('quizzes.store'), $payload)->assertRedirect();
        $quiz = DB::table('quizzes')->where('title', 'Random dummy quiz')->first();
        $this->assertNotNull($quiz);
        $this->assertSame(5, (int) $quiz->member_idx);
        $this->assertSame(3, (int) $quiz->actor_member_idx);
        $this->assertSame(1, DB::table('quiz_creation_histories')->where('member_idx', 5)->count());
        $reservation = DB::table('admin_dummy_activity_requests')->where('actor_member_idx', 3)
            ->where('action_code', 'quiz_create')->where('request_key', $payload['request_key'])->first();
        $this->assertSame(5, (int) $reservation->member_idx);
        $this->assertSame((int) $quiz->idx, (int) $reservation->result_idx);
        $this->assertNotNull($reservation->completed_at);

        $this->withSession($session)->post(route('quizzes.store'), $payload)->assertRedirect(route('quizzes.show', $quiz->idx));
        $this->assertSame(1, DB::table('quizzes')->where('title', 'Random dummy quiz')->count());
        $this->assertSame(1, DB::table('quiz_creation_histories')->where('member_idx', 5)->count());
    }

    public function test_random_dummy_quiz_creation_rejects_when_all_dummies_reached_daily_limit(): void
    {
        $usedAt = now('UTC')->format('Y-m-d H:i:s');
        foreach ([4, 5] as $memberIdx) {
            DB::table('members')->where('idx', $memberIdx)->update(['member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null]);
            for ($index = 0; $index < 5; $index++) {
                DB::table('quiz_creation_histories')->insert([
                    'member_idx' => $memberIdx, 'quiz_idx' => ($memberIdx * 1000) + $index, 'created_at' => $usedAt,
                ]);
            }
        }

        $response = $this->withSession(['member_idx' => 3])
            ->post(route('quizzes.store'), [
                'title' => 'No creator dummy available', 'idol_category' => 'boy', 'idol_idx' => 101,
                'request_key' => str_repeat('b', 64),
                'questions' => [$this->question('choice', 'No dummy question', ['A', 'B', 'C', 'D', 'E'], 1)],
            ]);

        $response->assertRedirect()->assertSessionHas('quiz_alert', __('quiz.random_dummy_no_eligible'));
        $this->assertSame(1, DB::table('quizzes')->count());
        $this->assertSame(0, DB::table('admin_dummy_activity_requests')->count());
    }

    public function test_quiz_creator_reward_amounts_are_configurable_and_rendered(): void
    {
        DB::table('heart_reward_settings')->where('action_code', 'quiz_creator_reward_2')->update([
            'heart_amount' => 1234,
        ]);

        $response = $this->withSession(['member_idx' => 1])->get(route('quizzes.create'));
        $response->assertOk()->assertSee('+1,234')->assertSee('+3')->assertSee('+10');
    }

    public function test_quiz_creation_rejects_inactive_or_mismatched_idol_categories(): void
    {
        $validQuestion = [$this->question('choice', 'Validated relationship', ['A', 'B', 'C', 'D', 'E'], 1)];
        foreach ([['girl', 101], ['girl', 105]] as [$category, $idolIdx]) {
            $this->withSession(['member_idx' => 1])->from(route('quizzes.create'))
                ->post(route('quizzes.store'), [
                    'idol_category' => $category, 'idol_idx' => $idolIdx, 'questions' => $validQuestion,
                ])->assertRedirect(route('quizzes.create'))->assertSessionHas('quiz_alert');
        }
        $this->assertSame(1, DB::table('quizzes')->count());
    }

    public function test_category_selector_uses_existing_active_idol_endpoint(): void
    {
        $response = $this->withSession(['member_idx' => 1])->get(route('fan-post.create.idols', ['category' => 'girl']));
        $response->assertOk()->assertJsonCount(1, 'idols')->assertJsonPath('idols.0.idx', 103);
    }

    public function test_quiz_creation_guidelines_are_localized_in_korean_and_english(): void
    {
        $this->withSession(['member_idx' => 1, 'locale' => 'ko'])->get(route('quizzes.create'))
            ->assertOk()->assertSee(__('quiz.creator_reward_guide_intro'))
            ->assertSee(__('quiz.creator_reward_for_count', ['count' => 1]))
            ->assertSee('+3')->assertSee('+5')->assertSee('+10')
            ->assertSee('한 계정당 하루 최대 5개의 퀴즈를 등록할 수 있습니다.')
            ->assertSee('다른 회원에게 불쾌감을 주는 퀴즈는 등록이 제한됩니다.')
            ->assertSee('불확실하거나 검증되지 않은 정보를 바탕으로 한 퀴즈는 등록이 제한됩니다.')
            ->assertSee('이미 등록된 퀴즈와 내용이 동일하거나 거의 유사한 퀴즈는 등록이 제한됩니다.')
            ->assertSee('운영 정책을 위반한 퀴즈는 등록이 제한되거나 삭제될 수 있습니다.')
            ->assertDontSee('quiz_create');

        $this->withSession(['member_idx' => 1, 'locale' => 'en'])->get(route('quizzes.create'))
            ->assertOk()
            ->assertSee('Each account may create up to 5 quizzes per day.')
            ->assertSee('Quizzes that may offend or upset other members may be restricted.')
            ->assertSee('Quizzes based on uncertain or unverified information may be restricted.')
            ->assertSee('Quizzes identical or substantially similar to existing quizzes may be restricted.')
            ->assertSee('Quizzes that violate operating policies may be restricted or removed.');
    }

    public function test_admin_can_configure_question_count_creator_rewards_without_changing_perfect_rewards(): void
    {
        $this->withSession(['member_idx' => 3])->get(route('admin.hearts.settings'))
            ->assertOk()->assertSee('quiz_creator_reward_1')->assertSee('quiz_creator_reward_2')->assertSee('quiz_creator_reward_3');

        $this->withSession(['member_idx' => 3])->put(route('admin.hearts.settings.update', 'quiz_creator_reward_1'), [
            'heart_amount' => 8, 'is_enabled' => 1,
        ])->assertRedirect();

        $setting = DB::table('heart_reward_settings')->where('action_code', 'quiz_creator_reward_1')->first();
        $this->assertSame(8, (int) $setting->heart_amount);
        $this->assertSame(5, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_creator_reward_2')->value('heart_amount'));
        $perfect = DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->first();
        $this->assertSame(10, (int) $perfect->min_heart_amount);
        $this->assertSame(30, (int) $perfect->max_heart_amount);
    }

    public function test_admin_can_configure_quiz_ranges_and_weekly_rewards_and_reject_invalid_ranges(): void
    {
        $admin = $this->withSession(['member_idx' => 3]);
        $admin->get(route('admin.hearts.settings'))
            ->assertOk()->assertSee('quiz_perfect')->assertSee('quiz_weekly_rank_1')
            ->assertSee('quiz_weekly_rank_2')->assertSee('quiz_weekly_rank_3')
            ->assertDontSee('quiz_create');

        $admin->put(route('admin.hearts.settings.update', 'quiz_perfect'), [
            'heart_amount' => 20, 'min_heart_amount' => 7, 'max_heart_amount' => 7, 'is_enabled' => 1,
        ])->assertRedirect();
        $this->assertSame(7, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->value('min_heart_amount'));
        $this->assertSame(7, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->value('max_heart_amount'));

        $admin->put(route('admin.hearts.settings.update', 'quiz_perfect'), [
            'heart_amount' => 20, 'min_heart_amount' => 9, 'max_heart_amount' => 8, 'is_enabled' => 1,
        ])->assertSessionHasErrors('max_heart_amount');
        $this->assertSame(7, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->value('min_heart_amount'));

        $admin->put(route('admin.hearts.settings.update', 'quiz_weekly_rank_1'), [
            'heart_amount' => 1350, 'is_enabled' => 1,
        ])->assertRedirect();
        $this->assertSame(1350, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_weekly_rank_1')->value('heart_amount'));
        $this->assertSame(0, (int) DB::table('heart_reward_settings')->where('action_code', 'quiz_weekly_rank_1')->value('daily_limit'));
    }

    public function test_quiz_creation_does_not_award_hearts(): void
    {
        $response = $this->withSession(['member_idx' => 1])->post(route('quizzes.store'), [
            'title' => 'Rewarded Quiz Title', 'idol_category' => 'boy', 'idol_idx' => 101, 'questions' => [
                $this->question('choice', 'Rewarded quiz', ['A', 'B', 'C', 'D', 'E'], 1),
            ],
        ])->assertRedirect();
        $quizIdx = (int) basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_create:'.$quizIdx)->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame('Rewarded Quiz Title', DB::table('quizzes')->where('idx', $quizIdx)->value('title'));
    }

    public function test_legacy_creation_reward_setting_does_not_affect_quiz_creation(): void
    {
        DB::table('heart_histories')->where('member_idx', 1)->delete();
        DB::table('members')->where('idx', 1)->update(['heart_balance' => 0]);
        DB::table('heart_reward_settings')->where('action_code', 'quiz_create')->update(['daily_limit' => 1]);

        $firstQuizIdx = $this->createQuiz([$this->question('choice', 'First rewarded quiz', ['A', 'B', 'C', 'D', 'E'], 1)]);
        $secondQuizIdx = $this->createQuiz([$this->question('choice', 'Second quiz', ['A', 'B', 'C', 'D', 'E'], 1)]);

        $this->assertNotSame($firstQuizIdx, $secondQuizIdx);
        $this->assertSame(3, DB::table('quizzes')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)
            ->where('reason', 'quiz_create_reward')->count());
        $this->assertSame(0, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
    }

    public function test_quiz_list_searches_and_filters_using_saved_question_count(): void
    {
        DB::table('idol_localizations')->insert([
            'idol_idx' => 101, 'language_code' => 'ko', 'localized_name' => '현지 아이돌명',
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 101, 'language_code' => 'en', 'alias_name' => 'Quiz Idol Alias'],
            ['idol_idx' => 101, 'language_code' => 'ko', 'alias_name' => 'Quiz Idol Alias'],
        ]);

        $listResponse = $this->withSession(['member_idx' => 2])->get(route('quizzes.index', [
            'q' => 'Test Quiz', 'questions' => 1, 'sort' => 'latest',
        ]));
        $listResponse->assertOk()
            ->assertSee('Test Quiz')
            ->assertSee(__('quiz.question_badge', ['count' => 1]))
            ->assertSee('data-result-count="1"', false)
            ->assertDontSee('quiz-list-completed-badge', false)
            ->assertDontSee('quiz-list-search-input', false)
            ->assertDontSee('quiz-list-filters', false)
            ->assertDontSee(__('quiz.type_choice'));
        $listMarkup = $listResponse->getContent();
        $heroEnd = strpos($listMarkup, '</header>', strpos($listMarkup, '<header class="quiz-list-hero"'));
        $sortControl = strpos($listMarkup, 'id="quiz-list-sort"');
        $createAction = strpos($listMarkup, 'class="idol-detail-create-post quiz-list-create-action"');
        $this->assertNotFalse($heroEnd);
        $this->assertNotFalse($sortControl);
        $this->assertNotFalse($createAction);
        $this->assertGreaterThan($heroEnd, $createAction);
        $this->assertGreaterThan($sortControl, $createAction);
        $this->assertStringContainsString('href="'.route('quizzes.create').'"', $listMarkup);
        $this->assertStringNotContainsString('quiz-list-create-button', $listMarkup);
        foreach (['Official Boy Idol', '현지 아이돌명', 'Quiz Idol Alias', 'Creator'] as $term) {
            $this->withSession(['member_idx' => 2])->get(route('quizzes.index', [
                'q' => $term, 'questions' => 1, 'sort' => 'oldest', 'idol_category' => 'boy',
            ]))->assertOk()
                ->assertSee('Test Quiz')
                ->assertSee('data-result-count="1"', false)
                ->assertSee(__('quiz.category_list_results', [
                    'category' => __('quiz.idol_category_boy'), 'count' => '1',
                ]));
        }

        $this->withSession(['member_idx' => 2])->get(route('quizzes.index', ['idol_category' => 'boy']))
            ->assertOk()
            ->assertSee('data-category-template', false)
            ->assertSee(__('quiz.category_list_results', [
                'category' => __('quiz.idol_category_boy'), 'count' => '1',
            ]));

        $this->withSession(['member_idx' => 2])->get(route('quizzes.index', [
            'q' => 'missing quiz title', 'questions' => 1,
        ]))->assertOk()->assertSee(__('quiz.empty_list'));

        DB::table('idols')->where('idx', 102)->update(['english_name' => 'Official Boy Group']);
        DB::table('quizzes')->where('idx', $this->quizIdx)->update(['idol_idx' => 102]);
        $this->withSession(['member_idx' => 2])->get(route('quizzes.index', [
            'q' => 'Official Boy Group', 'questions' => 1, 'sort' => 'most_questions', 'idol_category' => 'boy_group',
        ]))->assertOk()->assertSee('Test Quiz')->assertSee('data-result-count="1"', false);
    }

    public function test_guest_quiz_list_hides_filters_and_search_without_an_idol_selector(): void
    {
        $guestResponse = $this->flushSession()->get(route('quizzes.index'))->assertOk()
            ->assertSee(__('quiz.weekly_quiz_ranking_title'))
            ->assertSee(__('quiz.monthly_quiz_ranking_title'))
            ->assertSee(__('quiz.list_results', ['count' => '1']))
            ->assertSee('class="quiz-list-idol-badge"', false)->assertSee('Boy Idol')
            ->assertSee('class="quiz-list-question-badge"', false)
            ->assertSee('href="'.route('member.fan-posts', ['member_code' => 'CreatorMember1']).'"', false)
            ->assertSeeInOrder(['class="quiz-list-idol-badge"', 'class="quiz-list-question-badge"'])
            ->assertDontSee('data-idol-selector', false)
            ->assertDontSee('quiz-list-count-filters', false)
            ->assertDontSee('data-quiz-list-filters', false)
            ->assertDontSee('data-idol-category=', false)
            ->assertDontSee('quiz-list-search-input', false)
            ->assertDontSee('class="quiz-list-search"', false);
        $guestMarkup = $guestResponse->getContent();
        $this->assertStringContainsString(
            'data-sign-in-open data-sign-in-return-url="'.route('quizzes.create').'"',
            $guestMarkup
        );
        $this->assertStringNotContainsString('quiz-list-create-button', $guestMarkup);
    }

    public function test_quiz_list_shows_completion_badge_only_for_member_completed_today_in_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->travelTo(Carbon::parse('2026-10-25 23:59:50', 'UTC'));

        $this->flushSession()->get(route('quizzes.index'))->assertOk()
            ->assertDontSee('quiz-list-completed-badge', false);

        DB::table('quiz_attempts')->insert([
            'quiz_idx' => $this->quizIdx,
            'member_idx' => 2,
            'actor_member_idx' => 2,
            'attempt_date' => '2026-10-25',
            'status' => 'completed',
            'question_count' => 1,
            'quiz_version' => 1,
            'is_test_only' => 0,
            'score' => 0,
            'is_perfect' => 0,
            'awarded_hearts' => 0,
        ]);

        $this->flushSession()->get(route('quizzes.index'))->assertOk()
            ->assertDontSee('quiz-list-completed-badge', false);
        $this->withSession(['member_idx' => 2])->get(route('quizzes.index'))->assertOk()
            ->assertSee('class="quiz-list-completed-badge"', false)
            ->assertSee(__('quiz.today_completed_badge'));

        $this->travelTo(Carbon::parse('2026-10-26 00:00:10', 'UTC'));
        $this->withSession(['member_idx' => 2])->get(route('quizzes.index'))->assertOk()
            ->assertDontSee('quiz-list-completed-badge', false);
    }

    public function test_guest_is_sent_through_the_existing_sign_in_flow_for_quiz_creation(): void
    {
        $this->flushSession()->get(route('quizzes.create'))
            ->assertRedirect(route('home'))
            ->assertSessionHas('open_sign_in', true);
    }

    public function test_inactive_members_are_sent_through_the_existing_sign_in_flow_for_quiz_creation(): void
    {
        foreach ([5, 6] as $memberIdx) {
            $this->withSession(['member_idx' => $memberIdx])->get(route('quizzes.create'))
                ->assertRedirect(route('home'))->assertSessionHas('open_sign_in', true);
            $this->withSession(['member_idx' => $memberIdx])->post(route('quizzes.store'), ['questions' => []])
                ->assertRedirect(route('home'))->assertSessionHas('open_sign_in', true);
        }

        $this->assertSame(1, DB::table('quizzes')->count());
    }

    public function test_administrator_dummy_mode_creates_and_plays_as_dummy_creator_without_rewards(): void
    {
        $createScreen = $this->withSession(['member_idx' => 3])->get(route('quizzes.create'))->assertOk();
        preg_match('/name="request_key" value="([a-f0-9]{64})"/', $createScreen->getContent(), $createKeyMatch);
        $this->assertCount(2, $createKeyMatch);
        $response = $this->withSession(['member_idx' => 3])->post(route('quizzes.store'), [
            'title' => 'Admin Quiz Title', 'idol_category' => 'boy_group', 'idol_idx' => 102, 'questions' => [
                $this->question('choice', 'Admin-created quiz', ['A', 'B', 'C', 'D', 'E'], 1),
            ], 'request_key' => $createKeyMatch[1],
        ])->assertRedirect();

        $quizIdx = (int) basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $quiz = DB::table('quizzes')->where('idx', $quizIdx)->first();
        $this->assertSame(4, (int) $quiz->member_idx);
        $this->assertSame(3, (int) $quiz->actor_member_idx);
        $this->assertSame(102, (int) $quiz->idol_idx);
        $playScreen = $this->withSession(['member_idx' => 3])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee('name="selected_choice_order"', false)->assertDontSee(__('quiz.owner_preview'));
        preg_match('/name="request_key" value="([a-f0-9]{64})"/', $playScreen->getContent(), $playKeyMatch);
        $this->assertCount(2, $playKeyMatch);
        $this->withSession(['member_idx' => 3])->post(route('quizzes.answer', $quizIdx), [
            'request_key' => $playKeyMatch[1], 'quiz_version' => 1,
            'question_order' => 1, 'selected_choice_order' => 1,
        ])->assertRedirect();
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->count());
        $this->assertDatabaseHas('quiz_attempts', ['quiz_idx' => $quizIdx, 'member_idx' => 4, 'actor_member_idx' => 3, 'is_test_only' => 1]);
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_only_creator_or_administrator_can_open_quiz_editor(): void
    {
        $this->withSession(['member_idx' => 1])->get(route('quizzes.edit', $this->quizIdx))
            ->assertOk()->assertSee(__('quiz.edit_title'))->assertSee('value="Test Quiz"', false)->assertSee('value="Choose blue"', false)
            ->assertDontSee('data-preview-quiz', false)->assertDontSee('data-quiz-preview', false)
            ->assertSee('name="quiz_version" value="1"', false);
        $ownerDetail = $this->withSession(['member_idx' => 1])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertSee('href="'.route('quizzes.edit', $this->quizIdx).'"', false)
            ->assertSee('href="'.route('member.fan-posts', ['member_code' => 'CreatorMember1']).'"', false)
            ->assertSee('href="'.route('quizzes.index').'">'.__('quiz.back_to_quiz_list'), false)
            ->assertSee('class="quiz-card-eyebrow">Test Quiz</p>', false)
            ->assertDontSee('id="quiz-detail-title"', false)
            ->assertDontSee('class="quiz-question-type"', false);
        $ownerMarkup = $ownerDetail->getContent();
        $heroEnd = strpos($ownerMarkup, '</header>');
        $actionRow = strpos($ownerMarkup, 'class="quiz-detail-actions quiz-detail-content-actions"');
        $questionCard = strpos($ownerMarkup, 'class="quiz-question-card"');
        $editButton = strpos($ownerMarkup, 'href="'.route('quizzes.edit', $this->quizIdx).'"');
        $listButton = strpos($ownerMarkup, 'href="'.route('quizzes.index').'">'.__('quiz.back_to_quiz_list'));
        $this->assertNotFalse($heroEnd);
        $this->assertNotFalse($actionRow);
        $this->assertNotFalse($questionCard);
        $this->assertGreaterThan($heroEnd, $actionRow);
        $this->assertLessThan($questionCard, $actionRow);
        $this->assertLessThan($listButton, $editButton);
        $this->withSession(['member_idx' => 2])->get(route('quizzes.edit', $this->quizIdx))->assertForbidden();
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertDontSee('href="'.route('quizzes.edit', $this->quizIdx).'"', false)
            ->assertDontSee(__('quiz.delete_quiz'));
        $this->withSession(['member_idx' => 3])->get(route('quizzes.edit', $this->quizIdx))->assertOk();
        $this->withSession(['member_idx' => 5])->get(route('quizzes.edit', $this->quizIdx))
            ->assertRedirect(route('home'))->assertSessionHas('open_sign_in', true);
        $this->flushSession()->get(route('quizzes.edit', $this->quizIdx))
            ->assertRedirect(route('home'))->assertSessionHas('open_sign_in', true);
    }

    public function test_quiz_creator_can_soft_delete_quiz_without_removing_history(): void
    {
        $this->recordCompletedAttempt($this->quizIdx, 2, Carbon::parse('2026-10-20 12:00:00', 'Asia/Seoul'));
        DB::table('members')->where('idx', 1)->update(['heart_balance' => 13]);
        DB::table('heart_histories')->insert([
            'member_idx' => 1, 'amount' => 3, 'balance_after' => 13, 'reason' => 'quiz_creator_reward_1',
            'operation_key' => 'quiz_creator:'.$this->quizIdx.':2', 'actor_member_idx' => 2,
        ]);
        $settlementIdx = (int) DB::table('quiz_weekly_settlements')->insertGetId([
            'week_start_date' => '2026-10-19', 'week_end_date' => '2026-10-26', 'created_at' => now(),
        ]);
        DB::table('quiz_weekly_settlement_rewards')->insert([
            'settlement_idx' => $settlementIdx, 'rank_position' => 1, 'member_idx' => 1, 'quiz_idx' => $this->quizIdx,
            'participation_count' => 1, 'reached_at' => now(), 'reward_amount' => 1000,
            'operation_key' => 'quiz_weekly:2026-10-19:1:quiz:'.$this->quizIdx.':rank:1', 'created_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->delete(route('quizzes.destroy', $this->quizIdx))
            ->assertRedirect(route('quizzes.index'))
            ->assertSessionHas('global_notification.message', __('quiz.deleted_message'));

        $this->assertNotNull(DB::table('quizzes')->where('idx', $this->quizIdx)->value('deleted_at'));
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->count());
        $this->assertSame(1, DB::table('quiz_questions')->where('quiz_idx', $this->quizIdx)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'quiz_creator:'.$this->quizIdx.':2')->count());
        $this->assertSame(1, DB::table('quiz_weekly_settlement_rewards')->where('quiz_idx', $this->quizIdx)->count());
        $this->assertSame(13, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))->assertNotFound();
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $this->quizIdx), [
            'question_order' => 1, 'selected_choice_order' => 2,
        ])->assertNotFound();
        $this->assertEmpty(app(QuizWeeklyParticipationRanking::class)->rankQuizzes(
            Carbon::parse('2026-10-19', 'Asia/Seoul'), Carbon::parse('2026-10-26', 'Asia/Seoul')
        ));
    }

    public function test_only_quiz_creator_or_administrator_can_delete_quiz(): void
    {
        $this->withSession(['member_idx' => 2])->delete(route('quizzes.destroy', $this->quizIdx))->assertForbidden();
        $this->assertNull(DB::table('quizzes')->where('idx', $this->quizIdx)->value('deleted_at'));
        $this->withSession(['member_idx' => 3])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertSee(__('quiz.delete_quiz'));
        $this->withSession(['member_idx' => 3])->delete(route('quizzes.destroy', $this->quizIdx))
            ->assertRedirect(route('quizzes.index'));
    }

    public function test_edit_creates_a_new_question_version_and_preserves_in_progress_and_reward_records(): void
    {
        $quizIdx = $this->createQuiz([
            $this->question('choice', 'Old first question', ['A', 'B', 'C', 'D', 'E'], 1),
            $this->question('ox', 'Old second question', [], 2),
        ]);
        DB::table('members')->where('idx', 1)->update(['heart_balance' => 100]);
        DB::table('heart_histories')->insert([
            'member_idx' => 1, 'amount' => 100, 'balance_after' => 100, 'reason' => 'quiz_create_reward',
            'operation_key' => 'quiz_create:'.$quizIdx, 'actor_member_idx' => 1,
            'memo' => 'Legacy quiz creation reward', 'created_at' => now(),
        ]);
        $creationHistory = DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_create:'.$quizIdx)->first();
        $this->assertNotNull($creationHistory);

        $firstQuestion = DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 1)->where('question_order', 1)->first();
        $startedAt = now();
        $attemptIdx = (int) DB::table('quiz_attempts')->insertGetId([
            'quiz_idx' => $quizIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'attempt_date' => now('Asia/Seoul')->toDateString(), 'status' => 'in_progress',
            'question_count' => 2, 'quiz_version' => 1, 'is_test_only' => 0,
            'started_at' => $startedAt, 'created_at' => $startedAt, 'updated_at' => $startedAt,
        ]);
        DB::table('quiz_attempt_answers')->insert([
            'attempt_idx' => $attemptIdx, 'quiz_idx' => $quizIdx, 'question_idx' => $firstQuestion->idx,
            'question_order' => 1, 'selected_choice_order' => 1, 'is_correct' => null, 'submitted_at' => $startedAt,
        ]);
        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', 2)->first();
        $this->assertSame(1, (int) $attempt->quiz_version);

        DB::table('members')->insert([
            'idx' => 8, 'member_code' => 'PlayerMember008', 'email' => 'player8@example.test',
            'nickname' => 'Player 8', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'heart_balance' => 0,
        ]);
        $this->withSession(['member_idx' => 8])->post(route('quizzes.answer', $quizIdx), [
            'answers' => [1 => 1, 2 => 2], 'question_order' => 2, 'selected_choice_order' => 2, 'quiz_version' => 1,
        ])->assertRedirect();
        $completedBeforeEdit = DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', 8)->first();
        $perfectRewardBeforeEdit = DB::table('heart_histories')->where('member_idx', 8)
            ->where('operation_key', 'quiz_perfect:'.$completedBeforeEdit->idx)->first();
        $creatorRewardBeforeEdit = DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$quizIdx.':8')->first();
        $this->assertSame('completed', $completedBeforeEdit->status);
        $this->assertNotNull($perfectRewardBeforeEdit);
        $this->assertSame(5, (int) $creatorRewardBeforeEdit->amount);

        $questionImage = $this->quizImageUpload('updated-question.png');
        $coverImage = $this->quizImageUpload('updated-cover.png');
        $this->withSession(['member_idx' => 1])->put(route('quizzes.update', $quizIdx), [
            'title' => 'Updated Quiz Title',
            'quiz_version' => 1,
            'idol_category' => 'girl_group',
            'idol_idx' => 104,
            'cover_image' => $coverImage,
            'questions' => [
                $this->question('image', 'Updated image question', ['New A', 'New B', 'New C', 'New D', 'New E'], 2, $questionImage),
                $this->question('choice', 'Updated choice question', ['One', 'Two', 'Three', 'Four', 'Five'], 3),
                $this->question('ox', 'Updated OX question', [], 1),
            ],
        ])->assertRedirect(route('quizzes.show', $quizIdx))->assertSessionHas('quiz_updated', true);

        $updatedQuiz = DB::table('quizzes')->where('idx', $quizIdx)->first();
        $this->assertSame('Updated Quiz Title', $updatedQuiz->title);
        $this->assertSame(2, (int) $updatedQuiz->quiz_version);
        $this->assertSame(3, (int) $updatedQuiz->question_count);
        $this->assertSame(104, (int) $updatedQuiz->idol_idx);
        $this->assertTrue(Storage::disk('public')->exists(substr($updatedQuiz->cover_image_path, strlen('storage/'))));
        $this->assertSame(2, DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 1)->count());
        $this->assertSame(3, DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 2)->count());
        $newImageQuestion = DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 2)->where('question_order', 1)->first();
        $this->assertSame('Updated image question', $newImageQuestion->prompt);
        $this->assertSame(5, DB::table('quiz_question_choices')->where('question_idx', $newImageQuestion->idx)->count());
        $this->assertTrue(Storage::disk('public')->exists(substr($newImageQuestion->image_path, strlen('storage/'))));
        $editResponse = $this->withSession(['member_idx' => 1])->get(route('quizzes.edit', $quizIdx))->assertOk();
        $editResponse->assertSee('data-existing-image="'.\App\Support\ManagedImageStorage::publicUrl($updatedQuiz->cover_image_path).'"', false)
            ->assertSee('data-existing-image="'.\App\Support\ManagedImageStorage::publicUrl($newImageQuestion->image_path).'"', false)
            ->assertSee('name="questions[0][existing_question_idx]" value="'.$newImageQuestion->idx.'"', false);
        $this->assertSame($creationHistory->operation_key, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_create:'.$quizIdx)->value('operation_key'));
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_create:'.$quizIdx)->count());
        $this->assertSame($completedBeforeEdit->score, DB::table('quiz_attempts')->where('idx', $completedBeforeEdit->idx)->value('score'));
        $this->assertSame($completedBeforeEdit->awarded_hearts, DB::table('quiz_attempts')->where('idx', $completedBeforeEdit->idx)->value('awarded_hearts'));
        $this->assertSame($perfectRewardBeforeEdit->amount, DB::table('heart_histories')->where('idx', $perfectRewardBeforeEdit->idx)->value('amount'));
        $this->withSession(['member_idx' => 8])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee('Old first question')->assertSee('Old second question')->assertDontSee('Updated image question');

        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee('Old second question')->assertDontSee('Updated image question');
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $quizIdx), [
            'answers' => [1 => 2, 2 => 2], 'question_order' => 2, 'selected_choice_order' => 2, 'quiz_version' => 1,
        ])->assertRedirect();
        $this->assertSame(5, (int) DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$quizIdx.':2')->value('amount'));
        $this->assertSame(2, DB::table('heart_histories')->where('member_idx', 1)
            ->where('reason', 'quiz_creator_reward_2')->count());
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee('Old second question')->assertSee('Old first question');

        $this->travel(1)->days();
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $quizIdx), [
            'answers' => [1 => 2, 2 => 3, 3 => 1], 'question_order' => 3, 'selected_choice_order' => 1, 'quiz_version' => 2,
        ])->assertRedirect();
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$quizIdx.':2')->count());
        $this->assertSame(5, (int) DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$quizIdx.':2')->value('amount'));

        DB::table('members')->insert([
            'idx' => 7, 'member_code' => 'PlayerMember007', 'email' => 'player7@example.test',
            'nickname' => 'Player 7', 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'heart_balance' => 0,
        ]);
        $this->withSession(['member_idx' => 7])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee('Updated image question')->assertDontSee('Old first question');
        $this->withSession(['member_idx' => 7])->post(route('quizzes.answer', $quizIdx), [
            'answers' => [1 => 2, 2 => 1, 3 => 1], 'question_order' => 3, 'selected_choice_order' => 1, 'quiz_version' => 2,
        ])->assertRedirect();
        $this->assertSame(2, (int) DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)
            ->where('member_idx', 7)->value('quiz_version'));
    }

    public function test_administrator_can_edit_another_members_quiz_and_non_owner_cannot_update_it(): void
    {
        $payload = [
            'title' => 'Admin Edited Title', 'quiz_version' => 1, 'idol_category' => 'boy', 'idol_idx' => 101,
            'questions' => [$this->question('choice', 'Changed prompt', ['A', 'B', 'C', 'D', 'E'], 1)],
        ];
        $this->withSession(['member_idx' => 2])->put(route('quizzes.update', $this->quizIdx), $payload)->assertForbidden();
        $this->withSession(['member_idx' => 3])->put(route('quizzes.update', $this->quizIdx), $payload)
            ->assertRedirect(route('quizzes.show', $this->quizIdx));
        $this->assertSame(3, (int) DB::table('quizzes')->where('idx', $this->quizIdx)->value('actor_member_idx'));
        $this->assertSame(1, (int) DB::table('quizzes')->where('idx', $this->quizIdx)->value('member_idx'));
    }

    public function test_edit_keeps_existing_image_question_when_no_replacement_is_uploaded(): void
    {
        $image = $this->quizImageUpload('original-question.png');
        $quizIdx = $this->createQuiz([$this->question('image', 'Keep this image', ['A', 'B', 'C', 'D', 'E'], 1, $image)]);
        $originalQuestion = DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 1)->first();
        $this->withSession(['member_idx' => 1])->put(route('quizzes.update', $quizIdx), [
            'title' => 'Test Quiz', 'quiz_version' => 1, 'idol_category' => 'boy', 'idol_idx' => 101,
            'questions' => [[
                'type' => 'image', 'prompt' => 'Updated prompt', 'choices' => ['A', 'B', 'C', 'D', 'E'],
                'correct_choice_order' => 1, 'existing_question_idx' => $originalQuestion->idx,
            ]],
        ])->assertRedirect(route('quizzes.show', $quizIdx));
        $updatedQuestion = DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('quiz_version', 2)->first();
        $this->assertSame($originalQuestion->image_path, $updatedQuestion->image_path);
        $this->assertTrue(Storage::disk('public')->exists(substr($updatedQuestion->image_path, strlen('storage/'))));
    }

    public function test_member_can_create_mixed_question_types_and_upload_image(): void
    {
        $image = $this->quizImageUpload('quiz.png');
        $cover = $this->quizImageUpload('cover.png');
        $quizLocation = $this->withSession(['member_idx' => 1])->post(route('quizzes.store'), [
            'title' => 'Mixed Question Types', 'idol_category' => 'girl_group', 'idol_idx' => 104, 'cover_image' => $cover, 'questions' => [
                $this->question('image', 'Which one?', ['A', 'B', 'C', 'D', 'E'], 4, $image),
                $this->question('choice', 'Select two', ['One', 'Two', 'Three', 'Four', 'Five'], 2),
                $this->question('ox', 'The sky is blue.', [], 1),
            ],
        ])->assertRedirect()->headers->get('Location');

        $createdIdx = (int) basename(parse_url($quizLocation, PHP_URL_PATH));
        $this->assertSame(3, DB::table('quizzes')->where('idx', $createdIdx)->value('question_count'));
        $createdQuiz = DB::table('quizzes')->where('idx', $createdIdx)->first();
        $this->assertSame('Mixed Question Types', $createdQuiz->title);
        $this->assertSame(104, (int) $createdQuiz->idol_idx);
        $this->assertTrue(Storage::disk('public')->exists(substr($createdQuiz->cover_image_path, strlen('storage/'))));
        $this->withSession(['member_idx' => 2])->get(route('quizzes.index', ['idol_category' => 'girl_group']))
            ->assertOk()->assertSee('Mixed Question Types')->assertDontSee('Which one?');
        $this->withSession(['member_idx' => 3])->get(route('quizzes.show', $createdIdx))
            ->assertOk()->assertSee('Girl Group')->assertSee('Mixed Question Types')->assertSee('Which one?')->assertSee(route('idol.show', 104))
            ->assertSee('class="quiz-card-eyebrow">Mixed Question Types</p>', false)
            ->assertSee('class="quiz-list-hero quiz-detail-hero" aria-labelledby="quiz-detail-hero-title"', false)
            ->assertDontSee('quiz-detail-title-section')
            ->assertDontSee('quiz-detail-author');
        $questions = DB::table('quiz_questions')->where('quiz_idx', $createdIdx)->orderBy('question_order')->get();
        $this->assertSame(['image', 'choice', 'ox'], $questions->pluck('question_type')->all());
        $this->assertSame(5, DB::table('quiz_question_choices')->where('question_idx', $questions[0]->idx)->count());
        $this->assertSame('O', DB::table('quiz_question_choices')->where('question_idx', $questions[2]->idx)->where('choice_order', 1)->value('choice_text'));
        $this->assertTrue(Storage::disk('public')->exists(substr($questions[0]->image_path, strlen('storage/'))));
        $this->withSession(['member_idx' => 1])->get(route('quizzes.show', $createdIdx))
            ->assertOk()->assertSee(__('quiz.creator_unlimited_retries'))
            ->assertSee('name="selected_choice_order"', false)->assertDontSee(__('quiz.owner_preview'))
            ->assertSee('class="quiz-card-eyebrow">Mixed Question Types</p>', false)
            ->assertDontSee('quiz-detail-title-section')
            ->assertDontSee(__('quiz.perfect_score_reward_label'))
            ->assertDontSee(__('quiz.participation_limit_title'))
            ->assertDontSee(__('quiz.preview_title'))
            ->assertDontSee(__('quiz.creator_test_only'));
        $this->withSession(['member_idx' => 2])->get(route('quizzes.index'))
            ->assertOk()->assertSee('Mixed Question Types')
            ->assertSee('images/no-image.png');
    }

    public function test_invalid_creation_uses_alert_and_does_not_save_quiz(): void
    {
        $this->withSession(['member_idx' => 1])->from(route('quizzes.create'))
            ->post(route('quizzes.store'), ['title' => 'Retain this title', 'idol_category' => 'boy', 'idol_idx' => 101, 'questions' => [['type' => 'choice', 'prompt' => 'Keep this value', 'choices' => ['A'], 'correct_choice_order' => 8]]])
            ->assertRedirect(route('quizzes.create'))->assertSessionHas('quiz_alert')
            ->assertSessionHas('_old_input.title', 'Retain this title')
            ->assertSessionHas('_old_input.questions.0.prompt', 'Keep this value');
        $this->assertSame(1, DB::table('quizzes')->count());
    }

    public function test_quiz_title_is_required_to_create_a_quiz(): void
    {
        $this->withSession(['member_idx' => 1])->from(route('quizzes.create'))
            ->post(route('quizzes.store'), [
                'idol_category' => 'boy', 'idol_idx' => 101,
                'questions' => [$this->question('choice', 'A valid question', ['A', 'B', 'C', 'D', 'E'], 1)],
            ])->assertRedirect(route('quizzes.create'))->assertSessionHas('quiz_alert');

        $this->assertSame(1, DB::table('quizzes')->count());
    }

    public function test_single_question_result_is_immediate_and_award_is_idempotent(): void
    {
        DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->update([
            'min_heart_amount' => 17, 'max_heart_amount' => 17,
        ]);
        $answerUrl = route('quizzes.answer', $this->quizIdx);
        $this->withSession(['member_idx' => 2])->post($answerUrl, ['question_order' => 1, 'selected_choice_order' => 2])->assertRedirect();
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertSee(__('quiz.result_perfect'))->assertSee(__('quiz.correct_answer_short'))->assertSee('Blue');
        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->first();
        $this->assertNotNull($attempt);
        $this->assertSame(now(config('app.timezone'))->toDateString(), $attempt->attempt_date);
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 2)->where('operation_key', 'like', 'quiz_perfect:%')->count());
        $creatorReward = DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$this->quizIdx.':2')->first();
        $this->assertNotNull($creatorReward);
        $this->assertSame(3, (int) $creatorReward->amount);
        $this->assertSame(2, (int) $creatorReward->actor_member_idx);
        $reward = DB::table('heart_histories')->where('member_idx', 2)->first();
        $this->assertSame(17, (int) $reward->amount);
        $this->assertSame((int) $reward->amount, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->withSession(['member_idx' => 2])->post($answerUrl, ['question_order' => 1, 'selected_choice_order' => 2])->assertRedirect();
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 2)->count());
        $this->assertSame((int) $reward->amount, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }

    public function test_completion_result_shows_server_award_and_incorrect_explanation(): void
    {
        $this->assertSame('퀴즈 완료!', __('quiz.result_title', [], 'ko'));
        $this->assertSame('Quiz Complete!', __('quiz.result_title', [], 'en'));
        DB::table('heart_reward_settings')->where('action_code', 'quiz_perfect')->update([
            'min_heart_amount' => 23, 'max_heart_amount' => 23,
        ]);
        $answerUrl = route('quizzes.answer', $this->quizIdx);
        $this->withSession(['member_idx' => 2])->post($answerUrl, [
            'question_order' => 1, 'selected_choice_order' => 2,
        ])->assertRedirect();

        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->first();
        $this->assertSame(23, (int) $attempt->awarded_hearts);
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertSee(__('quiz.result_title'))->assertSee(__('quiz.result_score_prefix', ['total' => 1]))
            ->assertSee('<strong>1</strong>', false)->assertSee(__('quiz.result_score_suffix', ['total' => 1]))
            ->assertSee(__('quiz.result_earned', ['amount' => '23']))
            ->assertSee('quiz-result-icon--success', false)->assertSee('data-quiz-like', false)
            ->assertSee(__('quiz.back_to_quiz_list'))->assertDontSee(__('quiz.random_dummy_retry'))
            ->assertSeeInOrder(['quiz-result-summary', 'quiz-result-reward', 'data-quiz-like', 'quiz-result-answers']);

        $quizIdx = $this->createQuiz([$this->question('choice', 'Incorrect result', ['A', 'B', 'C', 'D', 'E'], 2)]);
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $quizIdx), [
            'question_order' => 1, 'selected_choice_order' => 1,
        ])->assertRedirect();
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $quizIdx))
            ->assertOk()->assertSee(__('quiz.result_earned', ['amount' => '0']))
            ->assertSee(__('quiz.result_incorrect_explanation'))->assertSee('quiz-result-answer--incorrect', false)
            ->assertSee(__('quiz.answer_incorrect'));
    }

    public function test_quiz_likes_toggle_idempotently_and_require_a_member(): void
    {
        $this->flushSession()->get(route('quizzes.show', $this->quizIdx))->assertOk()
            ->assertSee('data-quiz-like', false)->assertSee('data-signed-in="false"', false);
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $this->quizIdx), [
            'question_order' => 1, 'selected_choice_order' => 1,
        ])->assertRedirect();
        $likesUrl = route('quizzes.like', $this->quizIdx);
        $historyCount = DB::table('heart_histories')->count();

        $this->withSession(['member_idx' => 2])->postJson($likesUrl, ['liked' => true])
            ->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.count', 1);
        $this->withSession(['member_idx' => 2])->postJson($likesUrl, ['liked' => true])
            ->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.count', 1);
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertSee('data-liked="true"', false)->assertSee('data-quiz-like-count>1</span>', false);
        $this->withSession(['member_idx' => 2])->postJson($likesUrl, ['liked' => false])
            ->assertOk()->assertJsonPath('data.liked', false)->assertJsonPath('data.count', 0);
        $this->withSession(['member_idx' => 2])->postJson($likesUrl, ['liked' => false])
            ->assertOk()->assertJsonPath('data.liked', false)->assertJsonPath('data.count', 0);
        $this->assertSame($historyCount, DB::table('heart_histories')->count());
        $this->assertSame(0, DB::table('quiz_likes')->where('quiz_idx', $this->quizIdx)->count());
        $this->flushSession()->postJson($likesUrl, ['liked' => true])->assertUnauthorized();
    }

    public function test_admin_dummy_quiz_like_uses_the_reserved_dummy_member(): void
    {
        $requestKey = str_repeat('a', 64);
        $payloadHash = hash('sha256', json_encode(
            ['quiz_idx' => $this->quizIdx, 'quiz_version' => 1],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $attemptIdx = (int) DB::table('quiz_attempts')->insertGetId([
            'quiz_idx' => $this->quizIdx, 'member_idx' => 4, 'actor_member_idx' => 3,
            'attempt_date' => now('Asia/Seoul')->toDateString(), 'status' => 'completed', 'question_count' => 1,
            'quiz_version' => 1, 'is_test_only' => 0, 'score' => 0, 'is_perfect' => 0, 'awarded_hearts' => 0,
            'started_at' => now(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('admin_dummy_activity_requests')->insert([
            'actor_member_idx' => 3, 'member_idx' => 4, 'action_code' => 'quiz_participation',
            'request_key' => $requestKey, 'payload_hash' => $payloadHash, 'result_type' => 'quiz_attempt',
            'result_idx' => $attemptIdx, 'created_at' => now(), 'completed_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 3, 'random_dummy_activity_mode' => true])
            ->postJson(route('quizzes.like', $this->quizIdx), [
                'liked' => true, 'request_key' => $requestKey, 'quiz_version' => 1,
            ])->assertOk()->assertJsonPath('data.liked', true)->assertJsonPath('data.count', 1);
        $this->assertDatabaseHas('quiz_likes', [
            'quiz_idx' => $this->quizIdx, 'member_idx' => 4, 'actor_member_idx' => 3,
        ]);
    }

    public function test_wrong_answer_rewards_creator_once_and_next_day_retry_does_not_repeat_creator_reward(): void
    {
        $url = route('quizzes.answer', $this->quizIdx);
        $this->withSession(['member_idx' => 2])->post($url, ['question_order' => 1, 'selected_choice_order' => 1])->assertRedirect();
        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->first();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(0, (int) $attempt->awarded_hearts);
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 2)->count());
        $creatorRewardKey = 'quiz_creator:'.$this->quizIdx.':2';
        $this->assertSame(3, (int) DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', $creatorRewardKey)->value('amount'));
        $this->withSession(['member_idx' => 2])->post($url, ['question_order' => 1, 'selected_choice_order' => 2])->assertRedirect();
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->count());
        $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $this->quizIdx))->assertSee(__('quiz.result_incorrect_explanation'));

        $this->travel(1)->days();
        $this->withSession(['member_idx' => 2])->post($url, ['question_order' => 1, 'selected_choice_order' => 2])->assertRedirect();
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', $creatorRewardKey)->count());
        $this->assertSame(2, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 2)->count());
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 2)->where('reason', 'quiz_perfect')->count());
    }

    public function test_quiz_creator_can_retry_repeatedly_without_hearts_and_sees_answer_results(): void
    {
        $url = route('quizzes.answer', $this->quizIdx);
        $showUrl = route('quizzes.show', $this->quizIdx);
        $this->withSession(['member_idx' => 1])->get($showUrl)
            ->assertOk()->assertSee(__('quiz.creator_unlimited_retries'))
            ->assertSee('name="selected_choice_order"', false)->assertDontSee(__('quiz.owner_preview'))
            ->assertSee('<p class="quiz-card-eyebrow">Test Quiz</p>', false)
            ->assertSee(__('quiz.question_progress_badge', ['current' => 1, 'total' => 1]))
            ->assertDontSee(__('quiz.perfect_score_reward_label'))
            ->assertDontSee(__('quiz.participation_limit_title'))
            ->assertDontSee(__('quiz.preview_title'))
            ->assertDontSee(__('quiz.creator_test_only'));

        $this->withSession(['member_idx' => 1])->post($url, ['question_order' => 1, 'selected_choice_order' => 2])->assertRedirect();
        $this->withSession(['member_idx' => 1])->get($showUrl)
            ->assertOk()->assertSee(__('quiz.result_perfect'))
            ->assertSee(__('quiz.result_earned', ['amount' => '0']))
            ->assertSee(__('quiz.result_creator_test'))
            ->assertDontSee(__('quiz.result_incorrect_explanation'))
            ->assertSee(__('quiz.correct_answer_short'))->assertSee('Blue')
            ->assertDontSee(__('quiz.result_reward', ['amount' => '20']))
            ->assertSee('name="restart_attempt"', false);
        $this->assertSame(0, (int) DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 1)->value('awarded_hearts'));
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)->where('operation_key', 'like', 'quiz_perfect:%')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$this->quizIdx.':1')->count());

        $retryResponse = $this->withSession(['member_idx' => 1])->post($url, ['restart_attempt' => 1, 'quiz_version' => 1]);
        $retryToken = session('quiz_retry_tokens.'.$this->quizIdx);
        $this->assertIsString($retryToken);
        $retryUrl = route('quizzes.show', ['idx' => $this->quizIdx, 'owner_retry_token' => $retryToken]);
        $retryResponse->assertRedirect($retryUrl);
        $this->withSession(['member_idx' => 1])->get($retryUrl)
            ->assertOk()->assertSee('name="selected_choice_order"', false);
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 1)->count());
        $this->withSession(['member_idx' => 1])->post($url, [
            'answers' => [1 => 1], 'question_order' => 1, 'selected_choice_order' => 1,
            'quiz_version' => 1, 'owner_retry_token' => $retryToken,
        ])->assertRedirect();
        $this->withSession(['member_idx' => 1])->get($showUrl)
            ->assertOk()->assertSee(__('quiz.result_incorrect_explanation'))->assertSee(__('quiz.result_creator_test'));

        $attempts = DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 1)->orderBy('idx')->get();
        $this->assertCount(2, $attempts);
        $this->assertSame(['completed', 'completed'], $attempts->pluck('status')->all());
        $this->assertSame([0, 0], $attempts->pluck('awarded_hearts')->map(fn ($amount) => (int) $amount)->all());
        $this->assertSame(2, DB::table('quiz_attempt_answers')->where('quiz_idx', $this->quizIdx)->count());
        $this->assertSame([1, 1], $attempts->pluck('is_test_only')->map(fn ($flag) => (int) $flag)->all());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)->where('operation_key', 'like', 'quiz_perfect:%')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$this->quizIdx.':1')->count());
    }

    public function test_random_dummy_quiz_participation_rewards_ranks_and_replays_as_selected_member(): void
    {
        $this->travelTo(Carbon::parse('2026-10-19 12:00:00', 'Asia/Seoul'));
        $key = str_repeat('c', 64);
        $session = ['member_idx' => 3];
        $answer = [
            'request_key' => $key, 'quiz_version' => 1,
            'question_order' => 1, 'selected_choice_order' => 2,
        ];
        $url = route('quizzes.answer', $this->quizIdx);
        $this->withSession($session)->post($url, $answer)
            ->assertRedirect(route('quizzes.show', ['idx' => $this->quizIdx, 'request_key' => $key]));

        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->first();
        $this->assertSame(4, (int) $attempt->member_idx);
        $this->assertSame(3, (int) $attempt->actor_member_idx);
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(0, (int) $attempt->is_test_only);
        $this->assertGreaterThanOrEqual(10, (int) $attempt->awarded_hearts);
        $this->assertLessThanOrEqual(30, (int) $attempt->awarded_hearts);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 4, 'actor_member_idx' => 3, 'reason' => 'quiz_perfect',
            'operation_key' => 'quiz_perfect:'.$attempt->idx,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 1, 'actor_member_idx' => 3, 'reason' => 'quiz_creator_reward_1',
            'operation_key' => 'quiz_creator:'.$this->quizIdx.':4',
        ]);
        $reservation = DB::table('admin_dummy_activity_requests')->where('action_code', 'quiz_participation')->first();
        $this->assertSame((int) $attempt->idx, (int) $reservation->result_idx);
        $this->assertNotNull($reservation->completed_at);
        $this->assertSame([$this->quizIdx], app(QuizWeeklyParticipationRanking::class)
            ->rankQuizzes(Carbon::parse('2026-10-19 00:00:00', 'Asia/Seoul'), Carbon::parse('2026-10-26 00:00:00', 'Asia/Seoul'))
            ->pluck('quiz_idx')->all());

        $this->withSession($session)->post($url, $answer)->assertRedirect();
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->count());
        $this->assertSame(2, DB::table('heart_histories')->whereIn('reason', ['quiz_perfect', 'quiz_creator_reward_1'])->count());
        $this->withSession($session)->get(route('quizzes.show', ['idx' => $this->quizIdx, 'request_key' => $key]))
            ->assertOk()->assertSee(__('quiz.result_earned', ['amount' => number_format($attempt->awarded_hearts)]));
        $this->withSession(['member_idx' => 4])->get(route('quizzes.show', $this->quizIdx))
            ->assertOk()->assertDontSee(__('quiz.random_dummy_retry'));
        $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $this->quizIdx), [
            'restart_attempt' => 1, 'quiz_version' => 1, 'request_key' => str_repeat('f', 64),
        ])->assertForbidden();

        $this->withSession($session)->post($url, array_merge($answer, ['request_key' => str_repeat('d', 64)]))
            ->assertSessionHasErrors('random_dummy');
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->count());
    }

    public function test_random_dummy_creator_owned_quiz_attempts_are_test_only_and_can_retry(): void
    {
        DB::table('quizzes')->where('idx', $this->quizIdx)->update(['member_idx' => 4, 'actor_member_idx' => 3]);
        $session = ['member_idx' => 3];
        $url = route('quizzes.answer', $this->quizIdx);
        $key = str_repeat('e', 64);
        $this->withSession($session)->post($url, [
            'request_key' => $key, 'quiz_version' => 1, 'question_order' => 1, 'selected_choice_order' => 2,
        ])->assertRedirect();
        $this->assertSame(1, (int) DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->value('is_test_only'));
        $this->assertSame(0, DB::table('heart_histories')->count());
        $weekStart = Carbon::parse('2026-10-19 00:00:00', 'Asia/Seoul');
        $this->assertCount(0, app(QuizWeeklyParticipationRanking::class)->rankQuizzes($weekStart, $weekStart->copy()->addWeek()));

        $show = $this->withSession($session)->get(route('quizzes.show', ['idx' => $this->quizIdx, 'request_key' => $key]));
        $show->assertOk()->assertSee(__('quiz.random_dummy_retry'))->assertSee(__('quiz.back_to_quiz_list'))
            ->assertSeeInOrder(['quiz-result-summary', 'quiz-result-reward', 'data-quiz-like', 'quiz-result-answers'])
            ->assertSeeInOrder([__('quiz.back_to_quiz_list'), __('quiz.random_dummy_retry')]);
        preg_match('/name="request_key" value="([a-f0-9]{64})"/', $show->getContent(), $retryMatch);
        $this->assertCount(2, $retryMatch);
        $retryKey = $retryMatch[1];
        $this->withSession($session)->post($url, [
            'request_key' => $retryKey, 'restart_attempt' => 1, 'quiz_version' => 1,
        ])->assertRedirect(route('quizzes.show', ['idx' => $this->quizIdx, 'request_key' => $retryKey]));
        $this->assertSame(1, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 4)->count());
        $this->withSession($session)->get(route('quizzes.show', ['idx' => $this->quizIdx, 'request_key' => $retryKey]))
            ->assertOk()->assertSee('name="selected_choice_order"', false);
        $this->withSession($session)->post($url, [
            'request_key' => $retryKey, 'quiz_version' => 1, 'question_order' => 1, 'selected_choice_order' => 2,
        ])->assertRedirect();

        $this->assertSame(2, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('member_idx', 4)->count());
        $this->assertSame(2, DB::table('quiz_attempts')->where('quiz_idx', $this->quizIdx)->where('is_test_only', 1)->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertCount(0, app(QuizWeeklyParticipationRanking::class)->rankQuizzes($weekStart, $weekStart->copy()->addWeek()));
    }

    public function test_multi_question_refresh_starts_at_question_one_and_only_final_submission_persists_answers(): void
    {
        $quizIdx = $this->createQuiz([
            $this->question('choice', 'First', ['A', 'B', 'C', 'D', 'E'], 1),
            $this->question('ox', 'Second', [], 2),
        ]);
        $url = route('quizzes.answer', $quizIdx);
        $showUrl = route('quizzes.show', $quizIdx);
        $initial = $this->withSession(['member_idx' => 2])->get($showUrl)
            ->assertOk()->assertSee('First')->assertSee('Second')
            ->assertDontSee('correct_choice_order')->assertDontSee('is_correct');
        $this->assertSame(0, DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->count());
        $this->assertSame(0, DB::table('quiz_attempt_answers')->where('quiz_idx', $quizIdx)->count());

        $this->withSession(['member_idx' => 2])->post($url, [
            'question_order' => 1, 'selected_choice_order' => 1, 'quiz_version' => 1,
        ])->assertSessionHasErrors('answers');
        $this->assertSame(0, DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->count());

        // Simulate a legacy unfinished record: a reload must not restore its saved answer.
        $firstQuestion = DB::table('quiz_questions')->where('quiz_idx', $quizIdx)->where('question_order', 1)->first();
        $startedAt = now();
        $legacyAttemptIdx = (int) DB::table('quiz_attempts')->insertGetId([
            'quiz_idx' => $quizIdx, 'member_idx' => 2, 'actor_member_idx' => 2,
            'attempt_date' => now('Asia/Seoul')->toDateString(), 'status' => 'in_progress',
            'question_count' => 2, 'quiz_version' => 1, 'is_test_only' => 0,
            'started_at' => $startedAt, 'created_at' => $startedAt, 'updated_at' => $startedAt,
        ]);
        DB::table('quiz_attempt_answers')->insert([
            'attempt_idx' => $legacyAttemptIdx, 'quiz_idx' => $quizIdx, 'question_idx' => $firstQuestion->idx,
            'question_order' => 1, 'selected_choice_order' => 1, 'is_correct' => null, 'submitted_at' => $startedAt,
        ]);

        $refreshed = $this->withSession(['member_idx' => 2])->get($showUrl)->assertOk();
        $this->assertMatchesRegularExpression('/<article[^>]*data-quiz-question="1"[^>]*>/', $refreshed->getContent());
        $this->assertMatchesRegularExpression('/<article[^>]*data-quiz-question="2"[^>]*\shidden(?:\s|>)/', $refreshed->getContent());
        $this->assertDoesNotMatchRegularExpression('/name="selected_choice_order" value="[1-5]" checked/', $refreshed->getContent());

        $this->withSession(['member_idx' => 2])->post($url, [
            'answers' => [1 => 2, 2 => 2], 'question_order' => 2, 'selected_choice_order' => 2, 'quiz_version' => 1,
        ])->assertRedirect();
        $this->assertSame('completed', DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', 2)->value('status'));
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 2)->count());
        $this->assertSame(5, (int) DB::table('heart_histories')->where('member_idx', 1)
            ->where('operation_key', 'quiz_creator:'.$quizIdx.':2')->value('amount'));
    }

    public function test_multi_question_navigation_keeps_answers_until_final_submission_and_grades_latest_choices(): void
    {
        $quizIdx = $this->createQuiz([
            $this->question('choice', 'First navigation question', ['A', 'B', 'C', 'D', 'E'], 1),
            $this->question('choice', 'Second navigation question', ['A', 'B', 'C', 'D', 'E'], 2),
        ]);
        $answerUrl = route('quizzes.answer', $quizIdx);

        $showResponse = $this->withSession(['member_idx' => 2])->get(route('quizzes.show', $quizIdx));
        $showResponse
            ->assertOk()
            ->assertSee('data-quiz-question="1"', false)
            ->assertSee('data-quiz-question="2"', false)
            ->assertSee(__('quiz.question_progress_badge', ['current' => 1, 'total' => 2]))
            ->assertSee(__('quiz.question_progress_badge', ['current' => 2, 'total' => 2]))
            ->assertSee('data-quiz-next="1"', false)
            ->assertDontSee('data-quiz-previous="1"', false)
            ->assertSee('data-quiz-previous="2"', false)
            ->assertSee(__('quiz.previous_question'));
        $this->assertMatchesRegularExpression('/<article[^>]*data-quiz-question="2"[^>]*\shidden(?:\s|>)/', $showResponse->getContent());

        // Moving between the rendered question panels is client-side; loading the page creates no attempt.
        $this->assertSame(0, DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', 2)->count());
        $this->assertSame(0, DB::table('quiz_attempt_answers')->where('quiz_idx', $quizIdx)->count());

        // Submit the final choices, including a changed answer for the first question.
        $this->withSession(['member_idx' => 2])->post($answerUrl, [
            'answers' => [1 => 2, 2 => 2],
            'quiz_version' => 1,
        ])->assertRedirect();

        $attempt = DB::table('quiz_attempts')->where('quiz_idx', $quizIdx)->where('member_idx', 2)->first();
        $this->assertSame('completed', $attempt->status);
        $this->assertSame(1, (int) $attempt->score);
        $this->assertSame(2, (int) DB::table('quiz_attempt_answers')->where('attempt_idx', $attempt->idx)
            ->where('question_order', 1)->value('selected_choice_order'));
        $this->assertSame(2, (int) DB::table('quiz_attempt_answers')->where('attempt_idx', $attempt->idx)
            ->where('question_order', 2)->value('selected_choice_order'));
    }

    public function test_creator_reward_amount_matches_each_quiz_question_count(): void
    {
        foreach ([1 => 3, 2 => 5, 3 => 10] as $questionCount => $expectedAmount) {
            $questions = [];
            for ($order = 1; $order <= $questionCount; $order++) {
                $questions[] = $this->question('choice', 'Question '.$order, ['A', 'B', 'C', 'D', 'E'], 1);
            }
            $quizIdx = $this->createQuiz($questions, 'Reward Count '.$questionCount);
            $answers = array_fill(1, $questionCount, 1);
            $this->withSession(['member_idx' => 2])->post(route('quizzes.answer', $quizIdx), [
                'answers' => $answers, 'question_order' => $questionCount, 'selected_choice_order' => 1, 'quiz_version' => 1,
            ])->assertRedirect();

            $history = DB::table('heart_histories')->where('member_idx', 1)
                ->where('operation_key', 'quiz_creator:'.$quizIdx.':2')->first();
            $this->assertNotNull($history);
            $this->assertSame($expectedAmount, (int) $history->amount);
        }
    }

    public function test_weekly_ranking_uses_seoul_week_and_daily_quiz_counts_and_excludes_test_attempts(): void
    {
        $weekStart = Carbon::parse('2026-10-19 00:00:00', 'Asia/Seoul');
        $weekEnd = $weekStart->copy()->addWeek();
        $secondQuizIdx = $this->createQuiz([
            $this->question('choice', 'Another quiz', ['A', 'B', 'C', 'D', 'E'], 1),
        ], 'Another Quiz');

        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy(), false, false);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->setTime(12, 0), false, true);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->setTime(23, 59), false, false);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->addDay(), false, false);
        $this->recordCompletedAttempt($secondQuizIdx, 2, $weekStart->copy()->setTime(23, 59), false, false);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->subSecond(), false, false);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekEnd, false, false);
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->setTime(13, 0), true, false);
        $this->recordCompletedAttempt($this->quizIdx, 1, $weekStart->copy()->setTime(14, 0), false, true);
        $this->recordCompletedAttempt($this->quizIdx, 3, $weekStart->copy(), false, false);

        $ranking = app(QuizWeeklyParticipationRanking::class)->rank($weekStart, $weekEnd);
        $quizRanking = app(QuizWeeklyParticipationRanking::class)->rankQuizzes($weekStart, $weekEnd);

        $this->assertSame([2, 3], $ranking->pluck('member_idx')->all());
        $this->assertSame([3, 1], $ranking->pluck('participation_count')->all());
        $this->assertSame([$this->quizIdx, $secondQuizIdx], $quizRanking->pluck('quiz_idx')->all());
        $this->assertSame([3, 1], $quizRanking->pluck('completion_count')->all());
        $this->travelTo($weekStart->copy()->addDays(6)->setTime(12, 0));
        $response = $this->withSession(['member_idx' => 2])->get(route('quizzes.index'));
        $response->assertOk()->assertSee(__('quiz.weekly_quiz_ranking_title'))
            ->assertSee(__('quiz.monthly_quiz_ranking_title'))
            ->assertSee(route('quizzes.show', $this->quizIdx), false)
            ->assertSee('Test Quiz')->assertSee('Another Quiz')
            ->assertDontSee(__('quiz.weekly_participation_count', ['count' => 3]));

        $html = $response->getContent();
        $rankingStart = strpos($html, '<section class="quiz-weekly-ranking"');
        $rankingEnd = strpos($html, '<div class="quiz-list-heading-row"', $rankingStart);
        $rankingHtml = substr($html, $rankingStart, $rankingEnd - $rankingStart);
        $this->assertStringNotContainsString('Player', $rankingHtml);
        $this->assertStringNotContainsString('Creator', $rankingHtml);
        preg_match('/<section class="quiz-ranking-column quiz-ranking-column--monthly".*?<\/section>/s', $rankingHtml, $monthlyMarkup);
        $this->assertCount(1, $monthlyMarkup);
        $this->assertStringNotContainsString('quiz-weekly-ranking-rewards', $monthlyMarkup[0]);
    }

    public function test_monthly_popular_quiz_ranking_can_be_nonempty_when_weekly_ranking_is_empty(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'Asia/Seoul'));
        $this->recordCompletedAttempt($this->quizIdx, 2, Carbon::parse('2026-10-12 12:00:00', 'Asia/Seoul'));

        $response = $this->get(route('quizzes.index'));
        $response->assertOk()->assertSee(__('quiz.weekly_quiz_ranking_empty'))
            ->assertSee(__('quiz.monthly_quiz_ranking_title'))
            ->assertDontSee(__('quiz.monthly_quiz_ranking_period', [
                'start' => '2026.10.01', 'end' => '2026.11.01',
            ]));
        $this->assertSame(0, DB::table('quiz_weekly_settlements')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('reason', 'like', 'quiz_weekly_rank_%')->count());
    }

    public function test_weekly_popular_quiz_ranking_can_be_nonempty_when_monthly_ranking_is_empty(): void
    {
        $this->travelTo(Carbon::parse('2026-10-01 12:00:00', 'Asia/Seoul'));
        $this->recordCompletedAttempt($this->quizIdx, 2, Carbon::parse('2026-09-29 12:00:00', 'Asia/Seoul'));

        $this->get(route('quizzes.index'))->assertOk()
            ->assertSee(__('quiz.monthly_quiz_ranking_empty'))
            ->assertSee(__('quiz.weekly_quiz_ranking_title'))
            ->assertSee('Test Quiz');
    }

    public function test_weekly_ranking_breaks_equal_count_ties_by_reached_time_then_member_id(): void
    {
        DB::table('members')->where('idx', 3)->update(['member_level' => 1]);
        $fourthMember = [
            'idx' => 7, 'member_code' => 'TieMember007', 'email' => 'tie@example.test', 'nickname' => 'TieMember',
            'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null, 'heart_balance' => 0,
        ];
        DB::table('members')->insert($fourthMember);
        $weekStart = Carbon::parse('2026-10-19 00:00:00', 'Asia/Seoul');
        $secondQuizIdx = $this->createQuiz([
            $this->question('choice', 'Another quiz', ['A', 'B', 'C', 'D', 'E'], 1),
        ], 'Another Quiz');

        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->setTime(8, 0));
        $this->recordCompletedAttempt($secondQuizIdx, 2, $weekStart->copy()->addDay()->setTime(10, 0));
        foreach ([3, 7] as $memberIdx) {
            $this->recordCompletedAttempt($this->quizIdx, $memberIdx, $weekStart->copy()->setTime(8, 0));
            $this->recordCompletedAttempt($secondQuizIdx, $memberIdx, $weekStart->copy()->setTime(9, 0));
        }

        $ranking = app(QuizWeeklyParticipationRanking::class)->rank($weekStart, $weekStart->copy()->addWeek());

        $this->assertSame([3, 7, 2], $ranking->pluck('member_idx')->all());
        $this->assertSame([2, 2, 2], $ranking->pluck('participation_count')->all());
    }

    public function test_quiz_ranking_breaks_equal_count_ties_by_reached_time_then_quiz_id(): void
    {
        DB::table('members')->where('idx', 3)->update(['member_level' => 1]);
        $weekStart = Carbon::parse('2026-10-19 00:00:00', 'Asia/Seoul');
        $secondQuizIdx = $this->createQuiz([
            $this->question('choice', 'Second quiz question', ['A', 'B', 'C', 'D', 'E'], 1),
        ], 'Second Quiz');
        $thirdQuizIdx = $this->createQuiz([
            $this->question('choice', 'Third quiz question', ['A', 'B', 'C', 'D', 'E'], 1),
        ], 'Third Quiz');

        foreach ([2, 3] as $memberIdx) {
            $this->recordCompletedAttempt($this->quizIdx, $memberIdx, $weekStart->copy()->setTime(8, 0));
            $this->recordCompletedAttempt($secondQuizIdx, $memberIdx, $weekStart->copy()->addDay()->setTime(9, 0));
            $this->recordCompletedAttempt($thirdQuizIdx, $memberIdx, $weekStart->copy()->addDay()->setTime(9, 0));
        }

        $ranking = app(QuizWeeklyParticipationRanking::class)->rankQuizzes($weekStart, $weekStart->copy()->addWeek());

        $this->assertSame([$this->quizIdx, $secondQuizIdx, $thirdQuizIdx], $ranking->pluck('quiz_idx')->all());
        $this->assertSame([2, 2, 2], $ranking->pluck('completion_count')->all());
    }

    public function test_weekly_settlement_awards_only_occupied_ranks_and_retries_are_idempotent(): void
    {
        DB::table('heart_reward_settings')->where('action_code', 'quiz_weekly_rank_1')->update(['heart_amount' => 1300]);
        DB::table('heart_reward_settings')->where('action_code', 'quiz_weekly_rank_2')->update(['heart_amount' => 450, 'is_enabled' => 0]);
        DB::table('members')->where('idx', 3)->update(['member_level' => 1]);
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', 'Asia/Seoul'));
        $weekStart = Carbon::parse('2026-10-12 00:00:00', 'Asia/Seoul');
        $secondQuizIdx = $this->createQuiz([
            $this->question('choice', 'Another quiz', ['A', 'B', 'C', 'D', 'E'], 1),
        ], 'Another Quiz');
        $this->recordCompletedAttempt($this->quizIdx, 2, $weekStart->copy()->setTime(8, 0));
        $this->recordCompletedAttempt($secondQuizIdx, 2, $weekStart->copy()->addDay()->setTime(8, 0));
        $this->recordCompletedAttempt($this->quizIdx, 3, $weekStart->copy()->setTime(7, 0));

        $this->artisan('quizzes:settle-weekly-ranking', ['--week' => '2026-10-19'])->assertExitCode(1);
        $this->assertSame(0, DB::table('quiz_weekly_settlements')->count());
        $this->artisan('quizzes:settle-weekly-ranking', ['--week' => '2026-10-12'])->assertExitCode(0);
        $this->assertSame([1300, 0], DB::table('quiz_weekly_settlement_rewards')->orderBy('rank_position')->pluck('reward_amount')->all());
        $this->assertSame(2, DB::table('quiz_weekly_settlement_rewards')->count());
        $this->assertSame(1300, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 1)->where('reason', 'quiz_weekly_rank_1')
            ->where('operation_key', 'quiz_weekly:2026-10-12:1:quiz:'.$this->quizIdx.':rank:1')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', 1)->where('reason', 'quiz_weekly_rank_2')
            ->where('operation_key', 'quiz_weekly:2026-10-12:1:quiz:'.$secondQuizIdx.':rank:2')->count());
        $this->assertSame([$this->quizIdx, $secondQuizIdx], DB::table('quiz_weekly_settlement_rewards')
            ->orderBy('rank_position')->pluck('quiz_idx')->all());

        $this->artisan('quizzes:settle-weekly-ranking', ['--week' => '2026-10-12'])->assertExitCode(0);
        $this->assertSame(2, DB::table('quiz_weekly_settlement_rewards')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('reason', 'like', 'quiz_weekly_rank_%')->count());
        $this->assertSame(1300, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
    }

    public function test_dummy_members_cannot_create_quizzes_or_receive_quiz_rewards(): void
    {
        $url = route('quizzes.answer', $this->quizIdx);
        $this->withSession(['member_idx' => 4])->post($url, ['question_order' => 1, 'selected_choice_order' => 2])->assertForbidden();
        $this->withSession(['member_idx' => 4])->get(route('quizzes.create'))->assertForbidden();
        $this->withSession(['member_idx' => 4])->post(route('quizzes.store'), ['questions' => [
            $this->question('choice', 'Unauthorized create', ['A', 'B', 'C', 'D', 'E'], 1),
        ]])->assertForbidden();
        $this->assertSame(0, DB::table('quiz_attempts')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }
}
