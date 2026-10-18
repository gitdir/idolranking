<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordResetController extends Controller
{
    public function show(Request $request)
    {
        return view('auth.reset-password', [
            'resetEmail' => $this->normalizeEmail($request->query('email')),
        ]);
    }

    public function sendLink(Request $request)
    {
        $email = $this->normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('ui.password_email_invalid'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $rateLimit = $this->requestRateLimit($request);
        if ($rateLimit !== null) {
            return $this->genericRequestResponse($request, 429, $rateLimit);
        }

        $member = DB::table('members')
            ->where('email', $email)
            ->where('member_level', '>', 0)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->first(['idx']);

        if ($member !== null) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token, true);
            $now = now();
            $tokenIdx = DB::transaction(function () use ($member, $tokenHash, $now, $request) {
                $lockedMember = DB::table('members')
                    ->where('idx', $member->idx)
                    ->where('member_level', '>', 0)
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first(['idx']);
                if ($lockedMember === null) {
                    return null;
                }

                DB::table('password_reset_tokens')
                    ->where('member_idx', $lockedMember->idx)
                    ->whereNull('used_at')
                    ->update(['used_at' => $now]);

                return DB::table('password_reset_tokens')->insertGetId([
                    'member_idx' => $lockedMember->idx,
                    'token_hash' => $tokenHash,
                    'expires_at' => $now->copy()->addMinutes((int) config('password_reset.expires_minutes', 60)),
                    'used_at' => null,
                    'requested_ip' => $request->ip(),
                    'created_at' => $now,
                ]);
            });

            if ($tokenIdx === null) {
                return $this->genericRequestResponse($request);
            }

            $resetUrl = route('password.reset', ['email' => $email]).'#token='.$token;
            $mailer = (string) config('mail.password_reset_mailer', 'smtp');
            try {
                // A log transport (including one nested in a failover mailer) would write the token URL to logs.
                if (!$this->mailerIsSafe($mailer)) {
                    throw new \RuntimeException('Password reset mail must use a non-logging mail transport.');
                }
                Mail::mailer($mailer)->to($email)->locale(app()->getLocale())->send(new PasswordResetLink($resetUrl));
            } catch (\Throwable $exception) {
                DB::table('password_reset_tokens')->where('idx', $tokenIdx)->update(['used_at' => now()]);
                Log::warning('Password reset email delivery failed.', ['exception_class' => $exception::class]);
            }
        }

        return $this->genericRequestResponse($request);
    }

    public function reset(Request $request)
    {
        $email = $this->normalizeEmail($request->input('email'));
        $request->merge(['email' => $email]);

        $rateLimit = $this->resetRateLimit($request);
        if ($rateLimit !== null) {
            return response()->json(['success' => false, 'message' => $rateLimit], 429);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:254'],
            'token' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'password' => [
                'required', 'string', 'confirmed', 'min:8', 'max:64',
                Password::min(8)->letters()->numbers()->symbols(),
                function ($attribute, $value, $fail) {
                    if (is_string($value) && config('hashing.driver', 'bcrypt') === 'bcrypt'
                        && (strlen($value) > 72 || str_contains($value, "\0"))) {
                        $fail(__('messages.password_byte_limit'));
                    }
                },
            ],
            'password_confirmation' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => __('ui.password_entries_invalid'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $tokenHash = hash('sha256', $request->string('token')->toString(), true);
        $result = DB::transaction(function () use ($email, $tokenHash, $request) {
            $resetToken = DB::table('password_reset_tokens as t')
                ->join('members as m', 'm.idx', '=', 't.member_idx')
                ->where('m.email', $email)
                ->where('m.member_level', '>', 0)
                ->where('m.status', 'active')
                ->whereNull('m.deleted_at')
                ->where('t.token_hash', $tokenHash)
                ->whereNull('t.used_at')
                ->where('t.expires_at', '>', now())
                ->lockForUpdate()
                ->first(['t.idx as token_idx', 't.member_idx']);

            if ($resetToken === null) {
                return false;
            }

            $claimed = DB::table('password_reset_tokens')
                ->where('idx', $resetToken->token_idx)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['used_at' => now()]);
            if ($claimed !== 1) {
                return false;
            }

            DB::table('members')->where('idx', $resetToken->member_idx)->update([
                'password_hash' => Hash::make($request->string('password')->toString()),
                'password_changed_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('password_reset_tokens')
                ->where('member_idx', $resetToken->member_idx)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            return true;
        });

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => __('ui.password_link_invalid'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('messages.password_reset_success'),
        ]);
    }

    private function genericRequestResponse(Request $request, int $status = 200, ?string $message = null)
    {
        $message ??= __('messages.password_reset_sent_generic');
        if ($request->expectsJson()) {
            return response()->json(['success' => $status === 200, 'message' => $message], $status);
        }

        return redirect()->route('password.forgot')->with('status', $message);
    }

    private function requestRateLimit(Request $request): ?string
    {
        $key = 'password-reset:request:ip:'.hash('sha256', (string) $request->ip());
        $maximum = (int) config('password_reset.rate_limits.ip_per_hour', 10);
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            return __('messages.password_reset_requests_limited');
        }
        RateLimiter::hit($key, 3600);

        return null;
    }

    private function resetRateLimit(Request $request): ?string
    {
        $key = 'password-reset:submit:ip:'.hash('sha256', (string) $request->ip());
        $maximum = (int) config('password_reset.rate_limits.submit_per_hour', 20);
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            return __('messages.password_reset_attempts_limited');
        }
        RateLimiter::hit($key, 3600);

        return null;
    }

    private function normalizeEmail(mixed $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    private function mailerIsSafe(string $mailer, array $checked = []): bool
    {
        if (in_array($mailer, $checked, true)) {
            return false;
        }
        $checked[] = $mailer;
        $settings = config('mail.mailers.'.$mailer);
        if (!is_array($settings) || ($settings['transport'] ?? null) === 'log') {
            return false;
        }
        foreach (($settings['mailers'] ?? []) as $fallbackMailer) {
            if (!$this->mailerIsSafe((string) $fallbackMailer, $checked)) {
                return false;
            }
        }

        return true;
    }
}
