<dialog id="sign-in-modal" class="sign-in-modal" aria-labelledby="sign-in-title" data-auto-open="{{ session('open_sign_in') ? 'true' : 'false' }}">
    <button class="sign-in-close" type="button" data-sign-in-close aria-label="{{ __('ui.close') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <h2 id="sign-in-title">{{ __('ui.sign_in') }}</h2>
    <p class="sign-in-description">{{ __('ui.sign_in_description') }}</p>
    <form class="sign-in-form" method="post" action="{{ route('login') }}" novalidate>
        @csrf
        <input type="hidden" name="return_url" value="{{ request()->fullUrl() }}">
        <p class="sign-in-error" data-sign-in-error role="alert" @if(!session('auth_error')) hidden @endif>{{ session('auth_error') }}</p>
        <label for="sign-in-email">{{ __('ui.email_address') }}</label>
        <div class="sign-in-input-wrap"><svg class="sign-in-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="sign-in-email" name="email" type="email" autocomplete="email" placeholder="{{ __('ui.enter_email') }}" required autofocus></div>
        <label for="sign-in-password">{{ __('ui.password') }}</label>
        <div class="sign-in-input-wrap"><svg class="sign-in-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4M12 14v3"/></svg><input id="sign-in-password" name="password" type="password" autocomplete="current-password" placeholder="{{ __('ui.enter_password') }}" required><button class="sign-in-visibility" type="button" aria-controls="sign-in-password" aria-label="{{ __('ui.password') }}" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
        <div class="sign-in-options">
            <label class="sign-in-remember"><input type="checkbox" name="remember" value="1"> {{ __('ui.remember_me') }}</label>
            <a class="sign-in-text-link" href="{{ route('password.forgot') }}">{{ __('ui.forgot_password') }}</a>
        </div>
        <button class="sign-in-submit" type="submit">{{ __('ui.sign_in') }}</button>
    </form>
    <div class="sign-in-divider"><span>{{ __('ui.or') }}</span></div>
    <a class="sign-in-google" href="{{ route('auth.google.redirect', ['return_url' => request()->fullUrl()]) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285f4" d="M21.6 12.2c0-.7-.1-1.5-.2-2.2H12v4.2h5.4a4.6 4.6 0 0 1-2 3v2.5h3.2c1.9-1.8 3-4.3 3-7.5Z"/><path fill="#34a853" d="M12 22c2.7 0 5-.9 6.6-2.3l-3.2-2.5c-.9.6-2 1-3.4 1-2.6 0-4.9-1.8-5.7-4.2H3v2.6A10 10 0 0 0 12 22Z"/><path fill="#fbbc05" d="M6.3 14a6 6 0 0 1 0-4V7.4H3a10 10 0 0 0 0 9.2Z"/><path fill="#ea4335" d="M12 5.8c1.5 0 2.8.5 3.8 1.5l2.9-2.9A9.6 9.6 0 0 0 12 2a10 10 0 0 0-9 5.4L6.3 10c.8-2.4 3.1-4.2 5.7-4.2Z"/></svg>
        {{ __('ui.continue_google') }}
    </a>
    <p class="sign-in-create">{{ __('ui.new_here') }} <a class="sign-in-text-link" href="{{ route('register') }}">{{ __('ui.create_account') }}</a></p>
</dialog>
