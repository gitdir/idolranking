<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ManagedImageStorage;
use Tests\TestCase;

class IdolProfileImageReportTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('These isolated report tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, name TEXT, english_name TEXT, image_path TEXT, status TEXT DEFAULT 'active', updated_at TEXT)");
        DB::statement("CREATE TABLE idol_registration_requests (idx INTEGER PRIMARY KEY, approved_idol_idx INTEGER, image_path TEXT)");
        DB::statement("CREATE TABLE idol_profile_image_reports (idx INTEGER PRIMARY KEY AUTOINCREMENT, idol_idx INTEGER, member_idx INTEGER, actor_member_idx INTEGER, reported_image_path TEXT, image_path_hash TEXT, evidence_path TEXT, reason TEXT, details TEXT, status TEXT, moderated_by_member_idx INTEGER, moderated_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE(idol_idx, actor_member_idx, image_path_hash))");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");
        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminImage01', 'email' => 'admin@example.test', 'nickname' => 'Admin', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 10, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 2, 'member_code' => 'MemberImage02', 'email' => 'member@example.test', 'nickname' => 'Reporter', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 3, 'member_code' => 'MemberImage03', 'email' => 'other@example.test', 'nickname' => 'Other', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'deleted_at' => null],
            ['idx' => 4, 'member_code' => 'DummyImage04', 'email' => 'dummy@example.test', 'nickname' => 'Dummy', 'country_code' => 'KR', 'profile_image_path' => null, 'heart_balance' => 0, 'member_level' => 1, 'is_dummy' => 1, 'status' => 'active', 'deleted_at' => null],
        ]);
        DB::table('idols')->insert([
            'idx' => 10, 'type' => 'individual', 'name' => 'Test Idol', 'english_name' => 'Test Idol',
            'image_path' => 'storage/idol-profiles/test.png', 'status' => 'active',
        ]);
    }

    private function report(int $memberIdx = 2, string $reason = 'copyright'): int
    {
        $response = $this->withSession(['member_idx' => $memberIdx])->postJson(route('idol.profile-image.report', 10), [
            'reason' => $reason,
            'details' => $reason === 'other' ? 'Rights concern' : '',
            'image_path' => 'storage/attacker-chosen.png',
        ]);

        $response->assertCreated();

        return (int) DB::table('idol_profile_image_reports')->where('member_idx', $memberIdx)->orderByDesc('idx')->value('idx');
    }

    public function test_authenticated_report_uses_current_server_image_and_duplicate_is_rejected(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('idol-profiles/test.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pX8AAAAASUVORK5CYII='));

        $this->withSession(['member_idx' => 2])->postJson(route('idol.profile-image.report', 10), [
            'reason' => 'portrait_rights', 'image_path' => 'storage/attacker-chosen.png',
        ])->assertCreated();

        $report = DB::table('idol_profile_image_reports')->first();
        $this->assertSame('storage/idol-profiles/test.png', $report->reported_image_path);
        $this->assertSame(hash('sha256', 'storage/idol-profiles/test.png'), $report->image_path_hash);
        $this->assertSame(2, (int) $report->member_idx);
        $this->assertSame(2, (int) $report->actor_member_idx);
        $this->assertSame('pending', $report->status);

        $this->withSession(['member_idx' => 2])->postJson(route('idol.profile-image.report', 10), ['reason' => 'copyright'])
            ->assertStatus(409);
        $this->assertSame(1, DB::table('idol_profile_image_reports')->count());
        $this->assertSame('storage/idol-profiles/test.png', DB::table('idols')->where('idx', 10)->value('image_path'));
    }

    public function test_guest_cannot_report_and_invalid_reason_is_rejected(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('idol-profiles/test.png', 'image-bytes');

        $this->postJson(route('idol.profile-image.report', 10), ['reason' => 'copyright'])->assertUnauthorized();
        $this->withSession(['member_idx' => 2])->postJson(route('idol.profile-image.report', 10), ['reason' => 'spam'])
            ->assertUnprocessable();
        $this->assertSame(0, DB::table('idol_profile_image_reports')->count());
    }

    public function test_administrator_reporting_as_a_dummy_keeps_both_member_and_actor_attribution(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('idol-profiles/test.png', 'image-bytes');

        $this->withSession(['member_idx' => 1, 'acting_dummy_member_idx' => 4])
            ->postJson(route('idol.profile-image.report', 10), ['reason' => 'copyright'])->assertCreated();

        $report = DB::table('idol_profile_image_reports')->first();
        $this->assertSame(4, (int) $report->member_idx);
        $this->assertSame(1, (int) $report->actor_member_idx);
        $this->withSession(['member_idx' => 1])->postJson(route('idol.profile-image.report', 10), ['reason' => 'copyright'])
            ->assertStatus(409);
        $this->assertSame(1, DB::table('idol_profile_image_reports')->count());
    }

    public function test_admin_can_review_status_and_actions_are_audited(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('idol-profiles/test.png', 'image-bytes');
        $reportIdx = $this->report();

        $this->withSession(['member_idx' => 1])->get(route('admin.idol-profile-image-reports.index'))
            ->assertOk()->assertSee('프로필 이미지 권리 신고')->assertSee('Reporter');
        $this->withSession(['member_idx' => 1])->patch(route('admin.idol-profile-image-reports.status', $reportIdx), [
            'status' => 'reviewing', 'moderation_note' => '증빙 확인 중',
        ])->assertRedirect();

        $this->assertSame('reviewing', DB::table('idol_profile_image_reports')->where('idx', $reportIdx)->value('status'));
        $log = DB::table('admin_activity_logs')->first();
        $this->assertSame(1, (int) $log->actor_member_idx);
        $this->assertSame('idol_image_report_status_changed', $log->action);
    }

    public function test_admin_image_removal_quarantines_evidence_and_keeps_idol_active(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::fake('local');
        $imageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pX8AAAAASUVORK5CYII=');
        Storage::disk('public')->put('idol-profiles/test.png', $imageBytes);
        $reportIdx = $this->report();
        $secondReportIdx = $this->report(3, 'portrait_rights');

        $this->withSession(['member_idx' => 1])->patch(route('admin.idol-profile-image-reports.hide-image', $reportIdx), [
            'moderation_note' => '권리자 요청 확인',
        ])->assertRedirect();

        $idol = DB::table('idols')->where('idx', 10)->first();
        $report = DB::table('idol_profile_image_reports')->where('idx', $reportIdx)->first();
        $secondReport = DB::table('idol_profile_image_reports')->where('idx', $secondReportIdx)->first();
        $this->assertNull($idol->image_path);
        $this->assertSame('active', $idol->status);
        Storage::disk('public')->assertMissing('idol-profiles/test.png');
        $this->assertNotEmpty($report->evidence_path);
        $this->assertSame($report->evidence_path, $secondReport->evidence_path);
        Storage::disk('local')->assertExists($report->evidence_path);
        $this->assertSame('reviewed', $report->status);
        $this->assertSame(1, (int) $report->moderated_by_member_idx);
        $this->assertSame('idol_profile_image_removed_for_rights', DB::table('admin_activity_logs')->orderByDesc('idx')->value('action'));
        $this->assertSame(ManagedImageStorage::defaultUrl(), ManagedImageStorage::publicUrl($idol->image_path));

        $evidenceResponse = $this->withSession(['member_idx' => 1])->get(route('admin.idol-profile-image-reports.evidence', $reportIdx))->assertOk();
        $this->assertStringContainsString('no-store', $evidenceResponse->headers->get('Cache-Control'));
        $this->withSession(['member_idx' => 1])->get(route('admin.idol-profile-image-reports.evidence', $secondReportIdx))->assertOk();
        $this->withSession(['member_idx' => 2])->get(route('admin.idol-profile-image-reports.evidence', $reportIdx))->assertForbidden();
    }

    public function test_stale_or_shared_image_is_never_removed(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('idol-profiles/test.png', 'image-bytes');
        $reportIdx = $this->report();
        DB::table('idols')->where('idx', 10)->update(['image_path' => 'storage/idol-profiles/new.png']);

        $this->withSession(['member_idx' => 1])->patch(route('admin.idol-profile-image-reports.hide-image', $reportIdx), [
            'moderation_note' => '신고 이미지 확인',
        ])->assertRedirect()->assertSessionHasErrors('image');
        $this->assertSame('storage/idol-profiles/new.png', DB::table('idols')->where('idx', 10)->value('image_path'));
        Storage::disk('public')->assertExists('idol-profiles/test.png');

        DB::table('idols')->where('idx', 10)->update(['image_path' => 'storage/idol-profiles/test.png']);
        DB::table('idols')->insert(['idx' => 11, 'type' => 'group', 'name' => 'Shared', 'image_path' => 'storage/idol-profiles/test.png', 'status' => 'active']);
        $this->withSession(['member_idx' => 1])->patch(route('admin.idol-profile-image-reports.hide-image', $reportIdx), [
            'moderation_note' => '공유 파일 확인',
        ])->assertRedirect()->assertSessionHasErrors('image');
        $this->assertSame('storage/idol-profiles/test.png', DB::table('idols')->where('idx', 10)->value('image_path'));
        Storage::disk('public')->assertExists('idol-profiles/test.png');
    }

    public function test_non_admin_cannot_access_or_change_report_moderation(): void
    {
        $this->database();
        Storage::fake('public');
        Storage::disk('public')->put('idol-profiles/test.png', 'image-bytes');
        $reportIdx = $this->report();

        $this->withSession(['member_idx' => 2])->get(route('admin.idol-profile-image-reports.index'))->assertForbidden();
        $this->withSession(['member_idx' => 2])->patch(route('admin.idol-profile-image-reports.status', $reportIdx), [
            'status' => 'rejected',
        ])->assertForbidden();
        $this->assertSame('pending', DB::table('idol_profile_image_reports')->where('idx', $reportIdx)->value('status'));
    }
}
