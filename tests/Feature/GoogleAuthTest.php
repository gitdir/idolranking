<?php

namespace Tests\Feature;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE,
            member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active',
            country_code TEXT, heart_balance INTEGER DEFAULT 0, email_verified_at TEXT, last_login_at TEXT,
            password_changed_at TEXT, created_by_member_idx INTEGER, referred_by_member_idx INTEGER,
            deleted_at TEXT, created_at TEXT, updated_at TEXT
        )");
        DB::statement("CREATE TABLE member_social_accounts (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, provider TEXT, provider_user_id TEXT,
            created_at TEXT, updated_at TEXT, UNIQUE(provider, provider_user_id), UNIQUE(member_idx, provider)
        )");
        DB::statement("CREATE TABLE member_login_histories (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, login_type TEXT, result TEXT,
            ip_address TEXT, user_agent TEXT, failure_reason TEXT, created_at TEXT
        )");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, status TEXT DEFAULT 'published', deleted_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER, created_at TEXT)');
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT DEFAULT 'published', deleted_at TEXT)");
        DB::statement("CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, operation_key TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
    }

    private function googleUser(array $changes = []): User
    {
        $attributes = array_replace([
            'id' => 'google-sub-123',
            'name' => 'Google Fan',
            'email' => 'google@example.test',
            'verified_email' => true,
        ], $changes);

        return (new User)->setRaw($attributes)->map($attributes);
    }

    private function mockGoogleUser(User $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($user);
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);
    }

    private function member(array $changes = []): int
    {
        return DB::table('members')->insertGetId(array_replace([
            'email' => 'google@example.test',
            'password_hash' => null,
            'nickname' => 'google_fan',
            'member_level' => 1,
            'is_dummy' => 0,
            'status' => 'active',
            'country_code' => 'KR',
            'created_at' => now(),
            'updated_at' => now(),
        ], $changes));
    }

    public function test_google_redirect_route_uses_socialite(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')->once()->with(['openid', 'email', 'profile'])->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->get('/auth/google?return_url='.urlencode('/fan-post/7?sort=latest'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionHas('auth.return_url', '/fan-post/7?sort=latest');
    }

    public function test_google_redirect_rejects_external_return_url(): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('scopes')->once()->andReturnSelf();
        $provider->shouldReceive('redirect')->once()->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->once()->with('google')->andReturn($provider);

        $this->withSession(['auth.return_url' => '/old-page'])
            ->get('/auth/google?return_url='.urlencode('https://evil.example/phishing'))
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth')
            ->assertSessionMissing('auth.return_url');
    }

    public function test_existing_linked_google_member_is_logged_in(): void
    {
        $this->database();
        $idx = $this->member();
        DB::table('member_social_accounts')->insert([
            'member_idx' => $idx, 'provider' => 'google', 'provider_user_id' => 'google-sub-123',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->mockGoogleUser($this->googleUser());

        $this->withSession(['auth.return_url' => '/ranking/boy?period=monthly'])
            ->get('/auth/google/callback')->assertRedirect('/ranking/boy?period=monthly')->assertSessionHas('member_idx', $idx);
        $this->assertDatabaseHas('member_login_histories', [
            'member_idx' => $idx, 'login_type' => 'google', 'result' => 'success', 'failure_reason' => null,
        ]);
        $this->assertNotNull(DB::table('members')->where('idx', $idx)->value('last_login_at'));
    }

    public function test_new_google_account_requires_completion(): void
    {
        $this->database();
        $this->mockGoogleUser($this->googleUser());

        $this->get('/auth/google/callback')->assertRedirect('/auth/google/complete')
            ->assertSessionHas('google_signup', fn ($state) =>
                $state['provider_id'] === 'google-sub-123'
                && $state['email'] === 'google@example.test'
                && !array_key_exists('token', $state)
            );
        $this->withSession(['google_signup' => [
            'provider_id' => 'google-sub-123', 'email' => 'google@example.test',
            'name' => 'Google Fan', 'expires_at' => now()->addMinutes(10)->timestamp,
        ]])->get('/auth/google/complete')->assertOk()->assertSee('Complete Sign Up')->assertSee('google@example.test');
    }

    public function test_completion_creates_google_only_member_and_logs_in(): void
    {
        $this->database();
        $session = ['auth.return_url' => '/fan-post/7?sort=latest', 'google_signup' => [
            'provider_id' => 'google-sub-123', 'email' => 'google@example.test',
            'name' => 'Google Fan', 'expires_at' => now()->addMinutes(10)->timestamp,
        ]];

        $response = $this->withSession($session)->post('/auth/google/complete', [
            'nickname' => 'global_fan', 'country_code' => 'KR', 'member_level' => 10, 'is_dummy' => 1,
        ]);

        $response->assertRedirect('/fan-post/7?sort=latest')->assertSessionHas('member_idx')->assertSessionMissing('google_signup');
        $member = DB::table('members')->where('email', 'google@example.test')->first();
        $this->assertNotNull($member);
        $this->assertNull($member->password_hash);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $member->member_code);
        $this->assertSame(1, $member->member_level);
        $this->assertSame(0, $member->is_dummy);
        $this->assertSame('active', $member->status);
        $this->assertSame('KR', $member->country_code);
        $this->assertDatabaseHas('member_social_accounts', [
            'member_idx' => $member->idx, 'provider' => 'google', 'provider_user_id' => 'google-sub-123',
        ]);
    }

    public function test_completion_validates_country_and_nickname_uniqueness(): void
    {
        $this->database();
        $this->member(['email' => 'existing@example.test', 'nickname' => 'taken_name']);
        $session = ['google_signup' => [
            'provider_id' => 'google-sub-123', 'email' => 'google@example.test',
            'name' => '', 'expires_at' => now()->addMinutes(10)->timestamp,
        ]];

        $this->withSession($session)->from('/auth/google/complete')->post('/auth/google/complete', [
            'nickname' => 'x', 'country_code' => '',
        ])->assertRedirect('/auth/google/complete')->assertSessionHasErrors(['nickname', 'country_code']);
        $this->withSession($session)->from('/auth/google/complete')->post('/auth/google/complete', [
            'nickname' => 'taken_name', 'country_code' => 'US',
        ])->assertSessionHasErrors('nickname');
        $this->withSession($session)->from('/auth/google/complete')->post('/auth/google/complete', [
            'nickname' => 'valid_name', 'country_code' => 'ZZ',
        ])->assertSessionHasErrors('country_code');
        $this->assertSame(1, DB::table('members')->count());
    }

    public function test_existing_email_collision_does_not_create_or_link_member(): void
    {
        $this->database();
        $this->member(['password_hash' => 'existing-password-hash']);
        $this->mockGoogleUser($this->googleUser());

        $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('auth_error');
        $this->assertSame(1, DB::table('members')->count());
        $this->assertSame(0, DB::table('member_social_accounts')->count());
    }

    public function test_missing_or_expired_signup_session_is_rejected(): void
    {
        $this->get('/auth/google/complete')->assertRedirect('/')->assertSessionHas('auth_error');
        $this->withSession(['google_signup' => [
            'provider_id' => 'google-sub-123', 'email' => 'google@example.test',
            'expires_at' => now()->subSecond()->timestamp,
        ]])->post('/auth/google/complete', ['nickname' => 'global_fan', 'country_code' => 'KR'])
            ->assertRedirect('/')->assertSessionHas('auth_error');
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->database();
        $this->mockGoogleUser($this->googleUser(['verified_email' => false]));

        $this->get('/auth/google/callback')->assertRedirect('/')->assertSessionHas('auth_error');
        $this->assertSame(0, DB::table('members')->count());
    }
}
