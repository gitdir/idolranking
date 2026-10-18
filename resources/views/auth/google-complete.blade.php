@extends('layouts.app')

@section('title', __('ui.google_signup_title').' | IdolRanking')

@section('content')
    <section class="register-page" aria-labelledby="google-complete-title">
        <div class="register-decoration" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="register-dots"></span><span class="register-dots register-dots--lower"></span></div>
        <div class="register-card">
            <svg class="register-crown" viewBox="0 0 160 64" aria-hidden="true"><path fill="#ffc6e8" d="M10 30q10 0 10-12 0 12 10 12-10 0-10 12 0-12-10-12M140 20q8 0 8-10 0 10 8 10-8 0-8 10 0-10-8-10"/><path fill="#f365ae" d="m54 20 13 14L80 7l13 27 13-14-8 34H62Z"/><g fill="#f67ab9"><circle cx="54" cy="20" r="4"/><circle cx="80" cy="7" r="4"/><circle cx="106" cy="20" r="4"/></g></svg>
            <h1 id="google-complete-title">{{ __('ui.complete_signup') }}</h1>
            <p class="register-description">{{ __('ui.google_signup_description') }}</p>
            <form class="register-form" method="post" action="{{ route('auth.google.complete.store') }}">
                @csrf
                @error('registration')<p class="register-error" role="alert">{{ $message }}</p>@enderror
                <div class="register-field">
                    <label for="google-email">{{ __('ui.email_address') }}</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/></svg><input id="google-email" type="email" value="{{ $signup['email'] }}" readonly></div>
                </div>
                <div class="register-field">
                    <label for="google-nickname">{{ __('ui.nickname') }}</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 6 0 0 1 16 0v2Z"/></svg><input id="google-nickname" name="nickname" type="text" autocomplete="nickname" value="{{ old('nickname', $suggestedNickname) }}" required minlength="2" maxlength="20"></div>
                    @error('nickname')<p class="register-error">{{ $message }}</p>@enderror
                </div>
                <div class="register-field">
                    <label for="google-country">{{ __('ui.country') }}</label>
                    <div class="register-input-wrap"><svg class="register-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18M5 6h14M5 18h14"/></svg><x-country-select id="google-country" :selected="old('country_code')" /></div>
                    @error('country_code')<p class="register-error">{{ $message }}</p>@enderror
                </div>
                <button class="register-submit" type="submit">{{ __('ui.complete_signup') }}</button>
            </form>
        </div>
    </section>
@endsection
