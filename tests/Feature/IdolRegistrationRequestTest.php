<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\IdolRegistrationRequestNotification;
use Tests\TestCase;

class IdolRegistrationRequestTest extends TestCase
{
    private function duplicateSearchToken(string $requestType, string $englishName): string
    {
        $response = $this->getJson(route('idol-registration.duplicates', [
            'request_type' => $requestType,
            'english_name' => $englishName,
        ]));
        $response->assertOk()->assertJsonPath('success', true);
        return (string) $response->json('token');
    }

    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }
        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, english_name TEXT, image_path TEXT, type TEXT, gender TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, language_code))");
        DB::statement("CREATE TABLE idol_name_aliases (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, alias_name TEXT NOT NULL, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, created_at TEXT, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE heart_reward_settings (action_code TEXT PRIMARY KEY, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, actor_member_idx INTEGER, type TEXT, gender TEXT, name TEXT NOT NULL, english_name TEXT, group_idx INTEGER, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, sns TEXT, sns_links TEXT, image_path TEXT, content TEXT, reference_url TEXT, note TEXT, request_key TEXT, status TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_registration_request_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, request_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(request_idx, country_code))");
        DB::statement("CREATE TABLE idol_registration_request_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, request_idx INTEGER NOT NULL, language_code TEXT NOT NULL, text_role TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(request_idx, language_code, text_role))");
        DB::table('members')->insert(['idx' => 7, 'member_code' => 'MemberCode07', 'email' => 'fan@example.test', 'nickname' => 'fan', 'country_code' => 'KR']);
        DB::table('idols')->insert(['idx' => 5, 'name' => 'Demo Group', 'english_name' => 'Demo Group', 'image_path' => null, 'type' => 'group', 'gender' => 'female', 'status' => 'active']);
        DB::table('heart_reward_settings')->insert(['action_code' => 'idol_registration_approved', 'heart_amount' => 4321, 'daily_limit' => 0, 'is_enabled' => 1]);
    }

    public function test_guest_is_sent_to_the_existing_sign_in_flow(): void
    {
        $this->get('/idol-registration/request')->assertRedirect('/')->assertSessionHas('open_sign_in', true);
    }

    public function test_member_can_open_page_and_submit_pending_request(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->where('idx', 7)->update(['nickname' => '<script>applicant</script>']);
        DB::table('members')->insert([
            'idx' => 10, 'member_code' => 'AdminCode10', 'email' => 'admin@example.test', 'nickname' => 'admin',
            'member_level' => 10, 'is_dummy' => 0, 'status' => 'active',
        ]);
        DB::table('members')->insert([
            ['idx' => 11, 'member_code' => 'DummyAdmin11', 'email' => 'dummy-admin@example.test', 'nickname' => 'dummy admin', 'member_level' => 10, 'is_dummy' => 1, 'status' => 'active'],
            ['idx' => 12, 'member_code' => 'InactiveAdmin12', 'email' => 'inactive-admin@example.test', 'nickname' => 'inactive admin', 'member_level' => 10, 'is_dummy' => 0, 'status' => 'inactive'],
        ]);
        Mail::fake();

        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertSee('Request Idol Registration')->assertSee('Register Idol')
            ->assertSee('Approval Reward:')->assertSee('+4,321')
            ->assertSee('Hearts are awarded after your request is approved.');

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('a', 64)]);
        $response = $this->post('/idol-registration/request', [
            'request_key' => str_repeat('a', 64),
            'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Test Idol'),
            'request_type' => 'girl', 'name' => '테스트', 'english_name' => 'Test Idol',
            'group_idx' => 5, 'birth_date' => '2000-01-02',
            'height_cm' => '168.5', 'weight_kg' => '50.5', 'blood_type' => 'A',
            'agency' => '테스트 기획사', 'position' => '보컬',
            'debut_dates' => [['country_code' => 'KR', 'debut_date' => '2020-02-03'], ['country_code' => 'JP', 'debut_date' => '2021-04-05']],
            'genre' => 'Should be ignored', 'leader' => 'Should be ignored',
            'sns' => '@testidol', 'content' => 'A concise and accurate introduction.', 'source_language_code' => 'ko',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ]);

        $response->assertRedirect(route('idol-registration.create'))->assertSessionHas('success');
        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame(7, $saved->member_idx);
        $this->assertSame(7, $saved->actor_member_idx);
        $this->assertSame('individual', $saved->type);
        $this->assertSame('female', $saved->gender);
        $this->assertSame('pending', $saved->status);
        $this->assertSame('테스트', $saved->name);
        $this->assertSame(5, $saved->group_idx);
        $this->assertNull($saved->genre);
        $this->assertNull($saved->leader);
        $this->assertDatabaseHas('idol_registration_request_debuts', ['request_idx' => $saved->idx, 'country_code' => 'KR', 'debut_date' => '2020-02-03']);
        $this->assertDatabaseHas('idol_registration_request_debuts', ['request_idx' => $saved->idx, 'country_code' => 'JP', 'debut_date' => '2021-04-05']);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => $saved->idx, 'language_code' => 'ko', 'text_role' => 'submitted',
            'agency' => '테스트 기획사', 'position' => '보컬',
            'description' => 'A concise and accurate introduction.',
        ]);
        Storage::disk('public')->assertExists(substr($saved->image_path, 8));
        Mail::assertSent(IdolRegistrationRequestNotification::class, function (IdolRegistrationRequestNotification $mail) use ($saved): bool {
            $rendered = $mail->render();

            return $mail->hasTo('admin@example.test')
                && $mail->requestType === 'individual'
                && $mail->englishName === 'Test Idol'
                && $mail->applicantNickname === '<script>applicant</script>'
                && $mail->submittedAt === $saved->created_at
                && $mail->reviewUrl === route('admin.idol-requests.show', ['idx' => $saved->idx])
                && str_contains($rendered, '&lt;script&gt;applicant&lt;/script&gt;')
                && !str_contains($rendered, '<script>applicant</script>');
        });
        Mail::assertSentCount(1);
    }

    public function test_mail_delivery_failure_does_not_undo_a_saved_registration_request(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->insert([
            'idx' => 10, 'member_code' => 'AdminCode10', 'email' => 'admin@example.test', 'nickname' => 'admin',
            'member_level' => 10, 'is_dummy' => 0, 'status' => 'active',
        ]);
        Mail::shouldReceive('mailer')->once()->with('smtp')
            ->andThrow(new \RuntimeException('Simulated mail transport failure.'));
        $key = str_repeat('f', 64);
        $search = $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->getJson(route('idol-registration.duplicates', ['request_type' => 'girl_group', 'english_name' => 'Saved Despite Mail Failure']))
            ->assertOk();

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key,
                'duplicate_search_token' => $search->json('token'),
                'request_type' => 'girl_group',
                'english_name' => 'Saved Despite Mail Failure',
                'source_language_code' => 'en',
                'content' => 'Group introduction',
                'profile_image' => UploadedFile::fake()->image('group.png'),
            ])->assertRedirect(route('idol-registration.create'))->assertSessionHas('success');

        $this->assertSame(1, DB::table('idol_registration_requests')->count());
    }

    public function test_request_without_korean_name_uses_english_name_for_required_database_name(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('9', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Fallback Idol', 'source_language_code' => 'en',
                'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Fallback Idol'),
                'content' => 'Introduction', 'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame('Fallback Idol', $saved->name);
        $this->assertSame('Fallback Idol', $saved->english_name);
        $this->assertNull($saved->group_idx);
        $this->assertSame(0, DB::table('idol_registration_request_debuts')->count());
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => $saved->idx, 'language_code' => 'en', 'text_role' => 'submitted', 'description' => 'Introduction',
        ]);
        $this->assertSame(1, DB::table('idol_registration_request_localizations')->where('language_code', 'en')->where('text_role', 'submitted')->count());
    }

    public function test_group_request_saves_optional_genre_and_free_text_leader(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->insert([
            'idx' => 10, 'member_code' => 'AdminCode10', 'email' => 'admin@example.test', 'nickname' => 'admin',
            'member_level' => 10, 'is_dummy' => 0, 'status' => 'active',
        ]);
        Mail::fake();
        $key = str_repeat('b', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl_group', 'english_name' => 'Sample Group',
                'duplicate_search_token' => $this->duplicateSearchToken('girl_group', 'Sample Group'),
                'genre' => 'Pop / R&B', 'leader' => 'Leader entered as free text',
                'agency' => 'Sample Agency', 'source_language_code' => 'ko',
                'content' => 'Group introduction', 'profile_image' => UploadedFile::fake()->image('group.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame('group', $saved->type);
        $this->assertSame('Pop / R&B', $saved->genre);
        $this->assertSame('Leader entered as free text', $saved->leader);
        $this->assertSame(0, DB::table('idol_registration_request_debuts')->count());
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => $saved->idx, 'language_code' => 'ko', 'text_role' => 'submitted',
            'agency' => 'Sample Agency', 'genre' => 'Pop / R&B', 'leader' => 'Leader entered as free text',
        ]);
        Mail::assertSent(IdolRegistrationRequestNotification::class, fn (IdolRegistrationRequestNotification $mail): bool =>
            $mail->hasTo('admin@example.test')
            && $mail->requestType === 'group'
            && $mail->englishName === 'Sample Group'
        );
    }

    public function test_member_profile_fields_are_preserved_under_the_selected_source_language(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('d', 64);
        $agency = "株式会社テスト\n正式名称";
        $position = 'メインボーカル';

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Source Language Idol',
                'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Source Language Idol'),
                'agency' => $agency, 'position' => $position, 'source_language_code' => 'ja',
                'debut_dates' => [['country_code' => 'JP', 'debut_date' => '2020-02-03']],
                'content' => '紹介文', 'profile_image' => UploadedFile::fake()->image('source.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $requestIdx = (int) DB::table('idol_registration_requests')->value('idx');
        $this->assertDatabaseHas('idol_registration_request_debuts', [
            'request_idx' => $requestIdx, 'country_code' => 'JP', 'debut_date' => '2020-02-03',
        ]);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => $requestIdx, 'language_code' => 'ja', 'text_role' => 'submitted',
            'description' => '紹介文', 'agency' => $agency, 'position' => $position,
        ]);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => $requestIdx, 'language_code' => 'en', 'text_role' => 'submitted',
            'localized_name' => 'Source Language Idol', 'agency' => null, 'position' => null,
        ]);
    }

    public function test_request_form_keeps_name_fields_aligned_and_group_fields_group_only(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')->assertOk()
            ->assertSee('idol-request-name-field', false)
            ->assertSee('data-duplicate-search', false)
            ->assertSee('name="debut_dates[0][country_code]"', false)
            ->assertSee('name="debut_dates[0][debut_date]"', false)
            ->assertDontSee('name="nationality_code"', false)
            ->assertDontSee('name="nationality"', false)
            ->assertSee('data-group-only hidden', false)
            ->assertSee('name="genre"', false)
            ->assertSee('name="leader"', false)
            ->assertSee('name="source_language_code" required', false)
            ->assertDontSee('name="description_language_code"', false)
            ->assertViewHas('selectedGroup', null);
    }

    public function test_progressive_profile_fields_start_hidden_and_required_controls_are_disabled(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')->assertOk()
            ->assertSee('class="idol-request-type-options"', false)
            ->assertSee('name="english_name"', false)
            ->assertSee('data-duplicate-search', false)
            ->assertSee('data-registration-details hidden', false)
            ->assertSee('name="source_language_code" required disabled data-progressive-required', false)
            ->assertSee('name="profile_image" accept="image/jpeg,image/png" data-image-input required disabled data-progressive-required', false)
            ->assertSee('name="content" maxlength="1000" rows="6" disabled data-progressive-required', false);
    }

    public function test_individual_and_group_requests_allow_an_empty_introduction(): void
    {
        $this->database();
        Storage::fake('public');

        foreach (['boy' => 'Optional Intro Idol', 'girl_group' => 'Optional Intro Group'] as $type => $name) {
            $key = bin2hex(random_bytes(32));
            $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key]);
            $this->post('/idol-registration/request', [
                'request_key' => $key,
                'duplicate_search_token' => $this->duplicateSearchToken($type, $name),
                'request_type' => $type,
                'english_name' => $name,
                'source_language_code' => 'en',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

            $saved = DB::table('idol_registration_requests')->where('request_key', $key)->first();
            $this->assertNotNull($saved);
            $this->assertSame('', $saved->content);
            $this->assertSame('pending', $saved->status);
        }
    }

    public function test_form_field_order_and_source_language_default_to_current_supported_locale(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7, 'locale' => 'ko'])->get('/idol-registration/request')->assertOk()
            ->assertSeeInOrder([
                'class="idol-request-type-options"',
                'name="english_name"',
                'data-duplicate-search',
                'data-duplicate-panel',
                'name="source_language_code"',
                'name="name"',
                'id="idol-request-position"',
                'id="idol-request-agency"',
                'data-group-selects',
                'id="idol-request-group-name"',
                'id="idol-request-birth-date"',
                'id="idol-request-height"',
                'id="idol-request-weight"',
                'id="idol-request-blood"',
                'data-debut-date-editor',
                'data-social-link-list',
            ], false)
            ->assertSeeInOrder([
                'data-localized-name-field',
                'class="idol-request-group-details idol-request-field-full" data-group-only hidden',
                'id="idol-request-genre"',
                'id="idol-request-group-agency"',
                'id="idol-request-leader"',
                'data-debut-date-editor',
                'data-social-link-list',
            ], false)
            ->assertDontSee('name="group_type"', false)
            ->assertSee('data-duplicate-other-section', false)
            ->assertSee('value="ko" selected', false)
            ->assertDontSee('value="und"', false);

        $this->withSession(['member_idx' => 7, 'locale' => 'en'])->get('/idol-registration/request')->assertOk()
            ->assertSee('value="en" selected', false)
            ->assertSee('Idols or Groups Registered in Other Categories');

        $this->withSession(['member_idx' => 7, 'locale' => 'id'])->get('/idol-registration/request')->assertOk()
            ->assertSee('value="id" selected', false)
            ->assertSee('Nama dalam Bahasa Indonesia (opsional)');
    }

    public function test_optional_name_is_stored_in_the_selected_language(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('6', 64);
        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key,
                'request_type' => 'girl',
                'english_name' => 'Morning Star',
                'name' => 'Bintang Pagi',
                'source_language_code' => 'id',
                'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Morning Star'),
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'language_code' => 'id', 'localized_name' => 'Bintang Pagi', 'text_role' => 'submitted',
        ]);
        $this->assertSame(0, DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ko')->count());
    }

    public function test_duplicate_check_starts_with_compact_neutral_status(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7, 'locale' => 'ko'])->get('/idol-registration/request')->assertOk()
            ->assertSee('data-state="neutral"', false)
            ->assertSee('data-duplicate-panel data-state="neutral" aria-live="polite"', false)
            ->assertSee('영문 이름 중복 확인을 진행해 주세요.')
            ->assertDontSee('이미 등록된 아이돌은 승인되지 않습니다.');
    }

    public function test_duplicate_status_and_candidate_controls_share_one_unified_panel(): void
    {
        $this->database();
        $html = $this->withSession(['member_idx' => 7])->get('/idol-registration/request')->assertOk()->getContent();
        $panelStart = strpos($html, 'data-duplicate-panel');
        $panelEnd = strpos($html, '</section>', $panelStart);

        $this->assertNotFalse($panelStart);
        $this->assertNotFalse($panelEnd);
        foreach (['data-duplicate-status', 'data-duplicate-status-description', 'data-duplicate-candidates', 'data-duplicate-other-section', 'data-duplicate-confirm'] as $marker) {
            $markerPosition = strpos($html, $marker, $panelStart);
            $this->assertNotFalse($markerPosition);
            $this->assertLessThan($panelEnd, $markerPosition);
        }
    }

    public function test_duplicate_status_messages_are_translated_in_korean_and_english(): void
    {
        $messages = [
            'ko' => [
                'idol_duplicate_status_initial' => '영문 이름 중복 확인을 진행해 주세요.',
                'idol_duplicate_status_checking' => '이름을 확인하고 있습니다…',
                'idol_duplicate_status_available' => '등록 신청이 가능합니다.',
                'idol_duplicate_status_exact' => '등록 신청이 불가능합니다.',
                'idol_duplicate_status_similar' => '유사한 이름이 있습니다. 확인이 필요합니다.',
                'idol_duplicate_status_error' => '중복 확인에 실패했습니다. 다시 시도해 주세요.',
                'idol_duplicate_status_available_description' => '선택한 분류에서 동일하거나 유사한 이름을 찾지 못했습니다.',
                'idol_duplicate_status_exact_heading' => '이미 등록된 아이돌은 승인되지 않습니다.',
                'idol_duplicate_status_exact_description' => '동일한 아이돌/그룹이 이미 등록되어 있어 신청할 수 없습니다.',
                'idol_duplicate_status_similar_heading' => '유사한 이름이 있습니다. 확인이 필요합니다.',
                'idol_duplicate_status_similar_description' => '후보를 확인하고 신청 대상과 다른 아이돌/그룹인지 확인해 주세요.',
            ],
            'en' => [
                'idol_duplicate_status_initial' => 'Please check whether the English name is already registered.',
                'idol_duplicate_status_checking' => 'Checking the name…',
                'idol_duplicate_status_available' => 'This registration request can be submitted.',
                'idol_duplicate_status_exact' => 'This registration request cannot be submitted.',
                'idol_duplicate_status_similar' => 'A similar name exists. Please review it.',
                'idol_duplicate_status_error' => 'Unable to check the name. Please try again.',
                'idol_duplicate_status_available_description' => 'No identical or similar names were found in the selected category.',
                'idol_duplicate_status_exact_heading' => 'Already registered idols or groups cannot be approved.',
                'idol_duplicate_status_exact_description' => 'The same idol or group is already registered in this category, so this request cannot be submitted.',
                'idol_duplicate_status_similar_heading' => 'A similar name exists. Please review it.',
                'idol_duplicate_status_similar_description' => 'Review the candidates and confirm that they are different from the idol or group you are registering.',
            ],
        ];

        foreach ($messages as $locale => $expectedMessages) {
            foreach ($expectedMessages as $key => $expected) {
                $this->assertSame($expected, Lang::get('js.' . $key, [], $locale));
                $this->assertNotSame($key, Lang::get('js.' . $key, [], $locale));
            }
        }
    }

    public function test_duplicate_search_with_no_matches_returns_no_candidate_groups(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy', 'english_name' => 'No Such Idol Name',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)
            ->assertJsonCount(0, 'candidates')->assertJsonCount(0, 'other_candidates');
    }

    public function test_submission_requires_a_current_duplicate_check_even_when_there_are_no_matches(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->insert([
            'idx' => 10, 'member_code' => 'AdminCode10', 'email' => 'admin@example.test', 'nickname' => 'admin',
            'member_level' => 10, 'is_dummy' => 0, 'status' => 'active',
        ]);
        Mail::fake();
        $key = str_repeat('c', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Fresh Check Required',
                'source_language_code' => 'en', 'content' => 'Introduction',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
        Mail::assertNothingOutgoing();
    }

    public function test_debut_date_rows_require_country_and_date_and_reject_duplicate_countries(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('f', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Debut Dates Invalid',
                'content' => 'Introduction', 'source_language_code' => 'en',
                'debut_dates' => [['country_code' => 'KR', 'debut_date' => ''], ['country_code' => 'KR', 'debut_date' => '2020-01-01']],
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertSessionHasErrors(['debut_dates.0.debut_date', 'debut_dates.1.country_code']);

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_group_fields_are_validated_with_localized_length_error(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('c', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy_group', 'english_name' => 'Long Genre Group',
                'genre' => str_repeat('x', 201), 'content' => 'Group introduction', 'source_language_code' => 'en',
                'profile_image' => UploadedFile::fake()->image('group.png'),
            ])->assertSessionHasErrors('genre');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_registration_stores_up_to_ten_social_links(): void
    {
        $this->database();
        Storage::fake('public');
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertSee('data-max-links="10"', false)
            ->assertSee('data-duplicate-message=', false);
        $links = array_map(static fn (int $number): string => 'https://example.com/social/'.$number, range(1, 10));

        foreach ([0, 1, 3, 10] as $count) {
            $key = str_pad(dechex($count + 1), 64, '0', STR_PAD_LEFT);
            $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
                ->post('/idol-registration/request', [
                    'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Link Test '.$count, 'source_language_code' => 'en',
                    'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Link Test '.$count),
                    'sns_links' => array_slice($links, 0, $count) ?: [''],
                    'content' => 'Introduction', 'profile_image' => UploadedFile::fake()->image('profile.png'),
                ])->assertRedirect(route('idol-registration.create'));

            $saved = DB::table('idol_registration_requests')->where('request_key', $key)->first();
            $this->assertSame(array_slice($links, 0, $count), json_decode($saved->sns_links, true));
        }
    }

    public function test_registration_rejects_more_than_ten_social_links_invalid_urls_and_duplicates(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('8', 64);
        $base = [
            'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Social Validation Test', 'source_language_code' => 'en',
            'content' => 'Introduction', 'profile_image' => UploadedFile::fake()->image('profile.png'),
        ];

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', $base + ['sns_links' => array_map(
                static fn (int $number): string => 'https://example.com/social/'.$number,
                range(1, 11)
            )])
            ->assertSessionHasErrors('sns_links');

        foreach ([['javascript:alert(1)'], ['ftp://example.test/profile'], ['https://instagram.com/a', 'https://INSTAGRAM.com/a']] as $badLinks) {
            $key = str_pad(dechex(random_int(10, 100000)), 64, 'a', STR_PAD_LEFT);
            $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
                ->post('/idol-registration/request', array_merge($base, [
                    'request_key' => $key, 'english_name' => 'Invalid Social Test '.$key,
                    'sns_links' => $badLinks,
                ]))->assertSessionHasErrors($badLinks[0] === 'javascript:alert(1)' || str_starts_with($badLinks[0], 'ftp:') ? 'sns_links.0' : 'sns_links');
        }

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_disabled_or_zero_approval_reward_is_not_advertised(): void
    {
        $this->database();

        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')->update(['is_enabled' => 0]);
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertDontSee('Approval Reward:');

        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')->update(['is_enabled' => 1, 'heart_amount' => 0]);
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')
            ->assertOk()->assertDontSee('Approval Reward:');
    }

    public function test_server_rejects_wrong_group_gender(): void
    {
        $this->database();
        Storage::fake('public');
        $response = $this->from('/idol-registration/request')->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('b', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('b', 64),
            'request_type' => 'boy', 'name' => 'Demo', 'english_name' => 'Test Idol',
            'group_idx' => 5, 'content' => 'Introduction', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ]);
        $response->assertRedirect('/idol-registration/request')->assertSessionHasErrors('group_idx');
        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_uncropped_non_square_original_image_is_accepted(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('c', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('c', 64),
            'request_type' => 'boy', 'name' => 'Demo', 'english_name' => 'Demo Idol', 'source_language_code' => 'en',
            'duplicate_search_token' => $this->duplicateSearchToken('boy', 'Demo Idol'),
            'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('original.png', 900, 600),
        ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        Storage::disk('public')->assertExists(substr($saved->image_path, 8));
    }

    public function test_group_request_clears_individual_only_values_and_saves_country(): void
    {
        $this->database();
        Storage::fake('public');

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => str_repeat('d', 64)])->post('/idol-registration/request', [
            'request_key' => str_repeat('d', 64),
            'request_type' => 'girl_group', 'name' => 'Demo', 'english_name' => 'Demo Group Request',
            'duplicate_search_token' => $this->duplicateSearchToken('girl_group', 'Demo Group Request'),
            'group_idx' => 5, 'birth_date' => '2000-01-02',
            'height_cm' => '168.5', 'weight_kg' => '50.5', 'blood_type' => 'A',
            'agency' => '테스트 기획사', 'debut_dates' => [['country_code' => 'KR', 'debut_date' => '2020-02-03']], 'position' => '보컬',
            'sns' => '@demogroup', 'content' => 'A concise introduction.', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png', 600, 600),
        ])->assertRedirect(route('idol-registration.create'));

        $saved = DB::table('idol_registration_requests')->first();
        $this->assertSame('group', $saved->type);
        $this->assertDatabaseHas('idol_registration_request_debuts', ['request_idx' => $saved->idx, 'country_code' => 'KR', 'debut_date' => '2020-02-03']);
        foreach (['group_idx', 'birth_date', 'height_cm', 'weight_kg', 'blood_type', 'position'] as $field) {
            $this->assertNull($saved->{$field});
        }
    }

    public function test_repeated_request_key_does_not_create_a_duplicate_request(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('members')->insert([
            'idx' => 10, 'member_code' => 'AdminCode10', 'email' => 'admin@example.test', 'nickname' => 'admin',
            'member_level' => 10, 'is_dummy' => 0, 'status' => 'active',
        ]);
        Mail::fake();
        $key = str_repeat('e', 64);
        $payload = [
            'request_key' => $key,
            'request_type' => 'boy',
            'name' => 'Demo',
            'english_name' => 'Demo Idol',
            'content' => 'Introduction', 'source_language_code' => 'en',
        ];

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key]);
        $payload['duplicate_search_token'] = $this->duplicateSearchToken('boy', 'Demo Idol');
        $this->post('/idol-registration/request', $payload + [
                'profile_image' => UploadedFile::fake()->image('first.png', 600, 600),
            ])->assertRedirect(route('idol-registration.create'));
        $this->withSession(['member_idx' => 7])
            ->post('/idol-registration/request', $payload + [
                'profile_image' => UploadedFile::fake()->image('second.png', 600, 600),
            ])->assertRedirect(route('idol-registration.create'))->assertSessionHas('success');

        $this->assertSame(1, DB::table('idol_registration_requests')->count());
        $this->assertSame(1, DB::table('idol_registration_request_localizations')->where('text_role', 'submitted')->count());
        Mail::assertSent(IdolRegistrationRequestNotification::class, 1);
    }

    public function test_submitted_localizations_preserve_exact_english_and_japanese_text(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('4', 64);
        $english = '  Luna Star  ';
        $japanese = 'ルナ・スター';
        $description = "  First line, punctuation!\nSecond line ??original.  ";

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => $english, 'name' => $japanese,
                'duplicate_search_token' => $this->duplicateSearchToken('girl', $english),
                'duplicate_search_token' => $this->duplicateSearchToken('girl', $english),
                'content' => $description, 'source_language_code' => 'ja',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $rows = DB::table('idol_registration_request_localizations')->where('text_role', 'submitted')->get()->keyBy('language_code');
        $this->assertCount(2, $rows);
        $this->assertSame('Luna Star', $rows['en']->localized_name);
        $this->assertNull($rows['en']->description);
        $this->assertSame($japanese, $rows['ja']->localized_name);
        $this->assertSame($description, $rows['ja']->description);
        $this->assertSame('Luna Star', DB::table('idol_registration_requests')->value('english_name'));
        $this->assertSame(trim($description), DB::table('idol_registration_requests')->value('content'));
    }

    public function test_simplified_and_traditional_chinese_descriptions_keep_the_selected_language_and_exact_text(): void
    {
        $this->database();
        Storage::fake('public');

        foreach ([
            ['code' => 'zh-CN', 'english' => 'Simplified Sample', 'text' => " 简体中文介绍！\n保留原文。 "],
            ['code' => 'zh-TW', 'english' => 'Traditional Sample', 'text' => " 繁體中文介紹！\n保留原文。 "],
        ] as $index => $case) {
            $key = str_pad(dechex($index + 10), 64, '0', STR_PAD_LEFT);
            $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
                ->post('/idol-registration/request', [
                    'request_key' => $key, 'request_type' => 'girl', 'english_name' => $case['english'],
                    'duplicate_search_token' => $this->duplicateSearchToken('girl', $case['english']),
                    'duplicate_search_token' => $this->duplicateSearchToken('girl', $case['english']),
                    'content' => $case['text'], 'source_language_code' => $case['code'],
                    'profile_image' => UploadedFile::fake()->image('chinese-'.$index.'.png'),
                ])->assertRedirect(route('idol-registration.create'));
        }

        $this->assertSame(" 简体中文介绍！\n保留原文。 ", DB::table('idol_registration_request_localizations')
            ->where('language_code', 'zh-CN')->value('description'));
        $this->assertSame(" 繁體中文介紹！\n保留原文。 ", DB::table('idol_registration_request_localizations')
            ->where('language_code', 'zh-TW')->value('description'));
        $this->assertSame(0, DB::table('idol_registration_request_localizations')
            ->whereIn('language_code', ['zh-CN', 'zh-TW'])->whereNotIn('text_role', ['submitted'])->count());
    }

    public function test_und_is_not_accepted_for_new_submission_language(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('5', 64);
        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy', 'english_name' => 'Unknown Language Idol',
                'content' => 'Unclassified text', 'source_language_code' => 'und',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertSessionHasErrors('source_language_code');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_name_and_description_in_the_same_language_are_merged(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('6', 64);
        $description = "Same-language introduction\nwith exact punctuation.";

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Merged Idol',
                'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Merged Idol'),
                'duplicate_search_token' => $this->duplicateSearchToken('girl', 'Merged Idol'),
                'content' => $description, 'source_language_code' => 'en',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $rows = DB::table('idol_registration_request_localizations')->where('text_role', 'submitted')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('en', $rows[0]->language_code);
        $this->assertSame('Merged Idol', $rows[0]->localized_name);
        $this->assertSame($description, $rows[0]->description);
    }

    public function test_invalid_source_language_is_rejected(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('7', 64);

        $this->from('/idol-registration/request')
            ->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy', 'english_name' => 'Invalid Language Idol',
                'content' => 'Introduction', 'source_language_code' => 'xx',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertSessionHasErrors('source_language_code');

        $requiredKey = str_repeat('6', 64);
        $this->from('/idol-registration/request')
            ->withSession(['member_idx' => 7, 'idol_registration_request_key' => $requiredKey])
            ->post('/idol-registration/request', [
                'request_key' => $requiredKey, 'request_type' => 'boy', 'english_name' => 'Missing Source Language',
                'content' => 'Introduction', 'profile_image' => UploadedFile::fake()->image('missing-language.png'),
            ])->assertSessionHasErrors('source_language_code');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_selected_source_language_is_restored_after_validation_error(): void
    {
        $this->database();
        $key = str_repeat('9', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy', 'english_name' => 'Language Restore Idol',
                'content' => 'Introduction', 'source_language_code' => 'zh-TW',
            ])->assertSessionHasErrors('profile_image');

        $this->get('/idol-registration/request')->assertOk()->assertSee('value="zh-TW" selected', false);
    }

    public function test_localization_insert_failure_rolls_back_request_and_image_storage(): void
    {
        $this->database();
        Storage::fake('public');
        DB::statement("CREATE TRIGGER fail_submitted_localization BEFORE INSERT ON idol_registration_request_localizations BEGIN SELECT RAISE(ABORT, 'localization insert failed'); END");
        $key = str_repeat('8', 64);

        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy', 'english_name' => 'Rollback Idol',
                'duplicate_search_token' => $this->duplicateSearchToken('boy', 'Rollback Idol'),
                'duplicate_search_token' => $this->duplicateSearchToken('boy', 'Rollback Idol'),
                'source_language_code' => 'en',
                'content' => 'Introduction', 'profile_image' => UploadedFile::fake()->image('rollback.png'),
            ])->assertStatus(500);

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
        $this->assertSame(0, DB::table('idol_registration_request_localizations')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_duplicate_search_separates_selected_and_other_categories(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Demo Star', 'english_name' => 'Demo Star', 'type' => 'individual',
            'gender' => 'female', 'status' => 'active', 'image_path' => null,
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'demo',
        ]))->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('candidates.0.name', 'Demo Star')
            ->assertJsonPath('candidates.0.category', 'Girl Idol')
            ->assertJsonPath('candidates.0.image_url', asset('images/no-image.png'))
            ->assertJsonPath('candidates.0.url', route('idol.show', ['idx' => 6]));

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy', 'english_name' => 'demo',
        ]))->assertJsonPath('exact_duplicate', false)->assertJsonCount(0, 'candidates')
            ->assertJsonCount(2, 'other_candidates')
            ->assertJsonPath('other_candidates.0.category', 'Girl Group');
    }

    public function test_other_category_exact_match_is_informational_and_does_not_block_submission(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'ENHYPEN', 'english_name' => 'ENHYPEN', 'type' => 'group',
            'gender' => 'male', 'status' => 'active', 'image_path' => null,
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy', 'english_name' => 'ENHYPEN',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(0, 'candidates')
            ->assertJsonCount(1, 'other_candidates')
            ->assertJsonPath('other_candidates.0.category', 'Boy Group')
            ->assertJsonPath('other_candidates.0.exact', true)
            ->assertJsonPath('other_candidates.0.image_url', asset('images/no-image.png'));

        $key = str_repeat('e', 64);
        $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
            ->post('/idol-registration/request', [
                'request_key' => $key, 'request_type' => 'boy', 'english_name' => 'ENHYPEN',
                'duplicate_search_token' => $this->duplicateSearchToken('boy', 'ENHYPEN'),
                'source_language_code' => 'en', 'content' => 'New individual idol introduction.',
                'profile_image' => UploadedFile::fake()->image('profile.png'),
            ])->assertRedirect(route('idol-registration.create'));

        $this->assertSame(1, DB::table('idol_registration_requests')->count());
    }

    public function test_duplicate_search_matches_legacy_english_and_localized_names_exactly(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Old Legacy Name', 'english_name' => '  Blue   Orbit ', 'type' => 'individual',
            'gender' => 'female', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            'idol_idx' => 6, 'language_code' => 'ja', 'localized_name' => '  Blue Orbit Name  ',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'blue orbit',
        ]))->assertOk()->assertJsonPath('exact_duplicate', true)->assertJsonCount(1, 'candidates');

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'blueorbitname',
        ]))->assertOk()->assertJsonPath('exact_duplicate', true)->assertJsonCount(1, 'candidates');
    }

    public function test_duplicate_search_matches_any_language_alias_once_and_marks_partial_prefixes(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Comet', 'english_name' => 'Comet', 'type' => 'group',
            'gender' => 'female', 'status' => 'active',
        ]);
        DB::table('idols')->insert([
            'idx' => 7, 'name' => 'Comet Boys', 'english_name' => 'Comet Boys', 'type' => 'group',
            'gender' => 'male', 'status' => 'active',
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 6, 'language_code' => 'ja', 'alias_name' => 'Shooting Star', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 6, 'language_code' => 'ko', 'alias_name' => 'Shooting Star KR', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 6, 'language_code' => 'en', 'alias_name' => 'Comet', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 6, 'language_code' => 'en', 'alias_name' => 'Comet Stars', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 7, 'language_code' => 'ja', 'alias_name' => 'Shooting Star', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 7, 'language_code' => 'ko', 'alias_name' => 'Shooting Star', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Shooting Star',
        ]))->assertOk()->assertJsonPath('exact_duplicate', true)->assertJsonCount(1, 'candidates');
        $this->assertSame(1, count($this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Shooting Star',
        ]))->json('other_candidates')));

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Comet',
        ]))->assertOk()->assertJsonPath('exact_duplicate', true)->assertJsonCount(1, 'candidates');

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Shooting',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.exact', false);
    }

    public function test_duplicate_search_excludes_inactive_entities_and_other_categories_for_aliases(): void
    {
        $this->database();
        DB::table('idols')->insert([
            ['idx' => 6, 'name' => 'Inactive', 'english_name' => 'Inactive', 'type' => 'individual', 'gender' => 'female', 'status' => 'inactive'],
            ['idx' => 7, 'name' => 'Other Category', 'english_name' => 'Other Category', 'type' => 'group', 'gender' => 'male', 'status' => 'active'],
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 6, 'language_code' => 'ja', 'alias_name' => 'Hidden Alias', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 6, 'language_code' => 'ja', 'alias_name' => 'Hidden Alias', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy', 'english_name' => 'Hidden Alias',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(0, 'candidates')
            ->assertJsonCount(0, 'other_candidates');
    }

    public function test_submission_rejects_exact_multilingual_alias_and_requires_confirmation_for_similar_alias(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Existing Name', 'english_name' => 'Existing Name', 'type' => 'group',
            'gender' => 'female', 'status' => 'active',
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 6, 'language_code' => 'ja', 'alias_name' => 'Shooting Star', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $key = str_repeat('a', 64);

        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'girl_group', 'english_name' => 'Shooting Star',
            'content' => 'Introduction', 'source_language_code' => 'en', 'profile_image' => UploadedFile::fake()->image('profile.png'),
        ])->assertSessionHasErrors('english_name');
        $this->assertSame(0, DB::table('idol_registration_requests')->count());

        $key = str_repeat('b', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Shooting',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(1, 'candidates');
        $payload = [
            'request_key' => $key, 'request_type' => 'girl_group', 'english_name' => 'Shooting',
            'content' => 'Introduction', 'source_language_code' => 'en', 'profile_image' => UploadedFile::fake()->image('similar.png'),
            'duplicate_search_token' => $search->json('token'),
        ];
        $this->from('/idol-registration/request')->post('/idol-registration/request', $payload)
            ->assertSessionHasErrors('not_duplicate_confirmed');
        $this->post('/idol-registration/request', $payload + ['not_duplicate_confirmed' => '1'])
            ->assertRedirect(route('idol-registration.create'));
        $this->assertSame(1, DB::table('idol_registration_requests')->count());
    }

    public function test_duplicate_matching_preserves_diacritics_and_avoids_interior_substring_matches(): void
    {
        $this->database();
        $this->assertSame(
            \App\Support\IdolDuplicateMatcher::normalize("Cafe\u{0301}"),
            \App\Support\IdolDuplicateMatcher::normalize('Café')
        );
        $this->assertNotSame(
            \App\Support\IdolDuplicateMatcher::normalize('Café'),
            \App\Support\IdolDuplicateMatcher::normalize('Cafe')
        );
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Café Luna', 'english_name' => 'Café Luna', 'type' => 'individual',
            'gender' => 'female', 'status' => 'active',
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'Cafe',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(0, 'candidates');

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'afé',
        ]))->assertUnprocessable()->assertJsonValidationErrors('english_name');

        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'Lun',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)->assertJsonCount(1, 'candidates')
            ->assertJsonPath('candidates.0.exact', false);
    }

    public function test_duplicate_search_requires_a_member_session(): void
    {
        $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl', 'english_name' => 'demo',
        ]))->assertUnauthorized();
    }

    public function test_group_search_is_partial_gender_filtered_and_limited_to_ten(): void
    {
        $this->database();
        foreach (range(1, 12) as $number) {
            DB::table('idols')->insert([
                'idx' => 10 + $number, 'name' => '한빛 그룹 '.$number, 'english_name' => 'Hanbit Group '.$number,
                'type' => 'group', 'gender' => 'female', 'status' => 'active',
            ]);
        }
        DB::table('idols')->insert([
            ['idx' => 40, 'name' => 'Hanbit Boy Group', 'english_name' => 'Hanbit Boy Group', 'type' => 'group', 'gender' => 'male', 'status' => 'active'],
            ['idx' => 41, 'name' => 'Hanbit Hidden Group', 'english_name' => 'Hanbit Hidden Group', 'type' => 'group', 'gender' => 'female', 'status' => 'inactive'],
            ['idx' => 42, 'name' => 'Hanbit Individual', 'english_name' => 'Hanbit Individual', 'type' => 'individual', 'gender' => 'female', 'status' => 'active'],
        ]);

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.groups', [
            'request_type' => 'girl', 'q' => 'hAnBiT',
        ]))->assertOk()->assertJsonPath('success', true)->assertJsonCount(10, 'groups')
            ->assertJsonPath('groups.0.english_name', 'Hanbit Group 1')
            ->assertJsonPath('groups.0.name', '한빛 그룹 1')
            ->assertJsonMissing(['idx' => 40])->assertJsonMissing(['idx' => 41])->assertJsonMissing(['idx' => 42]);

        $this->getJson(route('idol-registration.groups', ['request_type' => 'girl', 'q' => '한빛']))
            ->assertOk()->assertJsonCount(10, 'groups');
    }

    public function test_group_search_rejects_group_categories_and_lists_active_groups_without_a_term(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.groups', [
            'request_type' => 'girl_group', 'q' => 'demo',
        ]))->assertUnprocessable()->assertJsonValidationErrors('request_type');

        $this->getJson(route('idol-registration.groups', ['request_type' => 'girl']))
            ->assertOk()->assertJsonCount(1, 'groups')->assertJsonPath('groups.0.idx', 5);
    }

    public function test_inactive_or_non_group_selection_is_rejected(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->where('idx', 5)->update(['status' => 'inactive']);
        $key = str_repeat('4', 64);

        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Selected Hidden Group Test',
            'group_idx' => 5, 'content' => 'Introduction', 'source_language_code' => 'en', 'profile_image' => UploadedFile::fake()->image('group.png'),
        ])->assertSessionHasErrors('group_idx');
        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_selected_group_gender_must_match_the_individual_registration_type(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('5', 64);
        $image = UploadedFile::fake()->createWithContent('profile.png', file_get_contents(base_path('tests/Fixtures/quiz-image.png')));

        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'boy', 'group_idx' => 5,
            'english_name' => 'Mismatched Group Type Test', 'content' => 'Introduction',
            'source_language_code' => 'en', 'profile_image' => $image,
        ])->assertSessionHasErrors('group_idx');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_selected_group_is_restored_after_validation_errors(): void
    {
        $this->database();
        Storage::fake('public');
        $this->withSession(['member_idx' => 7])->get('/idol-registration/request')->assertOk();
        $key = (string) $this->app['session.store']->get('idol_registration_request_key');

        $this->from('/idol-registration/request')->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'girl', 'english_name' => 'Selected Group Restore Test',
            'group_idx' => 5, 'source_language_code' => 'en', 'content' => str_repeat('x', 1001),
            'profile_image' => UploadedFile::fake()->image('group.png'),
        ])->assertSessionHasErrors('content');

        $this->get('/idol-registration/request')->assertOk()
            ->assertSee('<option value="5" selected>Demo Group</option>', false)
            ->assertDontSee('name="group_type"', false)
            ->assertSee('name="group_idx"', false);
    }

    public function test_submission_rejects_an_exact_active_duplicate(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('f', 64);

        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'girl_group', 'name' => 'Another Name',
            'english_name' => 'demo group', 'content' => 'Introduction', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png'),
        ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_similar_matches_require_explicit_confirmation_after_a_current_search(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Demo Stars', 'english_name' => 'Demo Stars', 'type' => 'group',
            'gender' => 'female', 'status' => 'active', 'image_path' => null,
        ]);
        $key = str_repeat('1', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Demo',
        ]))->assertOk()->assertJsonCount(2, 'candidates');
        $token = $search->json('token');
        $payload = [
            'request_key' => $key, 'request_type' => 'girl_group', 'name' => 'Other Group',
            'english_name' => 'Demo', 'content' => 'Introduction', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png'), 'duplicate_search_token' => $token,
        ];

        $this->from('/idol-registration/request')->post('/idol-registration/request', $payload)
            ->assertSessionHasErrors('not_duplicate_confirmed');
        $this->post('/idol-registration/request', $payload + ['not_duplicate_confirmed' => '1'])
            ->assertRedirect(route('idol-registration.create'));

        $this->assertSame(1, DB::table('idol_registration_requests')->count());
        $this->assertSame(1, DB::table('idol_registration_request_localizations')->where('text_role', 'submitted')->count());
    }

    public function test_changing_english_name_invalidates_a_previous_search_confirmation(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'Demo Stars', 'english_name' => 'Demo Stars', 'type' => 'group',
            'gender' => 'female', 'status' => 'active', 'image_path' => null,
        ]);
        DB::table('idols')->insert([
            'idx' => 7, 'name' => 'Other Idol', 'english_name' => 'Other Idol', 'type' => 'group',
            'gender' => 'female', 'status' => 'active', 'image_path' => null,
        ]);
        $key = str_repeat('2', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Demo',
        ]))->assertOk();
        $token = $search->json('token');

        $this->from('/idol-registration/request')->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'girl_group', 'name' => 'Other Group',
            'english_name' => 'Other', 'content' => 'Introduction', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png'),
            'duplicate_search_token' => $token, 'not_duplicate_confirmed' => '1',
        ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_changing_category_invalidates_a_previous_search_confirmation(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'New Boy Group', 'english_name' => 'New Boy Group', 'type' => 'group',
            'gender' => 'male', 'status' => 'active', 'image_path' => null,
        ]);
        $key = str_repeat('3', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'Demo',
        ]))->assertOk();

        $this->from('/idol-registration/request')->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'boy_group', 'name' => 'Other Group',
            'english_name' => 'New', 'content' => 'Introduction', 'source_language_code' => 'en',
            'profile_image' => UploadedFile::fake()->image('profile.png'),
            'duplicate_search_token' => $search->json('token'), 'not_duplicate_confirmed' => '1',
        ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_duplicate_search_ignores_case_and_all_unicode_whitespace_for_exact_names(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'ENHYPEN', 'english_name' => 'ENHYPEN', 'type' => 'group',
            'gender' => 'male', 'status' => 'active',
        ]);

        foreach (["ENHY PEN", "EN HYPEN", "enhyPen", "EN\u{3000}HYPEN"] as $name) {
            $this->withSession(['member_idx' => 7])->getJson(route('idol-registration.duplicates', [
                'request_type' => 'boy_group', 'english_name' => $name,
            ]))->assertOk()->assertJsonPath('exact_duplicate', true)
                ->assertJsonPath('candidates.0.exact', true);
        }
    }

    public function test_punctuation_insensitive_match_is_similar_not_blocking_exact(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => '(G)I-DLE', 'english_name' => '(G)I-DLE', 'type' => 'group',
            'gender' => 'female', 'status' => 'active',
        ]);

        $key = str_repeat('7', 64);
        $search = $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'girl_group', 'english_name' => 'GIDLE',
        ]))->assertOk()->assertJsonPath('exact_duplicate', false)
            ->assertJsonPath('candidates.0.exact', false);

        $payload = [
            'request_key' => $key, 'request_type' => 'girl_group', 'english_name' => 'GIDLE',
            'source_language_code' => 'en', 'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('similar.png'),
            'duplicate_search_token' => $search->json('token'),
        ];
        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', $payload)->assertSessionHasErrors('not_duplicate_confirmed');
        $this->post('/idol-registration/request', $payload + ['not_duplicate_confirmed' => '1'])
            ->assertRedirect(route('idol-registration.create'));
    }

    public function test_supported_english_group_names_submit_and_keep_only_outer_whitespace_trimmed(): void
    {
        $this->database();
        Storage::fake('public');

        foreach (['NCT 127', 'LE SSERAFIM', '(G)I-DLE', 'TOMORROW X TOGETHER'] as $index => $name) {
            $key = str_repeat((string) ($index + 1), 64);
            $search = $this->withSession([
                'member_idx' => 7, 'idol_registration_request_key' => $key,
            ])->getJson(route('idol-registration.duplicates', [
                'request_type' => 'boy_group', 'english_name' => '  '.$name."\u{3000}",
            ]))->assertOk()->assertJsonPath('exact_duplicate', false);

            $this->withSession(['member_idx' => 7, 'idol_registration_request_key' => $key])
                ->post('/idol-registration/request', [
                    'request_key' => $key, 'request_type' => 'boy_group', 'english_name' => '  '.$name."\u{3000}",
                    'source_language_code' => 'en', 'content' => 'Introduction',
                    'profile_image' => UploadedFile::fake()->image('profile-'.$index.'.png'),
                    'duplicate_search_token' => $search->json('token'),
                ])->assertRedirect(route('idol-registration.create'));

            $this->assertSame($name, DB::table('idol_registration_requests')->where('request_key', $key)->value('english_name'));
        }
    }

    public function test_duplicate_search_token_is_invalidated_when_internal_spacing_changes(): void
    {
        $this->database();
        Storage::fake('public');
        $key = str_repeat('6', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy_group', 'english_name' => 'TOMORROW X TOGETHER',
        ]))->assertOk();

        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', [
            'request_key' => $key, 'request_type' => 'boy_group', 'english_name' => 'TOMORROW  X TOGETHER',
            'source_language_code' => 'en', 'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('token.png'),
            'duplicate_search_token' => $search->json('token'),
        ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idol_registration_requests')->count());
    }

    public function test_submission_rejects_unsupported_english_characters_and_rechecks_exact_duplicates(): void
    {
        $this->database();
        Storage::fake('public');
        DB::table('idols')->insert([
            'idx' => 6, 'name' => 'ENHYPEN', 'english_name' => 'ENHYPEN', 'type' => 'group',
            'gender' => 'male', 'status' => 'active',
        ]);
        $key = str_repeat('9', 64);
        $search = $this->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy_group', 'english_name' => 'ENHY PEN',
        ]))->assertOk()->assertJsonPath('exact_duplicate', true);

        $payload = [
            'request_key' => $key, 'request_type' => 'boy_group', 'english_name' => 'ENHY PEN',
            'source_language_code' => 'en', 'content' => 'Introduction',
            'profile_image' => UploadedFile::fake()->image('duplicate.png'),
            'duplicate_search_token' => $search->json('token'),
        ];
        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', $payload)->assertSessionHasErrors('english_name');

        $key = str_repeat('8', 64);
        $this->from('/idol-registration/request')->withSession([
            'member_idx' => 7, 'idol_registration_request_key' => $key,
        ])->post('/idol-registration/request', array_merge($payload, [
            'request_key' => $key, 'english_name' => 'New Idol!',
        ]))
            ->assertSessionHasErrors('english_name');
        $this->assertSame(0, DB::table('idol_registration_requests')->count());
        $this->assertStringContainsString('영문자', Lang::get('ui.idol_english_name_invalid', [], 'ko'));
        $this->assertStringContainsString('English letters', Lang::get('ui.idol_english_name_invalid', [], 'en'));
    }
}
