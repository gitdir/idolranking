<?php

namespace Tests\Feature;

use App\Support\IdolLocalizationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminIdolRegistrationRequestTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, language_code TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
        DB::statement("CREATE TABLE heart_reward_settings (action_code TEXT PRIMARY KEY, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE fan_post_reports (idx INTEGER PRIMARY KEY, reason TEXT, status TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comment_reports (idx INTEGER PRIMARY KEY, status TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, genre TEXT, leader TEXT, sns TEXT, sns_links TEXT, image_path TEXT, content TEXT, status TEXT, view_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(idol_idx, country_code))");
        DB::statement("CREATE TABLE idol_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(idol_idx, language_code))");
        DB::statement("CREATE TABLE idol_name_aliases (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER NOT NULL, language_code TEXT NOT NULL, alias_name TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, created_at TEXT, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE admin_dummy_activity_requests (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, member_idx INTEGER, action_code TEXT, request_key TEXT, payload_hash TEXT, result_type TEXT, result_idx INTEGER, created_at TEXT, completed_at TEXT, updated_at TEXT, UNIQUE(actor_member_idx, action_code, request_key))");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY, member_idx INTEGER, actor_member_idx INTEGER, type TEXT, gender TEXT, name TEXT, english_name TEXT, group_idx INTEGER, birth_date TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, sns TEXT, sns_links TEXT, image_path TEXT, content TEXT, reference_url TEXT, note TEXT, request_key TEXT, status TEXT, approved_idol_idx INTEGER, approved_fan_post_idx INTEGER, reviewed_by_member_idx INTEGER, reviewed_at TEXT, review_note TEXT, rejection_reason TEXT, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_registration_request_debuts (idx INTEGER PRIMARY KEY AUTOINCREMENT, request_idx INTEGER NOT NULL, country_code TEXT NOT NULL, debut_date TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(request_idx, country_code))");
        DB::statement("CREATE TABLE idol_registration_request_localizations (idx INTEGER PRIMARY KEY AUTOINCREMENT, request_idx INTEGER NOT NULL, language_code TEXT NOT NULL, text_role TEXT NOT NULL, localized_name TEXT, description TEXT, agency TEXT, position TEXT, genre TEXT, leader TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(request_idx, language_code, text_role))");
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'adminCodeA01', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'country_code' => 'KR', 'heart_balance' => 100, 'member_level' => 10, 'status' => 'active', 'created_at' => now()],
            ['idx' => 2, 'member_code' => 'memberCode01', 'email' => 'purple@example.test', 'nickname' => 'purple_universe', 'country_code' => 'US', 'heart_balance' => 50, 'member_level' => 1, 'status' => 'active', 'created_at' => now()],
        ]);
        DB::table('idol_registration_requests')->insert(['idx' => 12, 'member_idx' => 2, 'actor_member_idx' => 2, 'type' => 'individual', 'gender' => 'female', 'name' => '하나', 'english_name' => 'HANA', 'image_path' => 'images/placeholders/portrait-rose.svg', 'content' => '소개', 'request_key' => 'request-12', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('heart_reward_settings')->insert(['action_code' => 'idol_registration_approved', 'heart_amount' => 4321, 'daily_limit' => 0, 'is_enabled' => 1]);
    }

    public function test_admin_can_view_real_request_list(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests')
            ->assertOk()
            ->assertSee('아이돌 등록 신청')
            ->assertSee('purple_universe')
            ->assertSee('승인')
            ->assertSee('반려');
    }

    public function test_review_shows_english_name_first_and_allows_approval_without_korean_name(): void
    {
        $this->database();

        $page = $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')->assertOk();
        $html = $page->getContent();
        $englishField = strpos($html, '<span>영문 이름 *</span><input name="english_name"');
        $koreanField = strpos($html, '<span>한글 이름</span><input name="name"');
        $this->assertNotFalse($englishField);
        $this->assertNotFalse($koreanField);
        $this->assertLessThan($koreanField, $englishField);
        $this->assertStringNotContainsString('name="name" value="하나" maxlength="200" required', $html);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'english_name' => 'HANA',
            'content' => '소개',
        ])->assertRedirect('/admin/idol-requests/12');

        $this->assertSame('HANA', DB::table('idols')->value('name'));
        $this->assertSame('HANA', DB::table('idols')->value('english_name'));
    }

    public function test_admin_can_review_and_approve_request_once(): void
    {
        $this->database();
        DB::table('idol_registration_request_debuts')->insert([
            ['request_idx' => 12, 'country_code' => 'KR', 'debut_date' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'country_code' => 'JP', 'debut_date' => '2025-02-01', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')
            ->assertOk()->assertSee('아이돌 등록 신청 상세')->assertSee('승인')->assertSee('반려')
            ->assertSee('승인 시 신청자에게 4,321 Heart가 지급됩니다.');

        $this->assertSame(0, DB::table('heart_histories')->count());

        $payload = [
            'name' => '하나 수정', 'english_name' => 'HANA EDITED', 'agency' => '테스트 엔터',
            'birth_date' => '2000-01-01',
            'debut_dates' => [['country_code' => 'KR', 'debut_date' => '2025-01-01'], ['country_code' => 'JP', 'debut_date' => '2025-02-01']],
            'height_cm' => 165, 'weight_kg' => 48, 'blood_type' => 'A', 'position' => '보컬',
            'sns_links' => ['https://instagram.com/hana', 'https://youtu.be/hana'], 'content' => '수정된 소개',
        ];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertRedirect('/admin/idol-requests/12')
            ->assertSessionHas('admin_success', '아이돌 등록 신청을 승인했습니다.');

        $saved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('approved', $saved->status);
        $this->assertNotNull($saved->approved_idol_idx);
        $this->assertSame($saved->image_path, DB::table('idols')->where('idx', $saved->approved_idol_idx)->value('image_path'));
        $expectedSocialLinks = ['https://instagram.com/hana', 'https://youtu.be/hana'];
        $this->assertSame($expectedSocialLinks, json_decode($saved->sns_links, true));
        $this->assertSame($expectedSocialLinks, json_decode(
            DB::table('idols')->where('idx', $saved->approved_idol_idx)->value('sns_links'), true
        ));
        $this->assertSame(1, (int) $saved->reviewed_by_member_idx);
        $this->assertSame('하나 수정', DB::table('idols')->where('idx', $saved->approved_idol_idx)->value('name'));
        $this->assertSame(1, DB::table('admin_activity_logs')->where('action', 'idol_request_approved')->count());
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => $saved->approved_idol_idx, 'country_code' => 'KR', 'debut_date' => '2025-01-01']);
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => $saved->approved_idol_idx, 'country_code' => 'JP', 'debut_date' => '2025-02-01']);
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $history = DB::table('heart_histories')->where('member_idx', 2)->first();
        $this->assertSame(4321, (int) $history->amount);
        $this->assertSame('idol_registration_approved', $history->reason);
        $this->assertSame('idol_registration_approved:12', $history->operation_key);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertSessionHasErrors('status');
        $this->assertSame(1, DB::table('idols')->count());
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', 2)
            ->where('operation_key', 'idol_registration_approved:12')->count());

        $idolIdx = (int) $saved->approved_idol_idx;
        $this->withSession(['member_idx' => 1])->get("/admin/idols/{$idolIdx}/edit")
            ->assertOk()->assertSee('https://instagram.com/hana')->assertSee('https://youtu.be/hana');
        $this->withSession(['member_idx' => 1])->put("/admin/idols/{$idolIdx}", [
            'name' => 'HANA EDITED', 'english_name' => 'HANA EDITED', 'agency' => 'Demo Agency',
            'content' => 'Updated introduction', 'status' => 'active',
            'sns_links' => ['https://x.com/hana', 'https://tiktok.com/@hana'],
        ])->assertRedirect("/admin/idols/{$idolIdx}/edit");
        $this->assertSame(['https://x.com/hana', 'https://tiktok.com/@hana'], json_decode(
            DB::table('idols')->where('idx', $idolIdx)->value('sns_links'), true
        ));
    }

    public function test_approval_rejects_an_exact_name_found_only_in_an_existing_alias(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 20, 'type' => 'individual', 'gender' => 'female', 'name' => 'Existing Name',
            'english_name' => 'Canonical Existing', 'status' => 'active', 'image_path' => null,
        ]);
        DB::table('idol_name_aliases')->insert([
            'idol_idx' => 20, 'language_code' => 'ja', 'alias_name' => 'Approval Alias',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->from('/admin/idol-requests/12')->post('/admin/idol-requests/12/approve', [
            'name' => 'Reviewed Name', 'english_name' => 'Approval Alias', 'content' => 'Description',
        ])->assertSessionHasErrors('name');

        $this->assertSame('pending', DB::table('idol_registration_requests')->where('idx', 12)->value('status'));
        $this->assertSame(1, DB::table('idols')->count());
    }

    public function test_admin_request_review_shows_deduplicated_matches_for_same_and_other_categories(): void
    {
        $this->database();
        DB::table('idols')->insert([
            ['idx' => 20, 'type' => 'individual', 'gender' => 'female', 'name' => 'Alias Candidate', 'english_name' => 'Canonical Name', 'status' => 'active', 'image_path' => null],
            ['idx' => 21, 'type' => 'group', 'gender' => 'male', 'name' => 'HANA Group', 'english_name' => 'HANA', 'status' => 'active', 'image_path' => null],
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 20, 'language_code' => 'ja', 'alias_name' => 'HANA', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 20, 'language_code' => 'ko', 'alias_name' => 'HANA', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')->assertOk()
            ->assertSee('같은 유형')
            ->assertSee('다른 유형에 등록된 대상')
            ->assertSee('Alias Candidate')
            ->assertSee('HANA Group')
            ->assertSee('여자 아이돌')
            ->assertSee('남자 그룹')
            ->assertSee('images/no-image.png')
            ->assertSee(route('idol.show', ['idx' => 20]))
            ->assertSee(route('idol.show', ['idx' => 21]));
        $this->assertSame(1, substr_count($this->get('/admin/idol-requests/12')->getContent(), 'Alias Candidate'));
    }

    public function test_exact_match_in_another_category_does_not_block_admin_approval(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 20, 'type' => 'group', 'gender' => 'male', 'name' => 'HANA Group',
            'english_name' => 'HANA', 'status' => 'active', 'image_path' => null,
        ]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ])->assertRedirect('/admin/idol-requests/12');

        $this->assertSame('approved', DB::table('idol_registration_requests')->where('idx', 12)->value('status'));
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'idol_registration_approved:12')->count());
    }

    public function test_group_approval_rewards_the_original_applicant_once(): void
    {
        $this->database();
        DB::table('idol_registration_requests')->where('idx', 12)->update([
            'type' => 'group', 'gender' => 'male', 'name' => 'Test Group', 'english_name' => 'Test Group',
            'genre' => 'Pop', 'leader' => 'Initial Leader',
        ]);
        DB::table('idol_registration_request_localizations')->insert([
            'request_idx' => 12, 'language_code' => 'ja', 'text_role' => 'submitted',
            'localized_name' => null, 'description' => null, 'agency' => 'Japanese Agency',
            'genre' => 'Japanese Genre', 'leader' => 'Japanese Leader', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $payload = ['name' => 'Test Group', 'english_name' => 'Test Group', 'genre' => 'Alternative Pop', 'leader' => 'Free Text Leader', 'content' => 'Group introduction'];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertRedirect('/admin/idol-requests/12');

        $approved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('approved', $approved->status);
        $this->assertSame('group', DB::table('idols')->where('idx', $approved->approved_idol_idx)->value('type'));
        $this->assertSame('Alternative Pop', DB::table('idols')->where('idx', $approved->approved_idol_idx)->value('genre'));
        $this->assertSame('Free Text Leader', DB::table('idols')->where('idx', $approved->approved_idol_idx)->value('leader'));
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $approved->approved_idol_idx, 'language_code' => 'ja',
            'agency' => 'Japanese Agency', 'genre' => 'Japanese Genre', 'leader' => 'Japanese Leader',
        ]);
        $this->assertSame('Alternative Pop', $approved->genre);
        $this->assertSame('Free Text Leader', $approved->leader);
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(100, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 2, 'amount' => 4321, 'reason' => 'idol_registration_approved',
            'operation_key' => 'idol_registration_approved:12',
        ]);
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertSessionHasErrors('status');
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'idol_registration_approved:12')->count());

        $idolIdx = (int) $approved->approved_idol_idx;
        $this->withSession(['member_idx' => 1])->get("/admin/idols/{$idolIdx}/edit")->assertOk()
            ->assertSee('Alternative Pop')->assertSee('Free Text Leader');
        $this->withSession(['member_idx' => 1])->put("/admin/idols/{$idolIdx}", [
            'name' => 'Test Group', 'english_name' => 'Test Group', 'content' => 'Updated group',
            'status' => 'active', 'genre' => 'Dance Pop', 'leader' => 'Updated Free Text Leader',
        ])->assertRedirect("/admin/idols/{$idolIdx}/edit");
        $this->assertDatabaseHas('idols', [
            'idx' => $idolIdx, 'genre' => 'Dance Pop', 'leader' => 'Updated Free Text Leader',
        ]);
    }

    public function test_admin_can_open_and_save_individual_and_group_edit_pages(): void
    {
        $this->database();
        DB::table('idols')->insert([
            [
                'idx' => 101, 'type' => 'group', 'gender' => 'female', 'name' => 'Example Group',
                'english_name' => 'Example Group', 'genre' => 'Pop', 'leader' => 'Original Leader',
                'content' => 'Group introduction', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'idx' => 102, 'type' => 'individual', 'gender' => 'female', 'name' => 'Example Idol',
                'english_name' => 'Example Idol', 'genre' => null, 'leader' => null,
                'content' => 'Idol introduction', 'status' => 'active',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idols/101/edit')->assertOk()
            ->assertSee('value="Pop"', false)->assertSee('value="Original Leader"', false);
        $this->withSession(['member_idx' => 1])->put('/admin/idols/101', [
            'name' => 'Example Group', 'english_name' => 'Example Group', 'genre' => 'Dance',
            'leader' => 'Updated Leader', 'content' => 'Updated group introduction', 'status' => 'active',
        ])->assertRedirect('/admin/idols/101/edit');
        $this->assertDatabaseHas('idols', ['idx' => 101, 'genre' => 'Dance', 'leader' => 'Updated Leader']);

        $this->withSession(['member_idx' => 1])->get('/admin/idols/102/edit')->assertOk()
            ->assertSee('Example Idol');
        $this->withSession(['member_idx' => 1])->put('/admin/idols/102', [
            'name' => 'Example Idol', 'english_name' => 'Example Idol', 'content' => 'Updated idol introduction',
            'status' => 'active',
        ])->assertRedirect('/admin/idols/102/edit');
        $this->assertDatabaseHas('idols', ['idx' => 102, 'genre' => null, 'leader' => null]);
    }

    public function test_admin_request_and_idol_edit_fields_follow_registration_order(): void
    {
        $this->database();
        DB::table('idol_registration_request_debuts')->insert([
            'request_idx' => 12, 'country_code' => 'KR', 'debut_date' => '2020-01-02',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $individualRequest = $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')->assertOk();
        $this->assertFieldOrder($individualRequest->getContent(), [
            'name="english_name"', 'name="name"', 'name="group_idx"', 'name="birth_date"',
            'name="height_cm"', 'name="weight_kg"', 'name="blood_type"',
            'name="agency"', 'name="position"', 'name="sns_links[]"', 'name="content"',
        ]);
        $individualRequest->assertSee('데뷔일');

        DB::table('idol_registration_requests')->where('idx', 12)->update([
            'type' => 'group', 'gender' => 'female', 'group_idx' => null,
        ]);
        $groupRequest = $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')->assertOk();
        $this->assertFieldOrder($groupRequest->getContent(), [
            'name="english_name"', 'name="name"', 'name="agency"',
            'name="genre"', 'name="leader"', 'name="sns_links[]"', 'name="content"',
        ]);

        DB::table('idols')->insert([
            ['idx' => 41, 'type' => 'group', 'gender' => 'female', 'name' => 'Review Group', 'english_name' => 'Review Group', 'genre' => null, 'leader' => null, 'content' => 'Group', 'status' => 'active'],
            ['idx' => 42, 'type' => 'individual', 'gender' => 'female', 'name' => 'Review Idol', 'english_name' => 'Review Idol', 'genre' => null, 'leader' => null, 'content' => 'Individual', 'status' => 'active'],
        ]);

        $individualEdit = $this->withSession(['member_idx' => 1])->get('/admin/idols/42/edit')->assertOk();
        $this->assertFieldOrder($individualEdit->getContent(), [
            'name="english_name"', 'name="name"', 'name="group_idx"', 'name="birth_date"',
            'name="height_cm"', 'name="weight_kg"', 'name="blood_type"',
            'name="agency"', 'name="debut_dates[0][country_code]"', 'name="position"', 'name="sns_links[]"', 'name="content"',
        ]);

        $groupEdit = $this->withSession(['member_idx' => 1])->get('/admin/idols/41/edit')->assertOk();
        $this->assertFieldOrder($groupEdit->getContent(), [
            'name="english_name"', 'name="name"', 'name="agency"',
            'name="debut_dates[0][country_code]"', 'name="genre"', 'name="leader"', 'name="sns_links[]"', 'name="content"',
        ]);
    }

    public function test_admin_edit_group_autocomplete_preselects_searches_and_clears_existing_group(): void
    {
        $this->database();
        DB::table('idols')->insert([
            ['idx' => 301, 'type' => 'group', 'gender' => 'female', 'name' => '한글 그룹', 'english_name' => 'Example Stars', 'content' => 'Group', 'status' => 'active'],
            ['idx' => 302, 'type' => 'individual', 'gender' => 'female', 'name' => 'Member', 'english_name' => 'Member', 'content' => 'Individual', 'status' => 'active'],
        ]);
        DB::table('idol_group_members')->insert(['individual_idx' => 302, 'group_idx' => 301, 'created_at' => now()]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 301, 'language_code' => 'ko', 'localized_name' => '별빛', 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 301, 'language_code' => 'ja', 'alias_name' => 'スターズ別名', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $edit = $this->withSession(['member_idx' => 1])->get('/admin/idols/302/edit')->assertOk();
        $edit->assertSee('value="Example Stars · 별빛"', false)
            ->assertSee('name="group_idx" value="301"', false)
            ->assertDontSee('<select name="group_idx">', false)
            ->assertSee('name="english_name" value="Member" maxlength="200" required', false)
            ->assertSee('name="name" value="Member" maxlength="200">', false);
        $this->assertLessThan(strpos($edit->getContent(), 'name="name"'), strpos($edit->getContent(), 'name="english_name"'));

        $search = $this->withSession(['member_idx' => 1])->getJson(route('idol-registration.groups', [
            'request_type' => 'girl', 'q' => 'スターズ別名',
        ]))->assertOk();
        $this->assertSame([301], array_column($search->json('groups'), 'idx'));
        foreach (range(320, 330) as $groupIdx) {
            DB::table('idols')->insert([
                'idx' => $groupIdx, 'type' => 'group', 'gender' => 'female', 'name' => 'Searchable Group '.$groupIdx,
                'english_name' => 'Searchable Group '.$groupIdx, 'content' => 'Group', 'status' => 'active',
            ]);
        }
        $this->withSession(['member_idx' => 1])->getJson(route('idol-registration.groups', [
            'request_type' => 'girl', 'q' => 'Searchable',
        ]))->assertOk()->assertJsonCount(10, 'groups');

        $this->withSession(['member_idx' => 1])->put('/admin/idols/302', [
            'english_name' => 'Member Updated', 'content' => 'Updated', 'status' => 'active', 'group_idx' => '301',
        ])->assertRedirect('/admin/idols/302/edit');
        $this->assertSame('Member Updated', DB::table('idols')->where('idx', 302)->value('name'));
        $this->assertDatabaseHas('idol_group_members', ['individual_idx' => 302, 'group_idx' => 301]);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/302', [
            'english_name' => 'Member Updated', 'content' => 'Updated', 'status' => 'active', 'group_idx' => '',
        ])->assertRedirect('/admin/idols/302/edit');
        $this->assertDatabaseMissing('idol_group_members', ['individual_idx' => 302]);
        $this->withSession(['member_idx' => 1])->from('/admin/idols/302/edit')->put('/admin/idols/302', [
            'english_name' => '', 'content' => 'Updated', 'status' => 'active',
        ])->assertSessionHasErrors('english_name');
        $this->assertDatabaseHas('idol_localizations', ['idol_idx' => 301, 'language_code' => 'ko', 'localized_name' => '별빛']);
        $this->assertDatabaseHas('idol_name_aliases', ['idol_idx' => 301, 'language_code' => 'ja', 'alias_name' => 'スターズ別名']);
    }

    public function test_admin_group_autocomplete_rejects_inactive_wrong_gender_and_non_group_ids(): void
    {
        $this->database();
        DB::table('idols')->insert([
            ['idx' => 311, 'type' => 'group', 'gender' => 'female', 'name' => 'Inactive Group', 'english_name' => 'Inactive Group', 'content' => 'Group', 'status' => 'inactive'],
            ['idx' => 312, 'type' => 'group', 'gender' => 'male', 'name' => 'Boy Group', 'english_name' => 'Boy Group', 'content' => 'Group', 'status' => 'active'],
            ['idx' => 313, 'type' => 'individual', 'gender' => 'female', 'name' => 'Other Idol', 'english_name' => 'Other Idol', 'content' => 'Individual', 'status' => 'active'],
            ['idx' => 314, 'type' => 'individual', 'gender' => 'female', 'name' => 'Target Idol', 'english_name' => 'Target Idol', 'content' => 'Individual', 'status' => 'active'],
        ]);

        foreach ([312, 313, 999] as $invalidGroupIdx) {
            $this->withSession(['member_idx' => 1])->put('/admin/idols/314', [
                'english_name' => 'Target Idol', 'content' => 'Individual', 'status' => 'active', 'group_idx' => $invalidGroupIdx,
            ])->assertSessionHasErrors('group_idx');
            $this->assertDatabaseMissing('idol_group_members', ['individual_idx' => 314]);
        }

        $this->withSession(['member_idx' => 1])->getJson(route('idol-registration.groups', [
            'request_type' => 'girl', 'q' => 'Group',
        ]))->assertOk()->assertJsonCount(0, 'groups');
    }

    public function test_admin_edit_loads_and_saves_localizations_and_aliases_without_losing_unicode(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 51, 'type' => 'group', 'gender' => 'female', 'name' => 'Legacy Group',
            'english_name' => 'Legacy Group EN', 'content' => 'Legacy description', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 51, 'language_code' => 'ko', 'localized_name' => '기존 그룹', 'description' => '기존 소개', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 51, 'language_code' => 'ja', 'localized_name' => '旧グループ', 'description' => '旧紹介', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $preservedAliasIdx = DB::table('idol_name_aliases')->insertGetId([
            'idol_idx' => 51, 'language_code' => 'ko', 'alias_name' => '기존 별칭', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idols/51/edit')->assertOk()
            ->assertSee('기존 그룹')->assertSee('기존 소개')->assertSee('旧グループ')->assertSee('기존 별칭')
            ->assertSee('다국어 정보 관리')->assertSee('name="multilingual_data_present"', false);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/51', [
            'name' => 'Legacy Group', 'english_name' => 'Canonical Group', 'content' => 'Updated legacy text',
            'status' => 'active', 'sns_links' => [], 'multilingual_data_present' => '1',
            'localizations' => [
                ['language_code' => 'en', 'localized_name' => 'Conflicting English', 'description' => 'English description', 'aliases' => []],
                ['language_code' => 'ko', 'localized_name' => '새 그룹', 'description' => '새 소개', 'aliases' => ['기존 별칭', '새 별칭']],
                ['language_code' => 'ja', 'localized_name' => '新グループ', 'description' => '新しい紹介', 'aliases' => ['新しい別名']],
            ],
        ])->assertRedirect('/admin/idols/51/edit');

        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => 51, 'language_code' => 'en', 'localized_name' => 'Canonical Group', 'description' => 'English description',
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => 51, 'language_code' => 'ko', 'localized_name' => '새 그룹', 'description' => '새 소개',
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => 51, 'language_code' => 'ja', 'localized_name' => '新グループ', 'description' => '新しい紹介',
        ]);
        $this->assertSame($preservedAliasIdx, DB::table('idol_name_aliases')->where('idol_idx', 51)
            ->where('language_code', 'ko')->where('alias_name', '기존 별칭')->value('idx'));
        $this->assertDatabaseHas('idol_name_aliases', ['idol_idx' => 51, 'language_code' => 'ko', 'alias_name' => '새 별칭']);
        $this->assertDatabaseHas('idol_name_aliases', ['idol_idx' => 51, 'language_code' => 'ja', 'alias_name' => '新しい別名']);
        $this->assertSame('새 그룹', $this->withSession(['locale' => 'ko'])->get('/idol/51')->assertOk()
            ->viewData('profile')['Name']);
    }

    public function test_custom_language_code_is_prepopulated_and_existing_language_cannot_be_changed(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 71, 'type' => 'group', 'gender' => 'male', 'name' => 'Example Group',
            'english_name' => 'Example Group', 'content' => 'Description', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 71, 'language_code' => 'fr-CA', 'localized_name' => 'Groupe Exemple', 'description' => 'Description française', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idols/71/edit')->assertOk()
            ->assertSee('Groupe Exemple')
            ->assertSee('option value="other" selected', false)
            ->assertSee('value="fr-CA"', false)
            ->assertSee('data-existing-language="true"', false)
            ->assertSee('name="localizations[0][language_code]" value="fr-CA"', false)
            ->assertSee('data-language-choice disabled', false)
            ->assertSee('data-custom-language-code', false)
            ->assertSee('option value="zh-CN"', false)
            ->assertSee('option value="zh-TW"', false)
            ->assertSee('+ 별칭 추가')
            ->assertDontSee('data-remove-localization>');

        $this->withSession(['member_idx' => 1])->put('/admin/idols/71', [
            'english_name' => 'Example Group', 'content' => 'Updated description', 'status' => 'active',
        ])->assertRedirect('/admin/idols/71/edit');
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => 71, 'language_code' => 'fr-CA', 'localized_name' => 'Groupe Exemple',
        ]);
    }

    public function test_multilingual_editor_has_one_add_button_and_an_empty_state_without_localizations(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 72, 'type' => 'group', 'gender' => 'female', 'name' => 'Example Group',
            'english_name' => 'Example Group', 'content' => 'Description', 'status' => 'active',
        ]);

        $response = $this->withSession(['member_idx' => 1])->get('/admin/idols/72/edit')->assertOk()
            ->assertSee('data-localization-empty', false)
            ->assertSee('오른쪽 위 언어 추가 버튼으로 첫 언어를 추가할 수 있습니다.');

        $this->assertSame(1, substr_count($response->getContent(), 'data-add-localization'));
    }

    public function test_admin_idol_edit_manages_country_specific_debut_dates(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 73, 'type' => 'individual', 'gender' => 'female', 'name' => 'Example Idol',
            'english_name' => 'Example Idol', 'content' => 'Profile', 'status' => 'active',
        ]);

        $response = $this->withSession(['member_idx' => 1])->get('/admin/idols/73/edit')->assertOk();
        $response->assertSee('name="debut_dates[0][country_code]"', false)
            ->assertSee('name="debut_dates[0][debut_date]"', false)
            ->assertSee('class="admin-review-country-field"', false)
            ->assertSee('데뷔일')
            ->assertDontSee('name="nationality_code"', false)
            ->assertDontSee('name="nationality"', false);
        preg_match_all('/data-country-option data-code="([A-Z]{2})"/', $response->getContent(), $countryMatches);
        $this->assertSame(['KR', 'JP', 'US', 'CN', 'KR', 'JP', 'US', 'CN'], $countryMatches[1]);
        $initialRows = explode('<template data-debut-date-template', $response->getContent(), 2)[0];
        $this->assertSame(0, substr_count($initialRows, 'data-remove-debut-date'));

        $this->withSession(['member_idx' => 1])->put('/admin/idols/73', [
            'name' => 'Example Idol', 'english_name' => 'Example Idol',
            'debut_dates' => [['country_code' => 'KR', 'debut_date' => '2020-01-02'], ['country_code' => 'JP', 'debut_date' => '2021-02-03']],
            'content' => 'Updated profile', 'status' => 'active',
        ])->assertRedirect('/admin/idols/73/edit');

        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => 73, 'country_code' => 'KR', 'debut_date' => '2020-01-02']);
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => 73, 'country_code' => 'JP', 'debut_date' => '2021-02-03']);

        $savedRowsResponse = $this->withSession(['member_idx' => 1])->get('/admin/idols/73/edit')->assertOk()
            ->assertSee('name="debut_dates[0][country_code]" value="KR"', false)
            ->assertSee('name="debut_dates[1][country_code]" value="JP"', false);
        $visibleRows = explode('<template data-debut-date-template', $savedRowsResponse->getContent(), 2)[0];
        $this->assertSame(1, substr_count($visibleRows, 'data-remove-debut-date'));
        $this->withSession(['member_idx' => 1])->put('/admin/idols/73', [
            'name' => 'Example Idol', 'english_name' => 'Example Idol',
            'content' => 'Basic-only profile edit', 'status' => 'active',
        ])->assertRedirect('/admin/idols/73/edit');
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => 73, 'country_code' => 'KR', 'debut_date' => '2020-01-02']);
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => 73, 'country_code' => 'JP', 'debut_date' => '2021-02-03']);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/73', [
            'name' => 'Example Idol', 'english_name' => 'Example Idol', 'debut_dates' => [
                ['country_code' => 'KR', 'debut_date' => '2020-01-03'], ['country_code' => '', 'debut_date' => ''],
            ], 'content' => 'Updated profile', 'status' => 'active',
        ])->assertRedirect('/admin/idols/73/edit');
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => 73, 'country_code' => 'KR', 'debut_date' => '2020-01-03']);
        $this->assertDatabaseMissing('idol_debuts', ['idol_idx' => 73, 'country_code' => 'JP']);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/73', [
            'name' => 'Example Idol', 'english_name' => 'Example Idol',
            'debut_dates' => [['country_code' => '', 'debut_date' => '']],
            'content' => 'Empty debut row', 'status' => 'active',
        ])->assertRedirect('/admin/idols/73/edit');
        $this->assertSame(0, DB::table('idol_debuts')->where('idol_idx', 73)->count());
    }
    public function test_group_localized_profile_fields_load_save_and_explicit_clear(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 53, 'type' => 'group', 'gender' => 'female', 'name' => 'Profile Group',
            'english_name' => 'Profile Group', 'content' => 'Legacy description', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            'idol_idx' => 53, 'language_code' => 'en', 'localized_name' => 'Profile Group',
            'description' => 'Keep this description', 'agency' => 'Old Agency', 'genre' => 'Old Genre',
            'leader' => 'Old Leader', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->get('/admin/idols/53/edit')->assertOk()
            ->assertSee('value="Old Agency"', false)->assertSee('value="Old Genre"', false)->assertSee('value="Old Leader"', false)
            ->assertDontSee('name="localizations[0][position]"', false);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/53', [
            'name' => 'Profile Group', 'english_name' => 'Profile Group', 'content' => 'Legacy description',
            'status' => 'active', 'sns_links' => [], 'multilingual_data_present' => '1',
            'localizations' => [[
                'language_code' => 'en', 'localized_name' => 'Profile Group', 'description' => 'Keep this description',
                'agency' => 'New Agency', 'genre' => '', 'leader' => 'New Leader', 'aliases' => [],
            ]],
        ])->assertRedirect('/admin/idols/53/edit');

        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => 53, 'language_code' => 'en', 'description' => 'Keep this description',
            'agency' => 'New Agency', 'genre' => null, 'leader' => 'New Leader',
        ]);
    }

    public function test_basic_information_only_edit_preserves_multilingual_rows_and_aliases(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 52, 'type' => 'individual', 'gender' => 'female', 'name' => 'Old name',
            'english_name' => 'Old English', 'content' => 'Old content', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 52, 'language_code' => 'en', 'localized_name' => 'Old English', 'description' => 'English bio', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 52, 'language_code' => 'ko', 'localized_name' => '한국 이름', 'description' => '한국 소개', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('idol_name_aliases')->insert([
            'idol_idx' => 52, 'language_code' => 'ko', 'alias_name' => '별명', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/52', [
            'name' => 'New legacy name', 'english_name' => 'New English', 'content' => 'New legacy content',
            'status' => 'active', 'sns_links' => [],
        ])->assertRedirect('/admin/idols/52/edit');

        $this->assertDatabaseHas('idol_localizations', ['idol_idx' => 52, 'language_code' => 'en', 'localized_name' => 'New English', 'description' => 'English bio']);
        $this->assertDatabaseHas('idol_localizations', ['idol_idx' => 52, 'language_code' => 'ko', 'localized_name' => '한국 이름', 'description' => '한국 소개']);
        $this->assertDatabaseHas('idol_name_aliases', ['idol_idx' => 52, 'language_code' => 'ko', 'alias_name' => '별명']);
    }

    public function test_explicit_language_and_alias_removal_only_deletes_the_omitted_rows(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 55, 'type' => 'group', 'gender' => 'female', 'name' => 'Group',
            'english_name' => 'Group EN', 'content' => 'Description', 'status' => 'active',
        ]);
        DB::table('idol_localizations')->insert([
            ['idol_idx' => 55, 'language_code' => 'en', 'localized_name' => 'Group EN', 'description' => 'English', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 55, 'language_code' => 'ko', 'localized_name' => '그룹', 'description' => '한국어', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('idol_name_aliases')->insert([
            ['idol_idx' => 55, 'language_code' => 'en', 'alias_name' => 'English Alias', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 55, 'language_code' => 'ko', 'alias_name' => '보존 별칭', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 55, 'language_code' => 'ko', 'alias_name' => '삭제 별칭', 'created_at' => now(), 'updated_at' => now()],
            ['idol_idx' => 55, 'language_code' => 'ja', 'alias_name' => '削除する別名', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/55', [
            'name' => 'Group', 'english_name' => 'Group EN', 'content' => 'Description',
            'status' => 'active', 'sns_links' => [], 'multilingual_data_present' => '1',
            'localizations' => [[
                'language_code' => 'ko', 'localized_name' => '그룹', 'description' => '한국어',
                'aliases' => ['보존 별칭'],
            ]],
        ])->assertRedirect('/admin/idols/55/edit');

        $this->assertSame(1, DB::table('idol_localizations')->where('idol_idx', 55)->count());
        $this->assertDatabaseHas('idol_localizations', ['idol_idx' => 55, 'language_code' => 'ko', 'localized_name' => '그룹']);
        $this->assertSame(1, DB::table('idol_name_aliases')->where('idol_idx', 55)->count());
        $this->assertDatabaseHas('idol_name_aliases', ['idol_idx' => 55, 'language_code' => 'ko', 'alias_name' => '보존 별칭']);
        $this->assertSame(0, DB::table('idol_name_aliases')->where('idol_idx', 55)->where('alias_name', '삭제 별칭')->count());
        $this->assertSame(1, DB::table('idols')->where('idx', 55)->count());
    }

    public function test_duplicate_aliases_are_rejected_using_normalized_comparison(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 53, 'type' => 'individual', 'gender' => 'female', 'name' => 'Name',
            'english_name' => 'Name EN', 'content' => 'Content', 'status' => 'active',
        ]);

        $this->withSession(['member_idx' => 1])->put('/admin/idols/53', [
            'name' => 'Name', 'english_name' => 'Name EN', 'content' => 'Content', 'status' => 'active',
            'sns_links' => [], 'multilingual_data_present' => '1',
            'localizations' => [[
                'language_code' => 'ko', 'localized_name' => '이름', 'description' => null,
                'aliases' => ['Stage Name', '  stage   name  '],
            ]],
        ])->assertSessionHasErrors('localizations.0.aliases.1');

        $this->assertSame(0, DB::table('idol_localizations')->where('idol_idx', 53)->count());
        $this->assertSame(0, DB::table('idol_name_aliases')->where('idol_idx', 53)->count());
    }

    public function test_multilingual_save_rolls_back_with_basic_fields_when_alias_storage_fails(): void
    {
        $this->database();
        DB::table('idols')->insert([
            'idx' => 54, 'type' => 'individual', 'gender' => 'female', 'name' => 'Original',
            'english_name' => 'Original EN', 'content' => 'Original content', 'status' => 'active',
        ]);
        DB::statement("CREATE TRIGGER fail_idol_alias_insert BEFORE INSERT ON idol_name_aliases BEGIN SELECT RAISE(ABORT, 'alias insert failed'); END");

        $this->withSession(['member_idx' => 1])->put('/admin/idols/54', [
            'name' => 'Changed', 'english_name' => 'Changed EN', 'content' => 'Changed content',
            'status' => 'active', 'sns_links' => [], 'multilingual_data_present' => '1',
            'localizations' => [[
                'language_code' => 'ko', 'localized_name' => '변경', 'description' => '소개', 'aliases' => ['별칭'],
            ]],
        ])->assertInternalServerError();

        $this->assertDatabaseHas('idols', ['idx' => 54, 'name' => 'Original', 'english_name' => 'Original EN']);
        $this->assertSame(0, DB::table('idol_localizations')->where('idol_idx', 54)->count());
        $this->assertSame(0, DB::table('idol_name_aliases')->where('idol_idx', 54)->count());
    }

    private function assertFieldOrder(string $html, array $fields): void
    {
        $previousPosition = -1;
        foreach ($fields as $field) {
            $position = strpos($html, $field);
            $this->assertNotFalse($position, "Expected form field {$field} to be present.");
            $this->assertGreaterThan($previousPosition, $position, "Expected form field {$field} in the requested order.");
            $previousPosition = $position;
        }
    }

    public function test_admin_can_submit_registration_request_as_a_dummy_member(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 3, 'member_code' => 'dummyMember03', 'email' => 'dummy@example.test',
            'nickname' => 'dummy_member', 'heart_balance' => 50, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active',
        ]);
        Storage::fake('public');

        $this->withSession(['member_idx' => 1])->post(route('admin.acting-dummy-member'), [
            'dummy_member_idx' => 3,
        ])->assertRedirect(route('home'));
        $this->get('/idol-registration/request')->assertOk();
        $requestKey = session('idol_registration_request_key');

        $this->post('/idol-registration/request', [
            'request_type' => 'boy', 'name' => 'Test Idol', 'english_name' => 'Test Idol',
            'duplicate_search_token' => $this->getJson(route('idol-registration.duplicates', [
                'request_type' => 'boy', 'english_name' => 'Test Idol',
            ]))->assertOk()->json('token'),
            'profile_image' => UploadedFile::fake()->image('idol.jpg'),
            'content' => 'A real request from the dummy member.', 'source_language_code' => 'en', 'request_key' => $requestKey,
        ])->assertRedirect(route('idol-registration.create'));

        $this->assertDatabaseHas('idol_registration_requests', [
            'member_idx' => 3, 'actor_member_idx' => 1, 'status' => 'pending',
        ]);
        $requestIdx = (int) DB::table('idol_registration_requests')->where('member_idx', 3)->value('idx');
        $this->post('/admin/idol-requests/'.$requestIdx.'/approve', [
            'name' => 'Test Idol', 'english_name' => 'Test Idol', 'content' => 'Approved dummy request.',
        ])->assertRedirect('/admin/idol-requests/'.$requestIdx);
        $this->assertSame(50, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseMissing('heart_histories', [
            'member_idx' => 3, 'reason' => 'idol_registration_approved',
            'operation_key' => 'idol_registration_approved:'.$requestIdx,
        ]);
        $this->assertSame(1, (int) session('member_idx'));
    }

    public function test_random_dummy_registration_is_approved_and_rewarded_once_with_actor_attribution(): void
    {
        $this->database();
        DB::table('members')->insert([
            'idx' => 3, 'member_code' => 'dummyMember03', 'email' => 'dummy@example.test',
            'nickname' => 'dummy_member', 'heart_balance' => 50, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active',
        ]);
        Storage::fake('public');

        $session = ['member_idx' => 1, \App\Support\RandomDummyActivity::SESSION_KEY => true];
        $this->withSession($session)->get('/idol-registration/request')->assertOk();
        $requestKey = (string) session('idol_registration_request_key');
        $duplicateToken = $this->getJson(route('idol-registration.duplicates', [
            'request_type' => 'boy', 'english_name' => 'Random Mode Idol',
        ]))->assertOk()->json('token');
        $image = UploadedFile::fake()->image('idol.png');
        $payload = [
            'request_type' => 'boy', 'name' => '', 'english_name' => 'Random Mode Idol',
            'duplicate_search_token' => $duplicateToken, 'profile_image' => $image,
            'content' => 'Random mode introduction.', 'source_language_code' => 'en', 'request_key' => $requestKey,
        ];

        $first = $this->post('/idol-registration/request', $payload)->assertRedirect();
        $requestRow = DB::table('idol_registration_requests')->where('request_key', $requestKey)->first();
        $this->assertSame(3, (int) $requestRow->member_idx);
        $this->assertSame(1, (int) $requestRow->actor_member_idx);
        $this->assertSame('approved', $requestRow->status);
        $this->assertSame('ko', DB::table('idol_registration_request_localizations')
            ->where('request_idx', $requestRow->idx)->where('text_role', 'submitted')
            ->where('description', 'Random mode introduction.')->value('language_code'));
        $this->assertDatabaseHas('admin_dummy_activity_requests', [
            'actor_member_idx' => 1, 'member_idx' => 3, 'action_code' => 'idol_registration',
            'result_type' => 'registration_request', 'result_idx' => $requestRow->idx,
        ]);
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 1, 'amount' => 4321,
            'operation_key' => 'idol_registration_approved:'.$requestRow->idx,
        ]);
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertSame(1, DB::table('idols')->count());

        $this->post('/idol-registration/request', $payload)->assertRedirect(route('idol.show', ['idx' => $requestRow->approved_idol_idx]));
        $this->assertSame(2, DB::table('idol_registration_requests')->count());
        $this->assertSame(1, DB::table('idols')->count());
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'idol_registration_approved:'.$requestRow->idx)->count());
        $this->assertSame(1, (int) session('member_idx'));
    }

    public function test_disabled_approval_reward_does_not_block_approval(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')
            ->update(['is_enabled' => 0]);

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')->assertOk()
            ->assertSee('현재 승인 보상이 없어 Heart가 지급되지 않습니다.')
            ->assertDontSee('승인 시 신청자에게 4,321 Heart가 지급됩니다.');
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ])->assertRedirect('/admin/idol-requests/12');

        $this->assertSame('approved', DB::table('idol_registration_requests')->where('idx', 12)->value('status'));
        $this->assertSame(1, DB::table('idols')->count());
        $this->assertSame(50, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_reviewed_localizations_save_separately_and_are_preferred_on_approval(): void
    {
        $this->database();
        $originalEnglish = " HANA!\n";
        $originalKorean = "하나 원문\n";
        $originalJapanese = "はな\n原文";
        $originalSimplifiedChinese = "简体名称\n原文介绍";
        $originalTraditionalChinese = "繁體名稱\n原文介紹";
        DB::table('idol_registration_request_debuts')->insert([
            'request_idx' => 12, 'country_code' => 'JP', 'debut_date' => '2020-01-02',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('idol_registration_request_localizations')->insert(array_map(
            static fn (array $row): array => $row + ['agency' => null, 'position' => null, 'genre' => null, 'leader' => null],
            [
            ['request_idx' => 12, 'language_code' => 'en', 'text_role' => 'submitted', 'localized_name' => $originalEnglish, 'description' => "Original EN\ntext", 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'language_code' => 'ko', 'text_role' => 'submitted', 'localized_name' => $originalKorean, 'description' => "원문 소개\n그대로", 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'language_code' => 'ja', 'text_role' => 'submitted', 'localized_name' => null, 'description' => $originalJapanese, 'agency' => '原文事務所', 'position' => '原文ポジション', 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'language_code' => 'zh-CN', 'text_role' => 'submitted', 'localized_name' => '简体名称', 'description' => $originalSimplifiedChinese, 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'language_code' => 'zh-TW', 'text_role' => 'submitted', 'localized_name' => '繁體名稱', 'description' => $originalTraditionalChinese, 'agency' => null, 'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now()],
            ]
        ));

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')
            ->assertOk()->assertSee('신청자가 제출한 원문')->assertSee('관리자 검토 다국어 정보')
            ->assertSee('원문 소개')->assertSee('はな')->assertSee('原文事務所');

        $reviewPayload = [
            'name' => 'Hana', 'english_name' => 'HANA', 'content' => 'Legacy intro',
            'reviewed_localizations' => [
                'en' => ['localized_name' => 'Hana Reviewed', 'description' => "Reviewed EN\nintro"],
                'ko' => ['localized_name' => '하나 검토', 'description' => ''],
                'ja' => ['agency' => '検討事務所'],
            ],
        ];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/save', $reviewPayload)
            ->assertRedirect('/admin/idol-requests/12');

        $this->assertSame($originalEnglish, DB::table('idol_registration_request_localizations')
            ->where('language_code', 'en')->where('text_role', 'submitted')->value('localized_name'));
        $this->assertSame($originalKorean, DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ko')->where('text_role', 'submitted')->value('localized_name'));
        $this->assertSame("원문 소개\n그대로", DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ko')->where('text_role', 'submitted')->value('description'));
        $this->assertSame('原文事務所', DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ja')->where('text_role', 'submitted')->value('agency'));
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'language_code' => 'en', 'text_role' => 'reviewed',
            'localized_name' => 'Hana Reviewed', 'description' => "Reviewed EN\nintro",
        ]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'Hana', 'english_name' => 'HANA', 'content' => 'Legacy intro',
        ])->assertRedirect('/admin/idol-requests/12');

        $idolIdx = (int) DB::table('idol_registration_requests')->where('idx', 12)->value('approved_idol_idx');
        $this->assertSame('Hana Reviewed', DB::table('idols')->where('idx', $idolIdx)->value('english_name'));
        $this->assertSame('하나 검토', DB::table('idols')->where('idx', $idolIdx)->value('name'));
        $this->assertDatabaseHas('idol_debuts', ['idol_idx' => $idolIdx, 'country_code' => 'JP', 'debut_date' => '2020-01-02']);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'en', 'localized_name' => 'Hana Reviewed',
            'description' => "Reviewed EN\nintro",
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'ko', 'localized_name' => '하나 검토',
            'description' => "원문 소개\n그대로",
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'ja', 'localized_name' => null,
            'description' => $originalJapanese, 'agency' => '検討事務所', 'position' => '原文ポジション',
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'zh-CN', 'localized_name' => '简体名称',
            'description' => $originalSimplifiedChinese,
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'zh-TW', 'localized_name' => '繁體名稱',
            'description' => $originalTraditionalChinese,
        ]);
        $this->assertSame($originalEnglish, DB::table('idol_registration_request_localizations')
            ->where('language_code', 'en')->where('text_role', 'submitted')->value('localized_name'));
    }

    public function test_basic_profile_edits_survive_save_then_approval_in_the_submitted_language(): void
    {
        $this->database();
        DB::table('idol_registration_requests')->where('idx', 12)->update([
            'type' => 'group', 'name' => '원래 그룹', 'english_name' => 'Original Group',
            'agency' => '원래 소속사', 'genre' => '원래 장르', 'leader' => '원래 리더', 'content' => '원래 소개',
        ]);
        DB::table('idol_registration_request_localizations')->insert([
            [
                'request_idx' => 12, 'language_code' => 'en', 'text_role' => 'submitted',
                'localized_name' => 'Original Group', 'description' => null, 'agency' => null,
                'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'request_idx' => 12, 'language_code' => 'ko', 'text_role' => 'submitted',
                'localized_name' => '원래 그룹', 'description' => '원래 소개', 'agency' => '원래 소속사',
                'position' => null, 'genre' => '원래 장르', 'leader' => '원래 리더', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/save', [
            'name' => '검토 그룹', 'english_name' => 'Reviewed Group', 'agency' => '검토 소속사',
            'genre' => '검토 장르', 'leader' => '검토 리더', 'content' => '검토 소개',
            'sns_links' => ['https://example.com/group'],
        ])->assertRedirect('/admin/idol-requests/12');

        $this->assertSame('원래 소개', DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ko')->where('text_role', 'submitted')->value('description'));
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'language_code' => 'ko', 'text_role' => 'reviewed',
            'localized_name' => '검토 그룹', 'description' => '검토 소개', 'agency' => '검토 소속사',
            'genre' => '검토 장르', 'leader' => '검토 리더',
        ]);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'language_code' => 'en', 'text_role' => 'reviewed',
            'localized_name' => 'Reviewed Group',
        ]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => '검토 그룹', 'english_name' => 'Reviewed Group', 'agency' => '검토 소속사',
            'genre' => '검토 장르', 'leader' => '검토 리더', 'content' => '검토 소개',
            'sns_links' => ['https://example.com/group'],
        ])->assertRedirect('/admin/idol-requests/12');

        $idol = DB::table('idols')->where('idx', DB::table('idol_registration_requests')->where('idx', 12)->value('approved_idol_idx'))->first();
        $this->assertSame('Reviewed Group', $idol->english_name);
        $this->assertSame('검토 그룹', $idol->name);
        $this->assertSame('검토 소속사', $idol->agency);
        $this->assertSame('검토 장르', $idol->genre);
        $this->assertSame('검토 리더', $idol->leader);
        $this->assertSame('검토 소개', $idol->content);
        $this->assertSame(['https://example.com/group'], json_decode($idol->sns_links, true));

        $resolved = app(IdolLocalizationResolver::class)->resolveMany([$idol], 'ko')[(int) $idol->idx];
        $this->assertSame('검토 그룹', $resolved['name']);
        $this->assertSame('검토 소개', $resolved['description']);
        $this->assertSame('검토 소속사', $resolved['agency']);
        $this->assertSame('검토 장르', $resolved['genre']);
        $this->assertSame('검토 리더', $resolved['leader']);
        $this->get('/idol/'.$idol->idx)->assertOk()
            ->assertSee('Reviewed Group')->assertSee('검토 소개')->assertSee('검토 소속사')
            ->assertSee('검토 장르')->assertSee('검토 리더');
    }

    public function test_basic_individual_position_and_details_survive_save_then_approval(): void
    {
        $this->database();
        DB::table('idol_registration_requests')->where('idx', 12)->update([
            'name' => '하나', 'english_name' => 'HANA', 'agency' => '원래 소속사',
            'position' => '원래 포지션', 'content' => '원래 소개',
        ]);
        DB::table('idol_registration_request_localizations')->insert([
            [
                'request_idx' => 12, 'language_code' => 'en', 'text_role' => 'submitted',
                'localized_name' => 'HANA', 'description' => null, 'agency' => null,
                'position' => null, 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'request_idx' => 12, 'language_code' => 'ko', 'text_role' => 'submitted',
                'localized_name' => '하나', 'description' => '원래 소개', 'agency' => '원래 소속사',
                'position' => '원래 포지션', 'genre' => null, 'leader' => null, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $payload = [
            'name' => '하나 수정', 'english_name' => 'HANA EDITED', 'agency' => '검토 소속사',
            'birth_date' => '2001-02-03', 'height_cm' => 167, 'weight_kg' => 49,
            'blood_type' => 'A', 'position' => '검토 포지션', 'content' => '검토 소개',
            'sns_links' => ['https://example.com/hana'],
        ];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/save', $payload)
            ->assertRedirect('/admin/idol-requests/12');
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertRedirect('/admin/idol-requests/12');

        $requestRow = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $idol = DB::table('idols')->where('idx', $requestRow->approved_idol_idx)->first();
        $this->assertSame('individual', $idol->type);
        $this->assertSame('HANA EDITED', $idol->english_name);
        $this->assertSame('하나 수정', $idol->name);
        $this->assertSame('2001-02-03', $idol->birth_date);
        $this->assertSame(167.0, (float) $idol->height_cm);
        $this->assertSame(49.0, (float) $idol->weight_kg);
        $this->assertSame('A', $idol->blood_type);
        $this->assertSame('검토 포지션', $idol->position);
        $this->assertSame('검토 소개', $idol->content);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idol->idx, 'language_code' => 'ko', 'localized_name' => '하나 수정',
            'description' => '검토 소개', 'agency' => '검토 소속사', 'position' => '검토 포지션',
        ]);
        $this->assertSame('원래 소개', DB::table('idol_registration_request_localizations')
            ->where('language_code', 'ko')->where('text_role', 'submitted')->value('description'));
        $this->get('/idol/'.$idol->idx)->assertOk()
            ->assertSee('HANA EDITED')->assertSee('검토 소개')->assertSee('검토 소속사')->assertSee('검토 포지션');
    }

    public function test_legacy_request_without_localizations_still_approves_with_english_localization(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'Legacy Name', 'english_name' => 'Legacy English', 'content' => 'Legacy description',
        ])->assertRedirect('/admin/idol-requests/12');

        $idolIdx = (int) DB::table('idol_registration_requests')->where('idx', 12)->value('approved_idol_idx');
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'en', 'localized_name' => 'Legacy English',
        ]);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'text_role' => 'reviewed', 'language_code' => 'en',
            'localized_name' => 'Legacy English',
        ]);
    }

    public function test_explicit_basic_name_edits_are_saved_as_reviewed_without_changing_submitted_rows(): void
    {
        $this->database();
        $submittedEnglish = 'Original English';
        $submittedKorean = '원래 한국 이름';
        DB::table('idol_registration_request_localizations')->insert([
            ['request_idx' => 12, 'language_code' => 'en', 'text_role' => 'submitted', 'localized_name' => $submittedEnglish, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
            ['request_idx' => 12, 'language_code' => 'ko', 'text_role' => 'submitted', 'localized_name' => $submittedKorean, 'description' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => '검토 한국 이름', 'english_name' => 'Reviewed Canonical', 'content' => 'Introduction',
        ])->assertRedirect('/admin/idol-requests/12');

        $idolIdx = (int) DB::table('idol_registration_requests')->where('idx', 12)->value('approved_idol_idx');
        $this->assertSame('Reviewed Canonical', DB::table('idols')->where('idx', $idolIdx)->value('english_name'));
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'en', 'localized_name' => 'Reviewed Canonical',
        ]);
        $this->assertDatabaseHas('idol_localizations', [
            'idol_idx' => $idolIdx, 'language_code' => 'ko', 'localized_name' => '검토 한국 이름',
        ]);
        $this->assertSame($submittedEnglish, DB::table('idol_registration_request_localizations')
            ->where('text_role', 'submitted')->where('language_code', 'en')->value('localized_name'));
        $this->assertSame($submittedKorean, DB::table('idol_registration_request_localizations')
            ->where('text_role', 'submitted')->where('language_code', 'ko')->value('localized_name'));
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'text_role' => 'reviewed', 'language_code' => 'en',
            'localized_name' => 'Reviewed Canonical',
        ]);
        $this->assertDatabaseHas('idol_registration_request_localizations', [
            'request_idx' => 12, 'text_role' => 'reviewed', 'language_code' => 'ko',
            'localized_name' => '검토 한국 이름',
        ]);
    }

    public function test_reviewed_localizations_reject_unsupported_languages_and_oversized_fields(): void
    {
        $this->database();
        $payload = [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
            'reviewed_localizations' => ['ja' => ['localized_name' => '花', 'description' => 'text']],
        ];

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/save', $payload)
            ->assertSessionHasErrors('reviewed_localizations');
        $payload['reviewed_localizations'] = ['en' => ['localized_name' => str_repeat('a', 201), 'description' => 'text']];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/save', $payload)
            ->assertSessionHasErrors('reviewed_localizations.en.localized_name');
        $this->assertSame(0, DB::table('idol_registration_request_localizations')->count());
    }

    public function test_approval_rolls_back_if_localization_storage_fails(): void
    {
        $this->database();
        DB::statement("CREATE TRIGGER fail_idol_localization BEFORE INSERT ON idol_localizations BEGIN SELECT RAISE(ABORT, 'localization insert failed'); END");

        $response = $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ]);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('pending', DB::table('idol_registration_requests')->where('idx', 12)->value('status'));
        $this->assertSame(0, DB::table('idols')->count());
        $this->assertSame(50, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_approval_uses_the_current_configured_reward_amount(): void
    {
        $this->database();
        DB::table('heart_reward_settings')->where('action_code', 'idol_registration_approved')
            ->update(['heart_amount' => 875]);

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ])->assertRedirect('/admin/idol-requests/12');

        $this->assertSame(925, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 2, 'amount' => 875, 'operation_key' => 'idol_registration_approved:12',
        ]);
    }

    public function test_reward_failure_rolls_back_approval_so_the_reward_can_be_retried(): void
    {
        $this->database();
        DB::statement('DROP TABLE heart_reward_settings');

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ])->assertServerError();

        $approved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('pending', $approved->status);
        $this->assertNull($approved->approved_idol_idx);
        $this->assertSame(0, DB::table('idols')->count());
        $this->assertSame(50, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_rejection_requires_and_retains_applicant_safe_reason(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/reject', ['rejection_reason' => '   '])
            ->assertSessionHasErrors('rejection_reason');

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/reject', [
            'rejection_reason' => '이미 등록된 아이돌입니다.',
        ])->assertRedirect('/admin/idol-requests/12');

        $saved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('rejected', $saved->status);
        $this->assertSame('이미 등록된 아이돌입니다.', $saved->rejection_reason);
        $this->assertNotNull($saved->reviewed_at);
        $this->assertSame(0, DB::table('idols')->count());
        $this->assertSame(1, DB::table('admin_activity_logs')->where('action', 'idol_request_rejected')->count());
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_admin_dashboard_uses_operational_data_and_marks_visitors_untracked(): void
    {
        $this->database();
        DB::table('heart_histories')->insert(['idx' => 1, 'member_idx' => 2, 'amount' => 25, 'reason' => 'event_reward', 'created_at' => now()]);

        $this->withSession(['member_idx' => 1])->get('/admin?period=today')
            ->assertOk()->assertSee('data-admin-visitor-chart', false);
    }

    public function test_regular_member_cannot_view_admin_request_list(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 2])->get('/admin/idol-requests')
            ->assertForbidden()->assertSee('접근 권한이 없습니다');
    }

    public function test_public_footer_shows_admin_entry_only_to_admin(): void
    {
        $this->database();

        $adminUrl = route('admin.index');
        $this->withSession(['member_idx' => 1])->get('/')->assertOk()->assertSee('href="'.$adminUrl.'"', false);
        $this->withSession(['member_idx' => 2])->get('/')->assertOk()->assertDontSee('href="'.$adminUrl.'"', false);
    }

    public function test_guest_is_sent_to_existing_sign_in_flow(): void
    {
        $this->database();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('admin.index').'"', false);

        $this->get('/admin')
            ->assertRedirect(route('home'))
            ->assertSessionHas('open_sign_in', true);
    }

    public function test_admin_approval_rejects_unsupported_english_name_characters(): void
    {
        $this->database();

        $this->from('/admin/idol-requests/12')->withSession(['member_idx' => 1])
            ->post('/admin/idol-requests/12/approve', [
                'english_name' => 'HANA!',
                'content' => 'Introduction',
            ])->assertSessionHasErrors('english_name');

        $this->assertSame(0, DB::table('idols')->count());
    }

    public function test_admin_canonical_edit_uses_the_shared_name_validation_and_duplicate_rules(): void
    {
        $this->database();
        DB::table('idols')->insert([
            ['idx' => 50, 'type' => 'individual', 'gender' => 'female', 'name' => 'Current', 'english_name' => 'Current', 'content' => 'Profile', 'status' => 'active'],
            ['idx' => 51, 'type' => 'individual', 'gender' => 'female', 'name' => 'ENHYPEN', 'english_name' => 'ENHYPEN', 'content' => 'Profile', 'status' => 'active'],
        ]);

        $this->from('/admin/idols/50/edit')->withSession(['member_idx' => 1])->put('/admin/idols/50', [
            'name' => 'Current', 'english_name' => 'Current!', 'content' => 'Profile', 'status' => 'active',
        ])->assertSessionHasErrors('english_name');

        $this->from('/admin/idols/50/edit')->withSession(['member_idx' => 1])->put('/admin/idols/50', [
            'name' => 'Current', 'english_name' => 'EN HY PEN', 'content' => 'Profile', 'status' => 'active',
        ])->assertSessionHasErrors('english_name');

        $this->assertSame('Current', DB::table('idols')->where('idx', 50)->value('english_name'));
    }
}
