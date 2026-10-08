<?php

namespace Tests\Feature;

use App\Support\MemberCreator;
use App\Mail\EmailVerificationCode;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class EmailAuthTest extends TestCase
{
    public function test_password_and_nickname_rules_accept_and_reject_confirmed_boundaries(): void
    {
        $controller = new AuthController;
        $passwordRules = (new \ReflectionMethod($controller, 'passwordRules'))->invoke($controller);
        $nicknameRules = array_values(array_filter(
            (new \ReflectionMethod($controller, 'nicknameRules'))->invoke($controller),
            fn ($rule) => is_string($rule)
        ));

        foreach (['abcd1234', 'Test123456', 'abcd1234!'] as $password) {
            $this->assertFalse(Validator::make([
                'password' => $password, 'password_confirmation' => $password,
            ], ['password' => $passwordRules])->fails(), $password);
        }

        foreach (['12345678', 'abcdefgh', 'abc123', str_repeat('a', 64).'1'] as $password) {
            $this->assertTrue(Validator::make([
                'password' => $password, 'password_confirmation' => $password,
            ], ['password' => $passwordRules])->fails(), $password);
        }
        $this->assertTrue(Validator::make([
            'password' => 'abcd1234', 'password_confirmation' => 'Different123',
        ], ['password' => $passwordRules])->fails());

        foreach (['ab', str_repeat('n', 20), '민지팬', 'さくらファン', 'golden_army'] as $nickname) {
            $this->assertFalse(Validator::make(['nickname' => $nickname], ['nickname' => $nicknameRules])->fails(), $nickname);
        }
        foreach (['a', str_repeat('n', 21)] as $nickname) {
            $this->assertTrue(Validator::make(['nickname' => $nickname], ['nickname' => $nicknameRules])->fails(), $nickname);
        }
    }

    public function test_register_ui_exposes_email_verification_controls_and_mail_content(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Send Code')
            ->assertSee('Verification Code')
            ->assertSee(route('register.verification.send'), false)
            ->assertSee(route('register.verification.verify'), false)
            ->assertSee('maxlength="64"', false)
            ->assertSee('minlength="2" maxlength="20"', false)
            ->assertSee('8–64 characters with at least one letter and one number.');

        $mail = new EmailVerificationCode('483271');
        $this->assertSame('IDOL RANKING - Email Verification Code', $mail->envelope()->subject);
        $this->assertStringContainsString('483271', $mail->render());
        $this->assertSame(300, config('registration.email_verification.expires_seconds'));
        $this->assertSame(5, config('registration.email_verification.max_attempts'));
    }

    private function database(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('The isolated database tests require pdo_sqlite.');
        }

        DB::statement("CREATE TABLE members (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_code TEXT UNIQUE, email TEXT UNIQUE, password_hash TEXT, nickname TEXT UNIQUE,
            member_level INTEGER DEFAULT 1, is_dummy INTEGER DEFAULT 0, status TEXT DEFAULT 'active',
            country_code TEXT, profile_image_path TEXT, heart_balance INTEGER DEFAULT 0, email_verified_at TEXT, last_login_at TEXT,
            password_changed_at TEXT, created_by_member_idx INTEGER, referred_by_member_idx INTEGER,
            deleted_at TEXT, created_at TEXT, updated_at TEXT
        )");
        DB::statement("CREATE TABLE member_login_histories (
            idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, login_type TEXT, result TEXT,
            ip_address TEXT, user_agent TEXT, failure_reason TEXT, created_at TEXT
        )");
        DB::statement("CREATE TABLE heart_reward_settings (idx INTEGER PRIMARY KEY AUTOINCREMENT, action_code TEXT UNIQUE, heart_amount INTEGER, daily_limit INTEGER DEFAULT 0, is_enabled INTEGER DEFAULT 1)");
        DB::statement("CREATE TABLE heart_histories (idx INTEGER PRIMARY KEY AUTOINCREMENT, member_idx INTEGER, amount INTEGER, balance_after INTEGER, reason TEXT, vote_idx INTEGER, comment_idx INTEGER, reversal_of_history_idx INTEGER, operation_key TEXT, actor_member_idx INTEGER, memo TEXT, created_at TEXT, UNIQUE (member_idx, operation_key))");
        DB::statement("CREATE TABLE idols (idx INTEGER PRIMARY KEY, type TEXT, gender TEXT, status TEXT DEFAULT 'active')");
        DB::statement("CREATE TABLE idol_group_members (individual_idx INTEGER, group_idx INTEGER, PRIMARY KEY (individual_idx, group_idx))");
        DB::statement("CREATE TABLE idol_votes (idx INTEGER PRIMARY KEY, idol_idx INTEGER, heart_amount INTEGER, created_at TEXT)");
        DB::statement("CREATE TABLE fan_posts (idx INTEGER PRIMARY KEY, idol_idx INTEGER, member_idx INTEGER, media_type TEXT DEFAULT 'text', image_path TEXT, youtube_video_id TEXT, content TEXT, view_count INTEGER DEFAULT 0, status TEXT DEFAULT 'published', deleted_at TEXT, created_at TEXT)");
        DB::statement('CREATE TABLE fan_post_likes (fan_post_idx INTEGER, member_idx INTEGER)');
        DB::statement("CREATE TABLE fan_post_comments (idx INTEGER PRIMARY KEY, fan_post_idx INTEGER, status TEXT DEFAULT 'published', deleted_at TEXT)");
    }

    private function registration(array $changes = []): array
    {
        return array_replace([
            'email' => 'New.Member@Example.test ',
            'password' => 'Strong-password1!',
            'password_confirmation' => 'Strong-password1!',
            'nickname' => ' new_member ',
            'country_code' => 'KR',
        ], $changes);
    }

    private function member(array $changes = []): int
    {
        return DB::table('members')->insertGetId(array_replace([
            'email' => 'member@example.test',
            'password_hash' => Hash::make('Strong-password1!'),
            'nickname' => 'member_one',
            'member_level' => 1,
            'is_dummy' => 0,
            'status' => 'active',
            'country_code' => 'US',
            'heart_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $changes));
    }

    public function test_shared_member_creator_assigns_code_to_admin_created_dummy_member(): void
    {
        $this->database();

        $idx = app(MemberCreator::class)->create([
            'email' => 'dummy@example.test',
            'password_hash' => null,
            'nickname' => 'dummy_member',
            'member_level' => 1,
            'is_dummy' => 1,
            'status' => 'active',
            'country_code' => 'US',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $member = DB::table('members')->where('idx', $idx)->first();

        $this->assertSame(1, $member->is_dummy);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $member->member_code);
    }

    private function verifiedEmailState(string $email = 'new.member@example.test'): array
    {
        return [
            'register_email_verification' => [
                'email' => $email,
                'code_hash' => null,
                'expires_at' => now()->addMinutes(5)->timestamp,
                'attempts' => 1,
                'verified' => true,
                'verified_at' => now()->timestamp,
                'sent_at' => now()->timestamp,
                'resend_available_at' => now()->addMinute()->timestamp,
            ],
        ];
    }

    public function test_registration_hashes_password_normalizes_data_and_signs_member_in(): void
    {
        $this->database();

        $this->withSession($this->verifiedEmailState())
            ->postJson('/register', $this->registration())
            ->assertCreated()->assertJson(['success' => true])->assertSessionHas('member_idx')
            ->assertSessionMissing('register_email_verification');

        $member = DB::table('members')->first();
        $this->assertSame('new.member@example.test', $member->email);
        $this->assertSame('new_member', $member->nickname);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{12}$/', $member->member_code);
        $this->assertSame(1, $member->member_level);
        $this->assertSame(0, $member->is_dummy);
        $this->assertNotNull($member->email_verified_at);
        $this->assertTrue(Hash::check('Strong-password1!', $member->password_hash));
        $this->assertNotSame('Strong-password1!', $member->password_hash);
    }

    public function test_verification_code_is_sent_only_for_valid_available_email(): void
    {
        $this->database();
        $this->member();
        Mail::fake();

        $this->postJson('/register/email-verification/send', ['email' => 'not-an-email'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/register/email-verification/send', ['email' => ' MEMBER@EXAMPLE.TEST '])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        Mail::assertNothingSent();

        $this->postJson('/register/email-verification/send', ['email' => ' VERIFY@EXAMPLE.TEST '])
            ->assertOk()->assertJson(['success' => true, 'email' => 'verify@example.test'])
            ->assertSessionHas('register_email_verification', fn ($state) =>
                $state['email'] === 'verify@example.test'
                && strlen($state['code_hash']) === 64
                && $state['verified'] === false
                && $state['expires_at'] > now()->timestamp
            );
        Mail::assertSent(EmailVerificationCode::class, fn ($mail) => preg_match('/^\d{6}$/', $mail->code) === 1);
    }

    public function test_correct_code_verifies_email_and_wrong_or_expired_code_fails(): void
    {
        $this->database();
        Mail::fake();
        $code = null;

        $this->postJson('/register/email-verification/send', ['email' => 'verify@example.test'])->assertOk();
        Mail::assertSent(EmailVerificationCode::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return true;
        });

        $this->postJson('/register/email-verification/verify', ['email' => 'verify@example.test', 'code' => '000000'])
            ->assertUnprocessable()->assertJson(['attempts_remaining' => 4]);
        $this->postJson('/register/email-verification/verify', ['email' => 'verify@example.test', 'code' => $code])
            ->assertOk()->assertJson(['success' => true, 'message' => 'Email verified.'])
            ->assertSessionHas('register_email_verification', fn ($state) => $state['verified'] === true && $state['code_hash'] === null);

        $expired = [
            'email' => 'expired@example.test', 'code_hash' => hash_hmac('sha256', 'expired@example.test|123456', (string) config('app.key')),
            'expires_at' => now()->subSecond()->timestamp, 'attempts' => 0, 'verified' => false,
            'verified_at' => null, 'sent_at' => now()->subMinutes(6)->timestamp, 'resend_available_at' => now()->subMinutes(5)->timestamp,
        ];
        $this->withSession(['register_email_verification' => $expired])
            ->postJson('/register/email-verification/verify', ['email' => 'expired@example.test', 'code' => '123456'])
            ->assertUnprocessable()->assertSessionMissing('register_email_verification');
    }

    public function test_five_incorrect_attempts_invalidate_current_code(): void
    {
        $this->database();
        Mail::fake();
        $this->postJson('/register/email-verification/send', ['email' => 'attempts@example.test'])->assertOk();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/register/email-verification/verify', ['email' => 'attempts@example.test', 'code' => '000000'])
                ->assertUnprocessable()->assertSessionHas('register_email_verification');
        }
        $this->postJson('/register/email-verification/verify', ['email' => 'attempts@example.test', 'code' => '000000'])
            ->assertUnprocessable()->assertSessionMissing('register_email_verification');
    }

    public function test_resend_replaces_code_and_rate_limit_is_enforced(): void
    {
        $this->database();
        Mail::fake();
        RateLimiter::clear('register-email:email:'.hash('sha256', 'resend@example.test'));
        $this->postJson('/register/email-verification/send', ['email' => 'resend@example.test'])->assertOk();
        Mail::assertSent(EmailVerificationCode::class, 1);
        $state = session('register_email_verification');
        $state['resend_available_at'] = now()->subSecond()->timestamp;

        $this->withSession(['register_email_verification' => $state])
            ->postJson('/register/email-verification/send', ['email' => 'resend@example.test'])->assertOk();
        Mail::assertSent(EmailVerificationCode::class, 2);
        $sent = Mail::sent(EmailVerificationCode::class);
        $oldCode = $sent->first()->code;
        $newCode = $sent->last()->code;
        $this->assertNotSame($oldCode, $newCode);
        $this->postJson('/register/email-verification/verify', ['email' => 'resend@example.test', 'code' => $oldCode])->assertUnprocessable();
        $this->postJson('/register/email-verification/verify', ['email' => 'resend@example.test', 'code' => $newCode])->assertOk();

        config(['registration.email_verification.rate_limits.email_per_hour' => 2]);
        $this->withSession(['register_email_verification' => array_replace(
            $this->verifiedEmailState('resend@example.test')['register_email_verification'],
            ['verified' => false, 'resend_available_at' => now()->subSecond()->timestamp]
        )])->postJson('/register/email-verification/send', ['email' => 'resend@example.test'])->assertStatus(429);
    }

    public function test_registration_requires_matching_verified_email_and_reset_invalidates_it(): void
    {
        $this->database();

        $this->postJson('/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email_verification');
        $this->withSession($this->verifiedEmailState('other@example.test'))
            ->postJson('/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email_verification');
        $this->withSession($this->verifiedEmailState())
            ->postJson('/register/email-verification/reset')
            ->assertOk()->assertSessionMissing('register_email_verification');
        $this->postJson('/register', $this->registration())
            ->assertUnprocessable()->assertJsonValidationErrors('email_verification');
    }

    public function test_registration_rejects_duplicates_confirmation_and_missing_country(): void
    {
        $this->database();
        $this->member();

        $this->postJson('/register', $this->registration(['email' => 'member@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/register', $this->registration(['nickname' => 'member_one']))
            ->assertUnprocessable()->assertJsonValidationErrors('nickname');
        $this->postJson('/register', $this->registration(['password_confirmation' => 'Different-password1!']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/register', $this->registration(['country_code' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('country_code');
        $this->postJson('/register', $this->registration(['country_code' => 'ZZ']))
            ->assertUnprocessable()->assertJsonValidationErrors('country_code');
    }

    public function test_registration_password_and_nickname_policy(): void
    {
        $this->database();

        foreach (['abcd1234', 'Test123456', 'abcd1234!'] as $index => $password) {
            $email = "password{$index}@example.test";
            $this->withSession($this->verifiedEmailState($email))->postJson('/register', $this->registration([
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'nickname' => 'valid_'.$index,
            ]))->assertCreated();
            $this->post('/logout')->assertRedirect('/');
        }

        foreach (['12345678', 'abcdefgh', 'abc123', str_repeat('a', 64).'1'] as $index => $password) {
            $email = "invalid{$index}@example.test";
            $this->withSession($this->verifiedEmailState($email))->postJson('/register', $this->registration([
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'nickname' => 'invalid_'.$index,
            ]))->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $this->withSession($this->verifiedEmailState())->postJson('/register', $this->registration([
            'password_confirmation' => 'Different123',
        ]))->assertUnprocessable()->assertJsonValidationErrors('password');

        foreach ([
            ['two@example.test', 'ab'],
            ['twenty@example.test', str_repeat('n', 20)],
            ['korean@example.test', '민지팬'],
            ['long.email.address.for.registration@example.test', 'global_fan'],
        ] as [$email, $nickname]) {
            $this->withSession($this->verifiedEmailState($email))->postJson('/register', $this->registration([
                'email' => $email, 'nickname' => $nickname,
            ]))->assertCreated();
            $this->post('/logout')->assertRedirect('/');
        }

        foreach ([['shortnick@example.test', 'a'], ['longnick@example.test', str_repeat('n', 21)]] as [$email, $nickname]) {
            $this->withSession($this->verifiedEmailState($email))->postJson('/register', $this->registration([
                'email' => $email, 'nickname' => $nickname,
            ]))->assertUnprocessable()->assertJsonValidationErrors('nickname');
        }
    }

    public function test_login_uses_password_hash_records_history_and_allows_protected_pages(): void
    {
        $this->database();
        $idx = $this->member();

        $this->postJson('/login', [
            'email' => ' MEMBER@EXAMPLE.TEST ',
            'password' => 'Strong-password1!',
            'return_url' => '/ranking/boy?period=monthly',
        ])->assertOk()->assertJson([
            'success' => true,
            'redirect' => '/ranking/boy?period=monthly',
        ])->assertSessionHas('member_idx', $idx);
        $this->get('/my-page')->assertOk()->assertSee('member@example.test');
        $this->post('/logout')->assertRedirect('/');
        $this->postJson('/login', [
            'email' => 'member@example.test',
            'password' => 'Strong-password1!',
            'return_url' => 'https://evil.example/phishing',
        ])->assertOk()->assertJson(['success' => true, 'redirect' => '/']);
        $this->assertNotNull(DB::table('members')->where('idx', $idx)->value('last_login_at'));
        $this->assertDatabaseHas('member_login_histories', ['member_idx' => $idx, 'result' => 'success', 'failure_reason' => null]);
    }

    public function test_invalid_password_unknown_email_inactive_and_passwordless_members_are_denied(): void
    {
        $this->database();
        $idx = $this->member();

        foreach ([
            ['member@example.test', 'wrong-password'],
            ['missing@example.test', 'Strong-password1!'],
        ] as [$email, $password]) {
            $this->postJson('/login', compact('email', 'password'))
                ->assertUnauthorized()->assertJson(['message' => 'Email or password is incorrect.'])->assertSessionMissing('member_idx');
        }

        DB::table('members')->where('idx', $idx)->update(['member_level' => 0]);
        $this->postJson('/login', ['email' => 'member@example.test', 'password' => 'Strong-password1!'])->assertUnauthorized();
        DB::table('members')->where('idx', $idx)->update(['member_level' => 1, 'password_hash' => null]);
        $this->postJson('/login', ['email' => 'member@example.test', 'password' => 'Strong-password1!'])->assertUnauthorized();
        $this->assertSame(4, DB::table('member_login_histories')->where('result', 'failed')->count());
    }

    public function test_guests_are_redirected_and_logout_invalidates_member_session(): void
    {
        $this->database();
        $this->get('/my-page')->assertRedirect('/')->assertSessionHas('open_sign_in');
        $this->get('/my-page/fan-posts')->assertRedirect('/');

        $idx = $this->member();
        $this->withSession(['member_idx' => $idx])->post('/logout')
            ->assertRedirect('/')->assertSessionMissing('member_idx');
        $this->get('/my-page')->assertRedirect('/');
    }

    public function test_return_url_validation_rejects_external_and_unsafe_urls(): void
    {
        $controller = new AuthController;
        $method = new \ReflectionMethod($controller, 'safeReturnUrl');
        $request = \Illuminate\Http\Request::create('http://idolranking.test/login', 'POST');

        $this->assertSame('/fan-post/42?sort=latest', $method->invoke($controller, $request, 'http://idolranking.test/fan-post/42?sort=latest'));
        $this->assertSame('/ranking/boy?period=monthly', $method->invoke($controller, $request, '/ranking/boy?period=monthly'));
        $this->assertNull($method->invoke($controller, $request, 'https://evil.example/phishing'));
        $this->assertNull($method->invoke($controller, $request, '//evil.example/phishing'));
        $this->assertNull($method->invoke($controller, $request, '/%5C%5Cevil.example/phishing'));
    }
}
