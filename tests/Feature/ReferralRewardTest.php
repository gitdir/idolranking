<?php

namespace Tests\Feature;

use App\Support\HeartRewardManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Mockery;
use Tests\TestCase;

class ReferralRewardTest extends TestCase
{
    private const REFERRAL_CODE = 'InviterCode1';

    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE,
            member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active', country_code TEXT,
            profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, email_verified_at TEXT, last_login_at TEXT, password_changed_at TEXT,
            created_by_member_idx INTEGER, referred_by_member_idx INTEGER, deleted_at TEXT, created_at TEXT, updated_at TEXT
        )");
        DB::statement("CREATE TABLE heart_reward_settings (
            action_code TEXT PRIMARY KEY, heart_amount INTEGER, daily_limit INTEGER, is_enabled INTEGER
        )");
        DB::statement("CREATE TABLE heart_histories (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT,
            vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER,
            memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key)
        )");
        DB::statement("CREATE TABLE member_social_accounts (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, provider TEXT, provider_user_id TEXT,
            created_at TEXT, updated_at TEXT, UNIQUE(provider, provider_user_id), UNIQUE(member_idx, provider)
        )");
        DB::statement("CREATE TABLE member_login_histories (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, login_type TEXT, result TEXT,
            ip_address TEXT, user_agent TEXT, failure_reason TEXT, created_at TEXT
        )");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, name TEXT, english_name TEXT, content TEXT, gender TEXT, type TEXT, status TEXT, image_path TEXT, view_count INTEGER DEFAULT 0)");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement('CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER)');
        DB::statement("CREATE TABLE fan_posts (
            idx INTEGER PRIMARY KEY, member_idx INTEGER, idol_idx INTEGER, media_type TEXT, language_code TEXT, image_path TEXT,
            youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT, deleted_at TEXT, created_at TEXT
        )");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT, deleted_at TEXT)");
        DB::statement('CREATE TABLE partner_banners (idx INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, image_path TEXT, target_url TEXT, heart_reward INTEGER DEFAULT 10, reward_enabled INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, display_order INTEGER DEFAULT 0, starts_at TEXT, ends_at TEXT, created_at TEXT, updated_at TEXT)');

        DB::table('heart_reward_settings')->insert([
            ['action_code' => 'signup', 'heart_amount' => 5, 'daily_limit' => 0, 'is_enabled' => 1],
            ['action_code' => 'invite_friend', 'heart_amount' => 1250, 'daily_limit' => 0, 'is_enabled' => 1],
        ]);
    }

    private function inviter(array $changes = []): int
    {
        return DB::table('members')->insertGetId(array_replace([
            'member_code' => self::REFERRAL_CODE,
            'email' => 'inviter@example.test',
            'password_hash' => Hash::make('Strong-password1!'),
            'nickname' => 'inviter_member',
            'member_level' => 1,
            'is_dummy' => 0,
            'status' => 'active',
            'country_code' => 'KR',
            'heart_balance' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ], $changes));
    }

    private function verifiedEmailSession(string $email): array
    {
        return ['register_email_verification' => [
            'email' => $email, 'verified' => true, 'expires_at' => now()->addMinutes(5)->timestamp,
        ]];
    }

    private function emailRegistration(string $email = 'new.member@example.test', string $nickname = 'new_member'): array
    {
        return [
            'email' => $email, 'password' => 'Strong-password1!', 'password_confirmation' => 'Strong-password1!',
            'nickname' => $nickname, 'country_code' => 'US',
        ];
    }

    private function failInviteRewardWrites(): void
    {
        DB::statement("CREATE TRIGGER fail_invite_reward BEFORE INSERT ON heart_histories
            WHEN NEW.reason = 'invite_friend_reward'
            BEGIN SELECT RAISE(ABORT, 'simulated referral reward failure'); END");
    }

    private function assertReferralReward(int $inviterIdx, int $newMemberIdx): void
    {
        $this->assertDatabaseHas('heart_histories', [
            'member_idx' => $inviterIdx,
            'amount' => 1250,
            'reason' => HeartRewardManager::ACTIONS['invite_friend']['reason'],
            'operation_key' => 'referral_reward:'.$newMemberIdx,
            'actor_member_idx' => $newMemberIdx,
        ]);
        $this->assertSame(1270, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
    }

    public function test_referral_survives_navigation_and_email_signup_pays_inviter_once(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();

        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()->assertSee('Fan Posts')
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        $this->get('/get-hearts')->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        $this->get('/register')->assertOk()
            ->assertSee('You&#039;ve been invited to IdolRanking!', false)
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);

        $this->withSession($this->verifiedEmailSession('new.member@example.test') + [HeartRewardManager::REFERRAL_SESSION_KEY => self::REFERRAL_CODE])
            ->postJson('/register', $this->emailRegistration())
            ->assertCreated()->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);

        $newMember = DB::table('members')->where('email', 'new.member@example.test')->first();
        $this->assertNotNull($newMember);
        $this->assertSame($inviterIdx, (int) $newMember->referred_by_member_idx);
        $this->assertSame(5, (int) $newMember->heart_balance);
        $this->assertReferralReward($inviterIdx, (int) $newMember->idx);

        $this->assertSame(0, app(HeartRewardManager::class)->awardReferral((int) $newMember->idx));
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', $inviterIdx)
            ->where('reason', HeartRewardManager::ACTIONS['invite_friend']['reason'])->count());
        $this->assertSame(1270, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
    }

    public function test_referral_survives_a_failed_email_signup_without_rewarding_until_creation(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);

        $this->postJson('/register', $this->emailRegistration())
            ->assertUnprocessable()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        $this->assertSame(1, DB::table('members')->count());
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', $inviterIdx)->count());
    }

    public function test_direct_registration_visit_does_not_show_referral_invitation(): void
    {
        $this->database();

        $this->get('/register')->assertOk()->assertDontSee("You've been invited to IdolRanking!");
    }

    public function test_referral_survives_existing_email_login_without_paying_the_inviter(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        DB::table('members')->insert([
            'member_code' => 'ExistingCode1', 'email' => 'existing@example.test',
            'password_hash' => Hash::make('Existing-password1!'), 'nickname' => 'existing_member',
            'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'country_code' => 'US',
            'heart_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);

        $this->postJson('/login', ['email' => 'existing@example.test', 'password' => 'Existing-password1!'])
            ->assertOk()->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);
        $this->assertSame(0, DB::table('heart_histories')->where('member_idx', $inviterIdx)->count());
        $this->assertSame(20, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
    }

    public function test_logged_in_visitor_stays_on_home_without_creating_referral_attribution(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $memberIdx = DB::table('members')->insertGetId([
            'member_code' => 'LoggedInCode1', 'email' => 'logged.in@example.test', 'nickname' => 'logged_in_member',
            'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'country_code' => 'US',
            'heart_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['member_idx' => $memberIdx, HeartRewardManager::REFERRAL_SESSION_KEY => 'oldReferral1'])
            ->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas('member_idx', $memberIdx)
            ->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);
        $this->assertSame(20, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
        $this->assertSame(0, DB::table('heart_histories')->count());
    }

    public function test_finite_invite_daily_limit_is_applied_to_the_inviter_only(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        DB::table('heart_reward_settings')->where('action_code', 'invite_friend')->update(['daily_limit' => 1]);

        foreach ([['one@example.test', 'member_one'], ['two@example.test', 'member_two']] as [$email, $nickname]) {
            $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
                ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
            $this->withSession($this->verifiedEmailSession($email))->postJson('/register', $this->emailRegistration($email, $nickname))
                ->assertCreated();
            $this->assertSame($inviterIdx, (int) DB::table('members')->where('email', $email)->value('referred_by_member_idx'));
            $this->post('/logout')->assertRedirect('/');
        }

        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', $inviterIdx)
            ->where('reason', HeartRewardManager::ACTIONS['invite_friend']['reason'])->count());
        $this->assertSame(1270, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
        $this->assertSame(3, DB::table('members')->count());
    }

    public function test_referral_survives_google_signup_and_existing_google_login_does_not_reward(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);

        $google = (new User)->setRaw([
            'id' => 'new-google-sub', 'name' => 'Google New', 'email' => 'google.new@example.test', 'verified_email' => true,
        ])->map(['id' => 'new-google-sub', 'name' => 'Google New', 'email' => 'google.new@example.test', 'verified_email' => true]);
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')->once()->with(['openid', 'email', 'profile'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/oauth'));
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->twice()->with('google')->andReturn($provider);
        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/oauth');
        $this->get('/auth/google/callback')->assertRedirect('/auth/google/complete');

        $this->post('/auth/google/complete', ['nickname' => 'google_new', 'country_code' => 'US'])
            ->assertRedirect('/')->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);
        $newMember = DB::table('members')->where('email', 'google.new@example.test')->first();
        $this->assertSame($inviterIdx, (int) $newMember->referred_by_member_idx);
        $this->assertReferralReward($inviterIdx, (int) $newMember->idx);
        $this->post('/logout')->assertRedirect('/');

        $existingIdx = DB::table('members')->insertGetId([
            'member_code' => 'ExistingCode1', 'email' => 'existing.google@example.test', 'nickname' => 'existing_google',
            'member_level' => 1, 'is_dummy' => 0, 'status' => 'active', 'country_code' => 'US',
            'heart_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('member_social_accounts')->insert([
            'member_idx' => $existingIdx, 'provider' => 'google', 'provider_user_id' => 'existing-google-sub',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $existingGoogle = (new User)->setRaw([
            'id' => 'existing-google-sub', 'name' => 'Existing Google', 'email' => 'existing.google@example.test', 'verified_email' => true,
        ])->map(['id' => 'existing-google-sub', 'name' => 'Existing Google', 'email' => 'existing.google@example.test', 'verified_email' => true]);
        $existingProvider = Mockery::mock();
        $existingProvider->shouldReceive('user')->once()->andReturn($existingGoogle);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($existingProvider);

        $this->withSession([HeartRewardManager::REFERRAL_SESSION_KEY => self::REFERRAL_CODE])
            ->get('/auth/google/callback')->assertSessionHas('member_idx', $existingIdx)
            ->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);
        $this->assertSame(1, DB::table('heart_histories')->where('member_idx', $inviterIdx)
            ->where('reason', HeartRewardManager::ACTIONS['invite_friend']['reason'])->count());
    }

    public function test_invalid_referral_and_disabled_reward_do_not_break_signup(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $this->get('/?ref=invalid-code')->assertOk()
            ->assertSessionMissing(HeartRewardManager::REFERRAL_SESSION_KEY);
        $this->assertSame($inviterIdx, app(HeartRewardManager::class)->eligibleInviterIdx(self::REFERRAL_CODE));
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        DB::table('heart_reward_settings')->where('action_code', 'invite_friend')->update(['is_enabled' => 0]);

        $this->withSession($this->verifiedEmailSession('new.member@example.test') + [HeartRewardManager::REFERRAL_SESSION_KEY => self::REFERRAL_CODE])
            ->postJson('/register', $this->emailRegistration())->assertCreated();
        $newMember = DB::table('members')->where('email', 'new.member@example.test')->first();
        $this->assertSame(1, (int) $newMember->referred_by_member_idx);
        $this->assertSame(0, DB::table('heart_histories')->where('reason', HeartRewardManager::ACTIONS['invite_friend']['reason'])->count());
    }

    public function test_admin_and_dummy_accounts_cannot_receive_referral_rewards(): void
    {
        $this->database();
        $adminIdx = $this->inviter([
            'member_code' => 'AdminInviter01', 'email' => 'admin.inviter@example.test', 'nickname' => 'admin_inviter',
            'member_level' => 10,
        ]);
        $dummyIdx = $this->inviter([
            'member_code' => 'DummyInviter01', 'email' => 'dummy.inviter@example.test', 'nickname' => 'dummy_inviter',
            'is_dummy' => 1,
        ]);

        $this->assertNull(app(HeartRewardManager::class)->eligibleInviterIdx('AdminInviter01'));
        $this->assertNull(app(HeartRewardManager::class)->eligibleInviterIdx('DummyInviter01'));

        foreach ([['admin-referred@example.test', $adminIdx], ['dummy-referred@example.test', $dummyIdx]] as [$email, $inviterIdx]) {
            $newMemberIdx = DB::table('members')->insertGetId([
                'member_code' => 'NewMember'.DB::table('members')->count(), 'email' => $email,
                'nickname' => 'new_member_'.DB::table('members')->count(), 'member_level' => 1,
                'is_dummy' => 0, 'status' => 'active', 'referred_by_member_idx' => $inviterIdx,
                'heart_balance' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->assertSame(0, app(HeartRewardManager::class)->awardReferral($newMemberIdx));
        }

        $this->assertSame(0, DB::table('heart_histories')->where('reason', HeartRewardManager::ACTIONS['invite_friend']['reason'])->count());
        $this->assertSame(20, (int) DB::table('members')->where('idx', $adminIdx)->value('heart_balance'));
        $this->assertSame(20, (int) DB::table('members')->where('idx', $dummyIdx)->value('heart_balance'));
    }

    public function test_email_signup_rolls_back_instead_of_succeeding_without_referral_reward(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        $this->failInviteRewardWrites();

        $response = $this->withSession($this->verifiedEmailSession('new.member@example.test') + [
            HeartRewardManager::REFERRAL_SESSION_KEY => self::REFERRAL_CODE,
        ])->postJson('/register', $this->emailRegistration());
        $response->assertServerError()->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);

        $this->assertDatabaseMissing('members', ['email' => 'new.member@example.test']);
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(20, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
    }

    public function test_google_signup_rolls_back_and_keeps_signup_state_when_referral_reward_fails(): void
    {
        $this->database();
        $inviterIdx = $this->inviter();
        $this->get('/?ref='.self::REFERRAL_CODE)->assertOk()
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE);
        $google = (new User)->setRaw([
            'id' => 'failing-google-sub', 'name' => 'Google New',
            'email' => 'google.failure@example.test', 'verified_email' => true,
        ])->map([
            'id' => 'failing-google-sub', 'name' => 'Google New',
            'email' => 'google.failure@example.test', 'verified_email' => true,
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')->once()->with(['openid', 'email', 'profile'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/oauth'));
        $provider->shouldReceive('user')->once()->andReturn($google);
        Socialite::shouldReceive('driver')->twice()->with('google')->andReturn($provider);
        $this->get('/auth/google')->assertRedirect('https://accounts.google.com/oauth');
        $this->get('/auth/google/callback')->assertRedirect('/auth/google/complete');
        $this->failInviteRewardWrites();

        $this->post('/auth/google/complete', ['nickname' => 'google_failure', 'country_code' => 'US'])
            ->assertRedirect()
            ->assertSessionHasErrors('registration')
            ->assertSessionHas(HeartRewardManager::REFERRAL_SESSION_KEY, self::REFERRAL_CODE)
            ->assertSessionHas('google_signup');

        $this->assertDatabaseMissing('members', ['email' => 'google.failure@example.test']);
        $this->assertSame(0, DB::table('heart_histories')->count());
        $this->assertSame(20, (int) DB::table('members')->where('idx', $inviterIdx)->value('heart_balance'));
    }
}
