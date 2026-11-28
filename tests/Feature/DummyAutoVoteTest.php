<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DummyAutoVoteTest extends TestCase
{
    private function database(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (idx INTEGER PRIMARY KEY, member_code TEXT, email TEXT, nickname TEXT, country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', deleted_at TEXT, favorite_idol_1_idx INTEGER, favorite_idol_2_idx INTEGER, favorite_idol_3_idx INTEGER, created_by_member_idx INTEGER, created_at TEXT, updated_at TEXT)");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, type TEXT DEFAULT 'individual', status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, actor_member_idx INTEGER, idol_idx INTEGER, heart_amount INTEGER, country_code_at_vote TEXT, request_key TEXT, created_at TEXT, UNIQUE(member_idx, request_key))");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE(member_idx, operation_key))");
        DB::statement("CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT, updated_by_member_idx INTEGER, updated_at TEXT)");
        DB::statement("CREATE TABLE admin_activity_logs (idx INTEGER PRIMARY KEY AUTOINCREMENT, actor_member_idx INTEGER, action TEXT, target_type TEXT, target_idx INTEGER, reason TEXT, summary TEXT, created_at TEXT)");

        DB::table('members')->insert([
            ['idx' => 1, 'member_code' => 'AdminCode001', 'nickname' => 'Admin', 'member_level' => 10, 'is_dummy' => 0, 'favorite_idol_1_idx' => null],
            ['idx' => 2, 'member_code' => 'DummyCode001', 'nickname' => 'Dummy', 'member_level' => 1, 'is_dummy' => 1, 'favorite_idol_1_idx' => 10],
            ['idx' => 3, 'member_code' => 'DummyCode002', 'nickname' => 'NoFavorite', 'member_level' => 1, 'is_dummy' => 1, 'favorite_idol_1_idx' => null],
            ['idx' => 4, 'member_code' => 'MemberCode001', 'nickname' => 'Member', 'member_level' => 1, 'is_dummy' => 0, 'favorite_idol_1_idx' => 11],
        ]);
        DB::table('idols')->insert([
            ['idx' => 10, 'name' => 'Favorite', 'type' => 'individual', 'status' => 'active'],
            ['idx' => 11, 'name' => 'Other', 'type' => 'individual', 'status' => 'active'],
        ]);
    }

    public function test_enabled_dummy_without_favorite_votes_for_random_active_idol_once_per_interval(): void
    {
        $this->database();
        DB::table('members')->where('idx', 2)->update(['country_code' => 'KR']);
        DB::table('members')->where('idx', 2)->update(['favorite_idol_1_idx' => null]);
        DB::table('members')->where('idx', 3)->update(['status' => 'inactive']);
        DB::table('idols')->insert(['idx' => 12, 'name' => 'Inactive', 'type' => 'individual', 'status' => 'inactive']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 00:00:00', 'UTC'));

        Artisan::call('dummy-members:auto-vote');
        $this->assertSame(0, DB::table('idol_votes')->count());

        $this->withSession(['member_idx' => 1])->put(route('admin.settings.update', 'management'), [
            'fan_post_view_boost_enabled' => '0', 'dummy_auto_vote_enabled' => '1',
            'dummy_auto_vote_interval_minutes' => 5, 'dummy_auto_vote_min_hearts' => 10,
            'dummy_auto_vote_max_hearts' => 50,
        ])->assertRedirect();

        Artisan::call('dummy-members:auto-vote');
        $vote = DB::table('idol_votes')->first();
        $this->assertNotNull($vote);
        $this->assertSame(2, (int) $vote->member_idx);
        $this->assertSame('KR', $vote->country_code_at_vote);
        $this->assertContains((int) $vote->idol_idx, [10, 11]);
        $this->assertGreaterThanOrEqual(10, (int) $vote->heart_amount);
        $this->assertLessThanOrEqual(50, (int) $vote->heart_amount);
        $this->assertSame(0, (int) DB::table('members')->where('idx', 2)->value('heart_balance'));
        $this->assertSame([(int) $vote->heart_amount, -(int) $vote->heart_amount],
            DB::table('heart_histories')->where('member_idx', 2)->orderBy('idx')->pluck('amount')->all());

        Artisan::call('dummy-members:auto-vote');
        $this->assertSame(1, DB::table('idol_votes')->count());
        $this->travel(5)->minutes();
        Artisan::call('dummy-members:auto-vote');
        $this->assertSame(2, DB::table('idol_votes')->count());

        $this->withSession(['member_idx' => 1])->put(route('admin.settings.update', 'management'), [
            'fan_post_view_boost_enabled' => '0', 'dummy_auto_vote_enabled' => '0',
            'dummy_auto_vote_interval_minutes' => 5, 'dummy_auto_vote_min_hearts' => 10,
            'dummy_auto_vote_max_hearts' => 50,
        ])->assertRedirect();
        $this->travel(5)->minutes();
        Artisan::call('dummy-members:auto-vote');
        $this->assertSame(2, DB::table('idol_votes')->count());
    }

    public function test_only_admin_can_assign_distinct_active_favorites_to_dummy(): void
    {
        $this->database();
        $this->withSession(['member_idx' => 4])->put(route('admin.members.favorites', 2), [
            'favorite_idol_1_idx' => 11,
        ])->assertForbidden();
        $this->withSession(['member_idx' => 1])->put(route('admin.members.favorites', 2), [
            'favorite_idol_1_idx' => 10, 'favorite_idol_2_idx' => 10,
        ])->assertSessionHasErrors('favorite_idol_2_idx');
        $this->withSession(['member_idx' => 1])->put(route('admin.members.favorites', 4), [
            'favorite_idol_1_idx' => 10,
        ])->assertNotFound();
        $this->withSession(['member_idx' => 1])->put(route('admin.members.favorites', 2), [
            'favorite_idol_1_idx' => 10, 'favorite_idol_2_idx' => 11,
        ])->assertRedirect();
        $this->assertDatabaseHas('members', [
            'idx' => 2, 'favorite_idol_1_idx' => 10, 'favorite_idol_2_idx' => 11,
        ]);
    }
}
