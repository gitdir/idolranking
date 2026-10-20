<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    @php
        $publicSettings = \App\Support\SiteSettings::all();
        $pageSeo = $seo ?? [];
        $seoTitle = $pageSeo['title'] ?? ($publicSettings['social_title'] ?? null) ?? ($publicSettings['site_title'] ?? 'IDOL RANKING');
        $seoDescription = $pageSeo['description'] ?? ($publicSettings['social_description'] ?? null) ?? ($publicSettings['site_description'] ?? '');
        $seoImage = $pageSeo['image'] ?? ($publicSettings['social_image_path'] ?? null);
        $seoImageUrl = \App\Support\ManagedImageStorage::publicUrl($seoImage);
        $canonicalUrl = $pageSeo['url'] ?? request()->url();
        $authenticatedMember = request()->attributes->get('currentMember');
        $activityMember = request()->attributes->get('activityMember');
        $activityMemberIsAuthenticated = $authenticatedMember !== null && $activityMember !== null
            && (int) $authenticatedMember->idx === (int) $activityMember->idx
            && ! (bool) request()->attributes->get('randomDummyMode', false);
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#ffffff">
    <meta name="default-image" content="{{ \App\Support\ManagedImageStorage::defaultUrl() }}">
    <title>@yield('title', $seoTitle)</title>
    @if($seoDescription)<meta name="description" content="{{ $seoDescription }}">@endif
    @if($publicSettings['site_keywords'] ?? null)<meta name="keywords" content="{{ $publicSettings['site_keywords'] }}">@endif
    @if($pageSeo['robots'] ?? null)<meta name="robots" content="{{ $pageSeo['robots'] }}">@endif
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:type" content="{{ $pageSeo['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    @if($seoDescription)<meta property="og:description" content="{{ $seoDescription }}">@endif
    <meta property="og:image" content="{{ $seoImageUrl }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <link rel="icon" href="{{ asset($publicSettings['favicon_path'] ?? 'favicon.ico') }}">
    {!! $publicSettings['head_code'] ?? '' !!}
    @if($publicSettings['custom_css'] ?? null)<style>{!! $publicSettings['custom_css'] !!}</style>@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        window.idolRankingT = (key, values = {}) => {
            let message = window.idolRankingMessages?.[key] ?? key;
            Object.entries(values).forEach(([name, value]) => { message = message.replaceAll(`:${name}`, String(value)); });
            return message;
        };
        window.idolRankingMessages = @json(__('js'));
    </script>
</head>
<body data-activity-member-is-authenticated="{{ $activityMemberIsAuthenticated ? 'true' : 'false' }}">
    <svg class="icon-definitions" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <defs>
            <symbol id="icon-heart" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 21s-9-5.7-9-12a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 6.3-9 12-9 12Z"/>
            </symbol>
            <symbol id="icon-eye" viewBox="0 0 24 24">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="2"/>
                <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/>
            </symbol>
            <symbol id="icon-recommend" viewBox="0 0 24 24">
                <path fill="currentColor" d="M2 10h4v12H2zm6 0 5-8c2 0 3 1 2.5 3L14 9h5a3 3 0 0 1 3 3l-2 8a2 2 0 0 1-2 2H8Z"/>
            </symbol>
            <symbol id="icon-comment" viewBox="0 0 24 24">
                <path fill="currentColor" d="M12 2C6 2 2 5.7 2 10.5c0 2.8 1.4 5.1 4 6.7L4 22l6-3h2c6 0 10-3.7 10-8.5S18 2 12 2Z"/>
                <circle cx="7" cy="10" r="1" fill="white"/><circle cx="12" cy="10" r="1" fill="white"/><circle cx="17" cy="10" r="1" fill="white"/>
            </symbol>
            <symbol id="icon-calendar-check" viewBox="0 0 24 24">
                <path d="M8 2v4M16 2v4M3 10h18"/><rect x="3" y="4" width="18" height="18" rx="2"/><path d="m9 16 2 2 4-4"/>
            </symbol>
            <symbol id="icon-play-circle" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4z"/>
            </symbol>
            <symbol id="icon-image-plus" viewBox="0 0 24 24">
                <path d="M16 5h6M19 2v6"/><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3-3L5 21"/>
            </symbol>
            <symbol id="icon-message-circle" viewBox="0 0 24 24">
                <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>
            </symbol>
            <symbol id="icon-thumbs-up" viewBox="0 0 24 24">
                <path d="M7 10v12M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h3l3.5-6.5a2 2 0 0 1 4 0v.5Z"/>
            </symbol>
            <symbol id="icon-user-plus" viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>
            </symbol>
            <symbol id="icon-users" viewBox="0 0 24 24">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
            </symbol>
        </defs>
    </svg>
    <a class="skip-link" href="#main-content">Skip to content</a>
    @include('partials.header')
    <main id="main-content" class="page-width main-content">
        @yield('content')
    </main>
    @include('partials.footer')
    @php
        $voteMember = request()->attributes->get('activityMember') ?? request()->attributes->get('currentMember');
        $voteSignedIn = request()->attributes->get('currentMember') !== null || session()->has('member_idx');
        $randomDummyMode = (bool) request()->attributes->get('randomDummyMode', false);
    @endphp
    <x-heart-vote-modal :my-hearts="$randomDummyMode ? 0 : ($voteMember?->heart_balance ?? 12540)" :signed-in="$voteSignedIn" :random-dummy-mode="$randomDummyMode" />
    <x-sign-in-modal />
    <x-idol-image-report-modal />
    <x-global-notification />
    {!! $publicSettings['body_end_code'] ?? '' !!}
</body>
</html>
