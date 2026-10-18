<?php

namespace App\Http\Controllers;

use App\Rules\AllowedNickname;

use App\Support\MemberCreator;
use App\Support\HeartRewardManager;
use App\Support\Countries;
use App\Http\Controllers\Concerns\ValidatesReturnUrls;
use App\Mail\EmailVerificationCode;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    use ValidatesReturnUrls;

    private const EMAIL_VERIFICATION_SESSION_KEY = 'register_email_verification';

    public function showRegister(Request $request)
    {
        if ($request->attributes->get('currentMember') !== null) {
            return redirect()->route('my-page');
        }

        $referralCode = $request->session()->get(HeartRewardManager::REFERRAL_SESSION_KEY);
        $hasReferralInvitation = app(HeartRewardManager::class)->eligibleInviterIdx($referralCode) !== null;
        if (!$hasReferralInvitation) {
            $request->session()->forget(HeartRewardManager::REFERRAL_SESSION_KEY);
        }

        $verification = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);

        return view('auth.register', [
            'hasReferralInvitation' => $hasReferralInvitation,
            'emailVerification' => is_array($verification) ? [
                'email' => (string) ($verification['email'] ?? ''),
                'verified' => (bool) ($verification['verified'] ?? false),
                'expires_at' => (int) ($verification['expires_at'] ?? 0),
                'resend_available_at' => (int) ($verification['resend_available_at'] ?? 0),
            ] : null,
        ]);
    }

    public function sendVerificationCode(Request $request)
    {
        $email = $this->normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('members', 'email')],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.email_required'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $now = now()->timestamp;
        $current = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);
        if (is_array($current) && ($current['email'] ?? null) === $email
            && (int) ($current['resend_available_at'] ?? 0) > $now) {
            $retryAfter = (int) $current['resend_available_at'] - $now;

            return response()->json([
                'success' => false,
                'message' => "You can resend the code in {$retryAfter} seconds.",
                'retry_after' => $retryAfter,
            ], 429);
        }

        $limitKeys = $this->verificationRateLimitKeys($request, $email);
        foreach ($limitKeys as [$key, $maximum]) {
            if (RateLimiter::tooManyAttempts($key, $maximum)) {
                $retryAfter = RateLimiter::availableIn($key);

                return response()->json([
                    'success' => false,
                    'message' => __('messages.verification_rate_limit'),
                    'retry_after' => $retryAfter,
                ], 429);
            }
        }

        do {
            $code = (string) random_int(100000, 999999);
            $codeHash = $this->verificationCodeHash($email, $code);
        } while (is_array($current) && isset($current['code_hash']) && hash_equals((string) $current['code_hash'], $codeHash));
        try {
            Mail::to($email)->send(new EmailVerificationCode($code));
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => __('messages.verification_send_failed'),
            ], 503);
        }

        $expiresIn = (int) config('registration.email_verification.expires_seconds', 300);
        $resendAfter = (int) config('registration.email_verification.resend_after_seconds', 60);
        $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, [
            'email' => $email,
            'code_hash' => $codeHash,
            'expires_at' => $now + $expiresIn,
            'attempts' => 0,
            'verified' => false,
            'verified_at' => null,
            'sent_at' => $now,
            'resend_available_at' => $now + $resendAfter,
        ]);

        foreach ($limitKeys as [$key]) {
            RateLimiter::hit($key, 3600);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.verification_sent'),
            'email' => $email,
            'expires_at' => $now + $expiresIn,
            'resend_available_at' => $now + $resendAfter,
        ]);
    }

    public function verifyEmailCode(Request $request)
    {
        $email = $this->normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.verification_code_six_digits'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $state = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);
        if (!is_array($state) || ($state['email'] ?? null) !== $email) {
            return response()->json([
                'success' => false,
                'message' => __('messages.verification_request_first'),
            ], 422);
        }

        if (($state['verified'] ?? false) === true) {
            return response()->json(['success' => true, 'message' => __('messages.email_verified'), 'email' => $email]);
        }

        if ((int) ($state['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::EMAIL_VERIFICATION_SESSION_KEY);

            return response()->json([
                'success' => false,
                'message' => __('messages.verification_expired'),
            ], 422);
        }

        $maximumAttempts = (int) config('registration.email_verification.max_attempts', 5);
        $attempts = (int) ($state['attempts'] ?? 0) + 1;
        $matches = isset($state['code_hash']) && hash_equals(
            (string) $state['code_hash'],
            $this->verificationCodeHash($email, $request->string('code')->toString())
        );

        if (!$matches) {
            if ($attempts >= $maximumAttempts) {
                $request->session()->forget(self::EMAIL_VERIFICATION_SESSION_KEY);

                return response()->json([
                    'success' => false,
                    'message' => __('messages.verification_attempts_limit'),
                ], 422);
            }

            $state['attempts'] = $attempts;
            $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, $state);

            return response()->json([
                'success' => false,
                'message' => __('messages.verification_invalid'),
                'attempts_remaining' => $maximumAttempts - $attempts,
            ], 422);
        }

        $state['code_hash'] = null;
        $state['verified'] = true;
        $state['verified_at'] = now()->timestamp;
        $state['attempts'] = $attempts;
        $request->session()->put(self::EMAIL_VERIFICATION_SESSION_KEY, $state);

        return response()->json(['success' => true, 'message' => __('messages.email_verified'), 'email' => $email]);
    }

    public function resetEmailVerification(Request $request)
    {
        $request->session()->forget(self::EMAIL_VERIFICATION_SESSION_KEY);

        return response()->json(['success' => true]);
    }

    public function register(Request $request)
    {
        $request->merge([
            'email' => $this->normalizeEmail($request->input('email')),
            'nickname' => trim((string) $request->input('nickname')),
            'country_code' => strtoupper(trim((string) $request->input('country_code'))),
        ]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254', Rule::unique('members', 'email')],
            'password' => $this->passwordRules(),
            'password_confirmation' => ['required', 'string'],
            'nickname' => $this->nicknameRules(),
            'country_code' => ['required', 'string', Rule::in(Countries::codes())],
        ], [
            'password.max' => 'Password must be between 8 and 64 characters.',
            'password.min' => 'Password must be between 8 and 64 characters.',
            'password.regex' => 'Password must contain at least one letter and one number.',
            'password.confirmed' => 'Passwords do not match.',
            'nickname.min' => 'Nickname must be between 2 and 20 characters.',
            'nickname.max' => 'Nickname must be between 2 and 20 characters.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.registration_invalid'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $verification = $request->session()->get(self::EMAIL_VERIFICATION_SESSION_KEY);
        $email = $request->string('email')->toString();
        if (!is_array($verification) || ($verification['verified'] ?? false) !== true
            || !hash_equals((string) ($verification['email'] ?? ''), $email)) {
            return response()->json([
                'success' => false,
                'message' => __('messages.verify_email'),
                'errors' => ['email_verification' => ['Verify this email address before continuing.']],
            ], 422);
        }

        $heartRewards = app(HeartRewardManager::class);
        $inviterIdx = $heartRewards->eligibleInviterIdx($request->session()->get(HeartRewardManager::REFERRAL_SESSION_KEY));

        try {
            $memberIdx = DB::transaction(function () use ($request, $inviterIdx, $heartRewards) {
                $memberIdx = app(MemberCreator::class)->create([
                    'email' => $request->string('email')->toString(),
                    'password_hash' => Hash::make($request->string('password')->toString()),
                    'nickname' => $request->string('nickname')->toString(), 'member_level' => 1,
                    'is_dummy' => 0, 'status' => 'active',
                    'country_code' => $request->string('country_code')->toString(),
                    'referred_by_member_idx' => $inviterIdx,
                    'email_verified_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                app(HeartRewardManager::class)->award($memberIdx, 'signup', 'signup:'.$memberIdx);
                $heartRewards->awardReferral($memberIdx);

                return $memberIdx;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => __('messages.registration_failed'),
                ], 500);
            }

            $errors = [];
            if (DB::table('members')->where('email', $request->string('email')->toString())->exists()) {
                $errors['email'] = ['This email address is already registered.'];
            }
            if (DB::table('members')->where('nickname', $request->string('nickname')->toString())->exists()) {
                $errors['nickname'] = ['This nickname is already in use.'];
            }

            if ($errors === []) {
                report($exception);

                return response()->json([
                    'success' => false,
                    'message' => __('messages.registration_failed'),
                ], 500);
            }

            return response()->json([
                'success' => false,
                'message' => __('messages.duplicate_account_fields'),
                'errors' => $errors,
            ], 422);
        }

        $request->session()->regenerate();
        $request->session()->put('member_idx', $memberIdx);
        $request->session()->forget([self::EMAIL_VERIFICATION_SESSION_KEY, HeartRewardManager::REFERRAL_SESSION_KEY]);

        return response()->json([
            'success' => true,
            'message' => __('messages.account_created'),
            'redirect' => route('my-page'),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->merge(['email' => $this->normalizeEmail($request->input('email'))]);
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.email_password_required'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $member = DB::table('members')->where('email', $request->string('email')->toString())->first([
            'idx', 'password_hash', 'member_level', 'status', 'deleted_at',
        ]);

        $failureReason = match (true) {
            $member === null => 'member_not_found',
            $member->password_hash === null => 'password_unavailable',
            $member->member_level <= 0 => 'member_level_denied',
            $member->status !== 'active' => 'member_status_denied',
            $member->deleted_at !== null => 'member_deleted',
            !Hash::check($request->string('password')->toString(), $member->password_hash) => 'invalid_password',
            default => null,
        };

        if ($failureReason !== null) {
            $this->recordLogin($request, $member?->idx, 'failed', $failureReason);

            return response()->json([
                'success' => false,
                'message' => __('messages.login_failed'),
            ], 401);
        }

        DB::transaction(function () use ($request, $member) {
            DB::table('members')->where('idx', $member->idx)->update(['last_login_at' => now()]);
            $this->recordLogin($request, $member->idx, 'success');
        });

        $redirect = $this->safeReturnUrl($request, $request->input('return_url'))
            ?? $this->safeReturnUrl($request, $request->session()->pull('url.intended'))
            ?? '/';
        $request->session()->regenerate();
        $request->session()->put('member_idx', $member->idx);
        $request->session()->forget(HeartRewardManager::REFERRAL_SESSION_KEY);

        return response()->json([
            'success' => true,
            'message' => __('messages.login_success'),
            'redirect' => $redirect,
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function normalizeEmail(mixed $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function verificationCodeHash(string $email, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$code, (string) config('app.key'));
    }

    private function verificationRateLimitKeys(Request $request, string $email): array
    {
        $config = config('registration.email_verification.rate_limits', []);

        return [
            ['register-email:email:'.hash('sha256', $email), (int) ($config['email_per_hour'] ?? 5)],
            ['register-email:session:'.hash('sha256', $request->session()->getId()), (int) ($config['session_per_hour'] ?? 10)],
            ['register-email:ip:'.hash('sha256', (string) $request->ip()), (int) ($config['ip_per_hour'] ?? 20)],
        ];
    }

    private function passwordRules(): array
    {
        return [
            'required', 'string', 'confirmed', 'min:8', 'max:64', 'regex:/[A-Za-z]/', 'regex:/[0-9]/',
            function ($attribute, $value, $fail) {
                if (is_string($value) && config('hashing.driver', 'bcrypt') === 'bcrypt'
                    && (strlen($value) > 72 || str_contains($value, "\0"))) {
                    $fail('The password must fit within 72 bytes and contain no null characters.');
                }
            },
        ];
    }

    private function nicknameRules(): array
    {
        return ['bail', 'required', 'string', 'min:2', 'max:20', new AllowedNickname, Rule::unique('members', 'nickname')];
    }

    private function recordLogin(Request $request, ?int $memberIdx, string $result, ?string $failureReason = null): void
    {
        DB::table('member_login_histories')->insert([
            'member_idx' => $memberIdx,
            'login_type' => 'password',
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1024),
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }
}
