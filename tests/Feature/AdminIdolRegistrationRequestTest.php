<?php

namespace Tests\Feature;

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
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, title TEXT, media_type TEXT, image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
        DB::statement("CREATE TABLE heart_reward_settings (action_code TEXT PRIMARY KEY, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE fan_post_reports (idx INTEGER PRIMARY KEY, reason TEXT, status TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, gender TEXT, name TEXT, english_name TEXT, agency TEXT, debut_date TEXT, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, status TEXT, view_count INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, created_at TEXT, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY, member_idx INTEGER, actor_member_idx INTEGER, type TEXT, gender TEXT, name TEXT, english_name TEXT, group_idx INTEGER, birth_date TEXT, nationality TEXT, height_cm REAL, weight_kg REAL, blood_type TEXT, agency TEXT, debut_date TEXT, position TEXT, sns TEXT, image_path TEXT, content TEXT, reference_url TEXT, note TEXT, request_key TEXT, status TEXT, approved_idol_idx INTEGER, approved_fan_post_idx INTEGER, reviewed_by_member_idx INTEGER, reviewed_at TEXT, review_note TEXT, rejection_reason TEXT, created_at TEXT, updated_at TEXT)");
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

    public function test_admin_can_review_and_approve_request_once(): void
    {
        $this->database();

        $this->withSession(['member_idx' => 1])->get('/admin/idol-requests/12')
            ->assertOk()->assertSee('아이돌 등록 신청 상세')->assertSee('승인')->assertSee('반려')
            ->assertSee('승인 시 신청자에게 4,321 Heart가 지급됩니다.');

        $this->assertSame(0, DB::table('heart_histories')->count());

        $payload = [
            'name' => '하나 수정', 'english_name' => 'HANA EDITED', 'agency' => '테스트 엔터',
            'debut_date' => '2025-01-01', 'birth_date' => '2000-01-01', 'nationality' => 'KR',
            'height_cm' => 165, 'weight_kg' => 48, 'blood_type' => 'A', 'position' => '보컬',
            'sns' => 'https://example.test/hana', 'content' => '수정된 소개',
        ];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertRedirect('/admin/idol-requests/12')
            ->assertSessionHas('admin_success', '아이돌 등록 신청을 승인했습니다.');

        $saved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('approved', $saved->status);
        $this->assertNotNull($saved->approved_idol_idx);
        $this->assertSame(1, (int) $saved->reviewed_by_member_idx);
        $this->assertSame('하나 수정', DB::table('idols')->where('idx', $saved->approved_idol_idx)->value('name'));
        $this->assertSame(1, DB::table('admin_activity_logs')->where('action', 'idol_request_approved')->count());
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
    }

    public function test_group_approval_rewards_the_original_applicant_once(): void
    {
        $this->database();
        DB::table('idol_registration_requests')->where('idx', 12)->update([
            'type' => 'group', 'gender' => 'male', 'name' => 'Test Group', 'english_name' => 'Test Group',
        ]);

        $payload = ['name' => 'Test Group', 'english_name' => 'Test Group', 'content' => 'Group introduction'];
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertRedirect('/admin/idol-requests/12');

        $approved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('approved', $approved->status);
        $this->assertSame('group', DB::table('idols')->where('idx', $approved->approved_idol_idx)->value('type'));
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame(100, (int) DB::table('members')->where('idx', 1)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 2, 'amount' => 4321, 'reason' => 'idol_registration_approved',
            'operation_key' => 'idol_registration_approved:12',
        ]);
        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', $payload)
            ->assertSessionHasErrors('status');
        $this->assertSame(1, DB::table('heart_histories')->where('operation_key', 'idol_registration_approved:12')->count());
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
            'profile_image' => UploadedFile::fake()->image('idol.jpg'),
            'content' => 'A real request from the dummy member.', 'request_key' => $requestKey,
        ])->assertRedirect(route('idol-registration.create'));

        $this->assertDatabaseHas('idol_registration_requests', [
            'member_idx' => 3, 'actor_member_idx' => 1, 'status' => 'pending',
        ]);
        $requestIdx = (int) DB::table('idol_registration_requests')->where('member_idx', 3)->value('idx');
        $this->post('/admin/idol-requests/'.$requestIdx.'/approve', [
            'name' => 'Test Idol', 'english_name' => 'Test Idol', 'content' => 'Approved dummy request.',
        ])->assertRedirect('/admin/idol-requests/'.$requestIdx);
        $this->assertSame(4371, (int) DB::table('members')->where('idx', 3)->value('heart_balance'));
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => 3, 'actor_member_idx' => 1, 'amount' => 4321,
            'reason' => 'idol_registration_approved', 'operation_key' => 'idol_registration_approved:'.$requestIdx,
        ]);
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

    public function test_reward_failure_does_not_roll_back_an_approved_idol(): void
    {
        $this->database();
        DB::statement('DROP TABLE heart_reward_settings');

        $this->withSession(['member_idx' => 1])->post('/admin/idol-requests/12/approve', [
            'name' => 'HANA', 'english_name' => 'HANA', 'content' => 'Introduction',
        ])->assertRedirect('/admin/idol-requests/12');

        $approved = DB::table('idol_registration_requests')->where('idx', 12)->first();
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->approved_idol_idx);
        $this->assertSame(1, DB::table('idols')->count());
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
}
