<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT UNIQUE, password_hash TEXT,
            member_level INTEGER DEFAULT 1, status TEXT DEFAULT 'active', deleted_at TEXT,
            password_changed_at TEXT, updated_at TEXT
        )");
        DB::statement("CREATE TABLE password_reset_tokens (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, token_hash BLOB UNIQUE,
            expires_at TEXT, used_at TEXT NULL, requested_ip TEXT NULL, created_at TEXT
        )");
    }

    private function member(string $email = 'member@example.test'): int
    {
        return DB::table('members')->insertGetId([
            'email' => $email,
            'password_hash' => Hash::make('Old-password1!'),
            'member_level' => 1,
            'status' => 'active',
        ]);
    }

    private function issueToken(int $memberIdx, string $token, ?\Illuminate\Support\Carbon $expiresAt = null, ?string $usedAt = null): int
    {
        return DB::table('password_reset_tokens')->insertGetId([
            'member_idx' => $memberIdx,
            'token_hash' => hash('sha256', $token, true),
            'expires_at' => $expiresAt ?? now()->addHour(),
            'used_at' => $usedAt,
            'created_at' => now(),
        ]);
    }

    public function test_registered_email_receives_single_use_hashed_token_with_sixty_minute_expiry(): void
    {
        $this->database();
        $memberIdx = $this->member();
        Mail::fake();

        $this->postJson(route('password.email'), ['email' => ' MEMBER@EXAMPLE.TEST '])
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'If an account exists for that email address, a password reset link has been sent.',
            ]);

        $stored = DB::table('password_reset_tokens')->where('member_idx', $memberIdx)->first();
        $this->assertNotNull($stored);
        $this->assertSame(32, strlen($stored->token_hash));
        $this->assertSame(3600, \Illuminate\Support\Carbon::parse($stored->expires_at)->timestamp
            - \Illuminate\Support\Carbon::parse($stored->created_at)->timestamp);
        $this->assertNull($stored->used_at);

        $mail = Mail::sent(PasswordResetLink::class)->sole();
        $this->assertStringContainsString('email=member%40example.test', $mail->resetUrl);
        $this->assertMatchesRegularExpression('/#token=[a-f0-9]{64}$/', $mail->resetUrl);
        $token = substr(parse_url($mail->resetUrl, PHP_URL_FRAGMENT), strlen('token='));
        $this->assertSame(hash('sha256', $token, true), $stored->token_hash);
    }

    public function test_unknown_email_gets_same_generic_response_and_no_mail(): void
    {
        $this->database();
        Mail::fake();
        $this->member();

        $registered = $this->postJson(route('password.email'), ['email' => 'member@example.test']);
        $unknown = $this->postJson(route('password.email'), ['email' => 'unknown@example.test']);

        $this->assertSame($registered->getContent(), $unknown->getContent());
        $this->assertSame(200, $unknown->getStatusCode());
        $this->assertSame(1, Mail::sent(PasswordResetLink::class)->count());
        $this->assertSame(1, DB::table('password_reset_tokens')->count());
    }

    public function test_valid_token_changes_password_and_cannot_be_reused(): void
    {
        $this->database();
        $memberIdx = $this->member();
        $token = bin2hex(random_bytes(32));
        $tokenIdx = $this->issueToken($memberIdx, $token);
        $payload = [
            'email' => 'member@example.test',
            'token' => $token,
            'password' => 'New-password2!',
            'password_confirmation' => 'New-password2!',
        ];

        $this->postJson(route('password.update'), $payload)
            ->assertOk()
            ->assertJson(['success' => true]);

        $member = DB::table('members')->where('idx', $memberIdx)->first();
        $this->assertTrue(Hash::check('New-password2!', $member->password_hash));
        $this->assertNotNull($member->password_changed_at);
        $this->assertNotNull(DB::table('password_reset_tokens')->where('idx', $tokenIdx)->value('used_at'));

        $this->postJson(route('password.update'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This password reset link is invalid, expired, or has already been used. Please request a new one.');
    }

    public function test_invalid_and_expired_tokens_are_rejected_without_changing_password(): void
    {
        $this->database();
        $memberIdx = $this->member();
        $expiredToken = bin2hex(random_bytes(32));
        $this->issueToken($memberIdx, $expiredToken, now()->subSecond());
        $payload = [
            'email' => 'member@example.test', 'token' => $expiredToken,
            'password' => 'New-password2!', 'password_confirmation' => 'New-password2!',
        ];

        $this->postJson(route('password.update'), $payload)->assertUnprocessable();
        $this->postJson(route('password.update'), array_replace($payload, ['token' => str_repeat('a', 64)]))
            ->assertUnprocessable();
        $this->assertTrue(Hash::check('Old-password1!', DB::table('members')->where('idx', $memberIdx)->value('password_hash')));
    }

    public function test_password_reset_requests_are_throttled_without_disclosing_account_state(): void
    {
        $this->database();
        $this->member();
        Mail::fake();
        config(['password_reset.rate_limits.ip_per_hour' => 1]);
        $key = 'password-reset:request:ip:'.hash('sha256', '127.0.0.1');
        RateLimiter::clear($key);

        $this->postJson(route('password.email'), ['email' => 'member@example.test'])->assertOk();
        $this->postJson(route('password.email'), ['email' => 'member@example.test'])
            ->assertStatus(429)
            ->assertJson(['success' => false, 'message' => 'Too many password reset requests. Please try again later.']);

        $this->assertSame(1, Mail::sent(PasswordResetLink::class)->count());
    }

    public function test_reset_link_is_not_sent_through_the_log_mailer(): void
    {
        $this->database();
        $memberIdx = $this->member();
        Mail::fake();
        config(['mail.password_reset_mailer' => 'log']);

        $this->postJson(route('password.email'), ['email' => 'member@example.test'])->assertOk();

        Mail::assertNothingSent();
        $this->assertNotNull(DB::table('password_reset_tokens')->where('member_idx', $memberIdx)->value('used_at'));
    }

    public function test_reset_submission_attempts_are_throttled(): void
    {
        $this->database();
        $this->member();
        config(['password_reset.rate_limits.submit_per_hour' => 1]);
        RateLimiter::clear('password-reset:submit:ip:'.hash('sha256', '127.0.0.1'));
        $payload = [
            'email' => 'member@example.test', 'token' => str_repeat('a', 64),
            'password' => 'New-password2!', 'password_confirmation' => 'New-password2!',
        ];

        $this->postJson(route('password.update'), $payload)->assertUnprocessable();
        $this->postJson(route('password.update'), $payload)->assertStatus(429);
    }

    public function test_reset_page_preserves_existing_design_and_reads_email_parameter(): void
    {
        $this->get(route('password.reset', ['email' => 'member@example.test']))
            ->assertOk()
            ->assertSee('Reset Your Password')
            ->assertSee(route('password.update'), false)
            ->assertSee('member@example.test', false);
    }
}
