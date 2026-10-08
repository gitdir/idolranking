<?php

namespace App\Http\Controllers;

use App\Rules\AllowedNickname;

use App\Support\MemberCreator;
use App\Support\HeartRewardManager;
use App\Support\Countries;
use App\Http\Controllers\Concerns\ValidatesReturnUrls;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleAuthController extends Controller
{
    use ValidatesReturnUrls;

    private const SIGNUP_SESSION_KEY = 'google_signup';
    private const RETURN_URL_SESSION_KEY = 'auth.return_url';
    private const SIGNUP_LIFETIME_SECONDS = 600;
    public function redirect(Request $request)
    {
        if ($request->attributes->get('currentMember') !== null) {
            return redirect()->route('my-page');
        }

        $returnUrl = $this->safeReturnUrl($request, $request->query('return_url'))
            ?? $this->safeReturnUrl($request, $request->session()->get('url.intended'));
        if ($returnUrl !== null) {
            $request->session()->put(self::RETURN_URL_SESSION_KEY, $returnUrl);
        } else {
            $request->session()->forget(self::RETURN_URL_SESSION_KEY);
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->filled('error')) {
            return $this->authenticationError($request, 'Google sign-in was cancelled or could not be completed.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            return $this->authenticationError($request, 'Your Google sign-in session expired. Please try again.');
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed.', ['exception' => $exception::class]);

            return $this->authenticationError($request, 'Google sign-in could not be completed. Please try again.');
        }

        $providerId = trim((string) $googleUser->getId());
        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $rawUser = is_array($googleUser->user ?? null) ? $googleUser->user : [];
        $emailVerified = ($rawUser['verified_email'] ?? $rawUser['email_verified'] ?? false) === true;

        if ($providerId === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->authenticationError($request, 'Google did not provide a valid email address.');
        }
        if (!$emailVerified) {
            return $this->authenticationError($request, 'Your Google email address must be verified before signing in.');
        }

        $linkedMember = DB::table('member_social_accounts as social')
            ->join('members as member', 'member.idx', '=', 'social.member_idx')
            ->where('social.provider', 'google')->where('social.provider_user_id', $providerId)
            ->first(['member.idx', 'member.email', 'member.member_level', 'member.status', 'member.deleted_at']);

        if ($linkedMember !== null) {
            if (mb_strtolower((string) $linkedMember->email) !== $email) {
                $this->recordLogin($request, $linkedMember->idx, 'failed', 'google_email_mismatch');

                return $this->authenticationError($request, 'This Google account does not match the linked email address.');
            }
            if ($linkedMember->member_level <= 0 || $linkedMember->status !== 'active' || $linkedMember->deleted_at !== null) {
                $this->recordLogin($request, $linkedMember->idx, 'failed', 'member_unavailable');

                return $this->authenticationError($request, 'This account is not available.');
            }

            return $this->login($request, $linkedMember->idx);
        }

        if (DB::table('members')->where('email', $email)->exists()) {
            $this->recordLogin($request, null, 'failed', 'email_account_not_linked');

            return $this->authenticationError($request, 'An account already uses this email. Sign in with your email and password first.');
        }

        $request->session()->put(self::SIGNUP_SESSION_KEY, [
            'provider_id' => $providerId,
            'email' => $email,
            'name' => trim((string) $googleUser->getName()),
            'expires_at' => now()->addSeconds(self::SIGNUP_LIFETIME_SECONDS)->timestamp,
        ]);

        return redirect()->route('auth.google.complete');
    }

    public function showCompletion(Request $request)
    {
        $signup = $this->signupState($request);
        if ($signup === null) {
            return $this->authenticationError($request, 'Your Google sign-up session is missing or expired. Please try again.');
        }

        $suggestedNickname = trim((string) ($signup['name'] ?? ''));
        if (mb_strlen($suggestedNickname) < 2 || mb_strlen($suggestedNickname) > 20) {
            $suggestedNickname = '';
        }

        return view('auth.google-complete', compact('signup', 'suggestedNickname'));
    }

    public function complete(Request $request)
    {
        $signup = $this->signupState($request);
        if ($signup === null) {
            return $this->authenticationError($request, 'Your Google sign-up session is missing or expired. Please try again.');
        }

        $request->merge([
            'nickname' => trim((string) $request->input('nickname')),
            'country_code' => strtoupper(trim((string) $request->input('country_code'))),
        ]);
        $validator = Validator::make($request->all(), [
            'nickname' => ['bail', 'required', 'string', 'min:2', 'max:20', new AllowedNickname, Rule::unique('members', 'nickname')],
            'country_code' => ['required', 'string', Rule::in(Countries::codes())],
        ], [
            'nickname.min' => 'Nickname must be between 2 and 20 characters.',
            'nickname.max' => 'Nickname must be between 2 and 20 characters.',
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $heartRewards = app(HeartRewardManager::class);
        $inviterIdx = $heartRewards->eligibleInviterIdx($request->session()->get(HeartRewardManager::REFERRAL_SESSION_KEY));

        try {
            $memberIdx = DB::transaction(function () use ($request, $signup, $inviterIdx) {
                if (DB::table('member_social_accounts')->where('provider', 'google')
                    ->where('provider_user_id', $signup['provider_id'])->lockForUpdate()->exists()
                    || DB::table('members')->where('email', $signup['email'])->lockForUpdate()->exists()) {
                    throw new \RuntimeException('google_account_conflict');
                }

                $memberIdx = app(MemberCreator::class)->create([
                    'email' => $signup['email'],
                    'password_hash' => null,
                    'nickname' => $request->string('nickname')->toString(),
                    'member_level' => 1,
                    'is_dummy' => 0,
                    'status' => 'active',
                    'country_code' => $request->string('country_code')->toString(),
                    'referred_by_member_idx' => $inviterIdx,
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('member_social_accounts')->insert([
                    'member_idx' => $memberIdx,
                    'provider' => 'google',
                    'provider_user_id' => $signup['provider_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                app(HeartRewardManager::class)->award($memberIdx, 'signup', 'signup:'.$memberIdx);
                $this->recordLogin($request, $memberIdx, 'success');

                return $memberIdx;
            });
        } catch (QueryException|\RuntimeException $exception) {
            if ($exception instanceof QueryException && (string) $exception->getCode() !== '23000') {
                report($exception);

                return back()->withErrors([
                    'registration' => 'Sign-up could not be completed. Please try again.',
                ])->withInput();
            }

            return $this->authenticationError($request, 'This Google account, email, or nickname is already registered.');
        }

        try {
            $heartRewards->awardReferral($memberIdx);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $request->session()->regenerate();
        $request->session()->put('member_idx', $memberIdx);
        $request->session()->forget([self::SIGNUP_SESSION_KEY, HeartRewardManager::REFERRAL_SESSION_KEY]);

        return redirect()->to($this->pullReturnUrl($request));
    }

    private function signupState(Request $request): ?array
    {
        $signup = $request->session()->get(self::SIGNUP_SESSION_KEY);
        if (!is_array($signup) || empty($signup['provider_id']) || empty($signup['email'])
            || (int) ($signup['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::SIGNUP_SESSION_KEY);

            return null;
        }

        return $signup;
    }

    private function login(Request $request, int $memberIdx)
    {
        DB::transaction(function () use ($request, $memberIdx) {
            DB::table('members')->where('idx', $memberIdx)->update(['last_login_at' => now()]);
            $this->recordLogin($request, $memberIdx, 'success');
        });

        $redirect = $this->pullReturnUrl($request);
        $request->session()->regenerate();
        $request->session()->put('member_idx', $memberIdx);
        $request->session()->forget(HeartRewardManager::REFERRAL_SESSION_KEY);

        return redirect()->to($redirect);
    }

    private function authenticationError(Request $request, string $message)
    {
        $request->session()->forget(self::SIGNUP_SESSION_KEY);

        $redirect = $this->safeReturnUrl($request, $request->session()->get(self::RETURN_URL_SESSION_KEY)) ?? '/';

        return redirect()->to($redirect)->with(['open_sign_in' => true, 'auth_error' => $message]);
    }

    private function pullReturnUrl(Request $request): string
    {
        return $this->safeReturnUrl($request, $request->session()->pull(self::RETURN_URL_SESSION_KEY))
            ?? $this->safeReturnUrl($request, $request->session()->pull('url.intended'))
            ?? '/';
    }

    private function recordLogin(Request $request, ?int $memberIdx, string $result, ?string $failureReason = null): void
    {
        DB::table('member_login_histories')->insert([
            'member_idx' => $memberIdx,
            'login_type' => 'google',
            'result' => $result,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1024),
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }
}
