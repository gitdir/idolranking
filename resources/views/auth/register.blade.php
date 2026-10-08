@extends('layouts.app')

@section('title', 'Create an Account | IdolRanking')

@section('content')
    <section class="register-page" aria-labelledby="register-title">
        <div class="register-decoration" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="register-dots"></span><span class="register-dots register-dots--lower"></span></div>
        <div class="register-card">
            <svg class="register-crown" viewBox="0 0 160 64" aria-hidden="true"><path fill="#ffc6e8" d="M10 30q10 0 10-12 0 12 10 12-10 0-10 12 0-12-10-12M140 20q8 0 8-10 0 10 8 10-8 0-8 10 0-10-8-10"/><path fill="#f365ae" d="m54 20 13 14L80 7l13 27 13-14-8 34H62Z"/><g fill="#f67ab9"><circle cx="54" cy="20" r="4"/><circle cx="80" cy="7" r="4"/><circle cx="106" cy="20" r="4"/></g></svg>
            <h1 id="register-title">Create an Account</h1>
            <p class="register-description">Sign up with your email to join Idol Ranking.</p>
            @if ($hasReferralInvitation ?? false)
                <aside class="register-invitation-note">
                    <strong>You've been invited to IdolRanking!</strong>
                    <span>Create your account and start supporting your favorite idols.</span>
                </aside>
            @endif
            <form class="register-form" method="post" action="{{ route('register.store') }}"
                  data-verification-send-url="{{ route('register.verification.send') }}"
                  data-verification-verify-url="{{ route('register.verification.verify') }}"
                  data-verification-reset-url="{{ route('register.verification.reset') }}"
                  data-verification-email="{{ $emailVerification['email'] ?? '' }}"
                  data-verification-verified="{{ ($emailVerification['verified'] ?? false) ? '1' : '0' }}"
                  data-verification-expires-at="{{ $emailVerification['expires_at'] ?? 0 }}"
                  data-verification-resend-at="{{ $emailVerification['resend_available_at'] ?? 0 }}"
                  novalidate>
                @csrf
                <p class="register-form-message" data-register-message role="alert" hidden></p>
                <div class="register-field">
                    <label for="register-email">Email Address</label>
                    <div class="register-verification-row">
                        <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="register-email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required maxlength="254" value="{{ $emailVerification['email'] ?? '' }}"></div>
                        <button class="register-code-button" type="button" data-send-verification-code>Send Code</button>
                    </div>
                    <p class="register-error" data-register-error="email" hidden></p>
                </div>
                <div class="register-field register-verification" data-verification-panel @if(!$emailVerification) hidden @endif>
                    <label for="register-verification-code">Verification Code</label>
                    <div class="register-verification-row">
                        <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 3h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg><input id="register-verification-code" name="verification_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code"></div>
                        <button class="register-code-button" type="button" data-verify-email>Verify</button>
                    </div>
                    <p class="register-verification-timer" data-verification-timer aria-live="polite"></p>
                    <p class="register-verification-status" data-verification-status role="status" @if(!($emailVerification['verified'] ?? false)) hidden @endif>Email verified.</p>
                    <p class="register-error" data-register-error="email_verification" hidden></p>
                </div>
                <div class="register-field">
                    <label for="register-password">Password</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/></svg><input id="register-password" name="password" type="password" autocomplete="new-password" placeholder="Create a password" required minlength="8" maxlength="64" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,64}" aria-describedby="register-password-hint"><button class="register-visibility" type="button" aria-controls="register-password" aria-label="Show password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="register-eye-slash" d="m3 3 18 18"/></svg></button></div>
                    <p class="register-hint" id="register-password-hint">8–64 characters with at least one letter and one number.</p>
                    <p class="register-error" data-register-error="password" hidden></p>
                </div>
                <div class="register-field">
                    <label for="register-confirm-password">Confirm Password</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/></svg><input id="register-confirm-password" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Confirm your password" required minlength="8" maxlength="64"><button class="register-visibility" type="button" aria-controls="register-confirm-password" aria-label="Show confirm password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="register-eye-slash" d="m3 3 18 18"/></svg></button></div>
                    <p class="register-error" data-register-error="password_confirmation" hidden></p>
                </div>
                <div class="register-field">
                    <label for="register-nickname">Nickname</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 6 0 0 1 16 0v2Z"/></svg><input id="register-nickname" name="nickname" type="text" autocomplete="nickname" placeholder="Choose a nickname" required minlength="2" maxlength="20" aria-describedby="register-nickname-hint"></div>
                    <p class="register-hint" id="register-nickname-hint">2–20 characters.</p>
                    <p class="register-error" data-register-error="nickname" hidden></p>
                </div>
                <div class="register-field">
                    <label for="register-country">Country</label>
                    <div class="register-input-wrap register-country-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 6h14M5 18h14"/></svg><x-country-select id="register-country" :selected="old('country_code')" /></div>
                    <p class="register-error" data-register-error="country_code" hidden></p>
                </div>
                <button class="register-submit" type="submit">Create Account</button>
            </form>
            <p class="register-sign-in">Already have an account? <button type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">Sign In</button></p>
            <p class="register-google-note">Google sign-in is available from the Sign In modal.</p>
        </div>
    </section>
@endsection
