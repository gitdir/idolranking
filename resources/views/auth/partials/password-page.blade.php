@php
    $title = $reset ? __('ui.reset_your_password') : __('ui.forgot_password_title');
    $description = $reset ? __('ui.create_new_password_description') : __('ui.reset_email_description');
@endphp
<section class="password-page{{ $reset ? '' : ' password-page--forgot' }}" aria-labelledby="{{ $reset ? 'password-page-title' : 'password-card-title' }}">
    @if ($reset)
        <header class="password-page-heading">
            <h1 id="password-page-title">{{ $title }}</h1>
            <p>{{ $description }}</p>
            <svg class="password-page-decoration" viewBox="0 0 180 130" aria-hidden="true"><path fill="#f5d5fa" d="m45 30 30 30 28-42 18 52 42-23-28 62-84-24Z"/><path fill="#edd8ff" d="M18 65S-5 50 4 41q7-8 14 1 7-9 14-1 9 9-14 24"/><g fill="#fbe0f6"><circle cx="45" cy="21" r="8"/><circle cx="105" cy="9" r="8"/><circle cx="169" cy="42" r="8"/></g></svg>
        </header>
    @endif
    <div class="password-page-card">
        <svg class="password-page-art" viewBox="0 0 220 180" aria-hidden="true">
            <circle cx="110" cy="90" r="84" fill="#fff0fa"/>
            @if ($reset)
                <path d="M88 76V53a22 22 0 0 1 44 0v23" fill="none" stroke="#a28aff" stroke-width="11"/>
                <rect x="71" y="69" width="78" height="65" rx="12" fill="#b9a1ff" stroke="#a98fff"/>
                <circle cx="110" cy="97" r="10" fill="#fff8ff"/><rect x="105" y="98" width="10" height="19" rx="4" fill="#fff8ff"/>
            @else
                <g transform="rotate(-9 110 90)"><path d="m45 84 65-49 65 49v65H45Z" fill="#c9baff" stroke="#a48fff" stroke-width="3"/><rect x="63" y="38" width="96" height="90" rx="7" fill="#fff" stroke="#b8a4ff" stroke-width="4"/><path d="M111 93s-28-16-16-28q9-8 16 2 8-10 17-2 12 12-17 28" fill="#ff70b9"/><path d="m45 84 65 43 65-43v65H45Z" fill="#ede8ff" stroke="#c4b8ff" stroke-width="3"/><path d="m45 149 65-44 65 44" fill="#f6f2ff" stroke="#c4b8ff" stroke-width="3"/></g>
            @endif
            <path d="m12 78-6-4m8 21-8 2m199-22 6-5m-6 24 8 2" stroke="#ff70b9" stroke-width="4" stroke-linecap="round"/>
        </svg>
        <h2 @if(!$reset) id="password-card-title" @endif>{{ $title }}</h2>
        <p class="password-card-description">{{ $reset ? __('ui.enter_new_password_below') : $description }}</p>
        <form class="password-preview-fields password-page-form" data-password-page-form method="post"
              action="{{ $reset ? route('password.update') : route('password.email') }}">
            @csrf
            @if ($reset)
                <input type="hidden" name="email" value="{{ $resetEmail ?? '' }}">
                <input type="hidden" name="token" data-reset-token>
                @foreach (['password' => __('ui.new_password'), 'password_confirmation' => __('ui.confirm_new_password')] as $key => $label)
                    @php($inputId = $key === 'password' ? 'password-new' : 'password-confirm')
                    <div class="password-page-field">
                        <label for="{{ $inputId }}">{{ $label }} <span>*</span></label>
                        <div class="password-page-input">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/></svg>
                            <input id="{{ $inputId }}" name="{{ $key }}" type="password" autocomplete="new-password" placeholder="{{ $key === 'password' ? __('ui.enter_new_password') : __('ui.confirm_new_password_placeholder') }}" minlength="8" maxlength="64" required disabled>
                            <button type="button" class="password-page-eye" aria-controls="{{ $inputId }}" aria-label="{{ __('js.password_show') }}" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        @if ($key === 'password')<p class="password-page-hint">{{ __('ui.password_reset_hint') }}</p>@endif
                    </div>
                @endforeach
            @else
                <div class="password-page-field">
                    <label for="password-email">{{ __('ui.email_address') }} <span>*</span></label>
                    <div class="password-page-input"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="password-email" name="email" type="email" autocomplete="email" maxlength="254" placeholder="{{ __('ui.enter_email') }}" required></div>
                </div>
            @endif
            <p class="password-page-message" data-password-page-message role="status" aria-live="polite">@if(!$reset && session('status')){{ session('status') }}@endif</p>
            <button type="submit" class="password-page-submit" @if($reset) disabled @endif>{{ $reset ? __('ui.reset_password_button') : __('ui.send_reset_link') }}</button>
        </form>
        <div class="password-page-divider"><span>{{ __('ui.back_to') }}</span></div>
        <button type="button" class="password-page-sign-in" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12H4m7-7-7 7 7 7"/></svg>{{ __('ui.sign_in') }}</button>
    </div>
    @if ($reset)
        <footer class="password-page-footer"><strong>IDOL RANKING</strong><p>&copy; {{ date('Y') }} IDOL RANKING. {{ __('ui.copyright') }}</p></footer>
    @endif
</section>
<script>
    (() => {
        const form = document.querySelector('[data-password-page-form]');
        const message = form?.querySelector('[data-password-page-message]');
        if (!form || !message) return;

        const resetTokenInput = form.querySelector('[data-reset-token]');
        if (resetTokenInput) {
            const token = new URLSearchParams(window.location.hash.slice(1)).get('token') || '';
            resetTokenInput.value = token;
            form.querySelectorAll('input[type="password"], button[type="submit"]').forEach((control) => {
                control.disabled = !/^[a-fA-F0-9]{64}$/.test(token);
            });
            if (!token) message.textContent = window.idolRankingT('reset_invalid');
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true;
            message.textContent = '';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const result = await response.json();
                message.textContent = result.message || window.idolRankingT('reset_failed');
                if (result.success && resetTokenInput) {
                    form.querySelectorAll('input').forEach((input) => { input.disabled = true; });
                    window.history.replaceState(null, '', window.location.pathname + window.location.search);
                } else if (!resetTokenInput || resetTokenInput.value) {
                    submit.disabled = false;
                }
            } catch {
                message.textContent = window.idolRankingT('reset_failed_later');
                submit.disabled = false;
            }
        });
    })();

    @if ($reset)
        document.querySelectorAll('.password-page-eye').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.getAttribute('aria-controls'));
                const visible = input.type === 'password';
                input.type = visible ? 'text' : 'password';
                button.setAttribute('aria-pressed', String(visible));
                button.setAttribute('aria-label', window.idolRankingT(visible ? 'password_hide' : 'password_show'));
            });
        });
    @endif
</script>
