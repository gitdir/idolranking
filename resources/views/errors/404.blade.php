<!DOCTYPE html>
@php($errorLocale = array_key_exists(session('locale'), config('locales.public', [])) ? session('locale') : app()->getLocale())
<html lang="{{ $errorLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#ffffff">
    <title>{{ __('ui.not_found_title', [], $errorLocale) }}</title>
    @vite('resources/css/app.css')
</head>
<body class="error-page-body">
    <main class="error-page">
        <a class="brand error-page-brand" href="{{ route('home') }}" aria-label="{{ __('ui.home_aria', [], $errorLocale) }}">
            <svg class="brand-crown" viewBox="0 0 48 40" aria-hidden="true">
                <path fill="#ff4596" d="m5 11 11 7L24 3l8 15 11-7-6 23H11Z"/>
                <path fill="#ff80b6" d="m5 11 11 7L24 3v31H11Z"/>
                <path stroke="#ff4596" stroke-width="3" d="M11 38h26"/>
                <g fill="#ff80b6"><circle cx="4" cy="10" r="3"/><circle cx="24" cy="3" r="3"/><circle cx="44" cy="10" r="3"/></g>
            </svg>
            <span translate="no"><span class="brand-accent">IDOL</span> RANKING</span>
        </a>

        <section class="error-page-card" aria-labelledby="error-page-heading">
            <p class="error-page-code">404</p>
            <h1 id="error-page-heading">{{ __('ui.not_found_heading', [], $errorLocale) }}</h1>
            <p class="error-page-description">{{ __('ui.not_found_description', [], $errorLocale) }}</p>
            <a class="error-page-home" href="{{ route('home') }}">{{ __('ui.go_home', [], $errorLocale) }}</a>
        </section>
    </main>
</body>
</html>
