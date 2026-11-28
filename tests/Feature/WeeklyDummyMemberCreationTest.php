<?php

namespace Tests\Feature;

use App\Support\Countries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WeeklyDummyMemberCreationTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement('CREATE TABLE members (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE, member_level INTEGER, is_dummy INTEGER, status TEXT, country_code TEXT, email_verified_at TEXT, created_at TEXT, updated_at TEXT)');
        DB::statement('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)');
        DB::statement('CREATE TABLE weekly_dummy_member_creations (idx INTEGER PRIMARY KEY AUTOINCREMENT, week_start_date TEXT UNIQUE, member_idx INTEGER UNIQUE, created_at TEXT)');
    }

    public function test_setting_and_week_key_allow_exactly_one_account_per_seoul_week(): void
    {
        $this->database();
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));

        Artisan::call('dummy-members:create-weekly');
        $this->assertDatabaseCount('members', 0);

        DB::table('site_settings')->insert(['setting_key' => 'dummy_auto_create_enabled', 'setting_value' => '1']);
        Artisan::call('dummy-members:create-weekly');
        Artisan::call('dummy-members:create-weekly');
        $this->assertDatabaseCount('members', 1);
        $this->assertDatabaseHas('weekly_dummy_member_creations', ['week_start_date' => '2026-11-30']);

        $member = DB::table('members')->first();
        $this->assertSame(1, (int) $member->member_level);
        $this->assertSame(1, (int) $member->is_dummy);
        $this->assertSame('active', $member->status);
        $this->assertContains($member->country_code, Countries::codes());
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $member->member_code);
        $this->assertStringEndsWith('@dummy.idolranking.invalid', $member->email);
        $this->assertStringStartsWith('운영더미', $member->nickname);
        $this->assertNotNull($member->email_verified_at);
        $this->assertDatabaseHas('weekly_dummy_member_creations', ['member_idx' => $member->idx]);

        $this->travelTo(Carbon::parse('2026-12-07 04:00:00', 'Asia/Seoul'));
        Artisan::call('dummy-members:create-weekly');
        $this->assertDatabaseCount('members', 2);
    }

    public function test_failed_creation_does_not_claim_the_week(): void
    {
        $this->database();
        $this->travelTo(Carbon::parse('2026-11-30 04:00:00', 'Asia/Seoul'));
        DB::table('site_settings')->insert(['setting_key' => 'dummy_auto_create_enabled', 'setting_value' => '1']);
        DB::table('members')->insert(['member_code' => 'Existing0001', 'email' => 'weekly-20261130@dummy.idolranking.invalid']);

        try {
            Artisan::call('dummy-members:create-weekly');
            $this->fail('Expected the duplicate synthetic email to reject the creation.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertDatabaseCount('weekly_dummy_member_creations', 0);
        }

        DB::table('members')->where('member_code', 'Existing0001')->delete();
        Artisan::call('dummy-members:create-weekly');
        $this->assertDatabaseCount('weekly_dummy_member_creations', 1);
        $this->assertDatabaseCount('members', 1);
    }
}
