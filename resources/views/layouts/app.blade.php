<!DOCTYPE html>
<html lang="en">
<head>
    @php
        $publicSettings = \App\Support\SiteSettings::all();
        $pageSeo = $seo ?? [];
        $seoTitle = $pageSeo['title'] ?? ($publicSettings['social_title'] ?? null) ?? ($publicSettings['site_title'] ?? 'IDOL RANKING');
        $seoDescription = $pageSeo['description'] ?? ($publicSettings['social_description'] ?? null) ?? ($publicSettings['site_description'] ?? '');
        $seoImage = $pageSeo['image'] ?? ($publicSettings['social_image_path'] ?? null);
        $seoImageUrl = \App\Support\ManagedImageStorage::publicUrl($seoImage);
        $canonicalUrl = $pageSeo['url'] ?? request()->url();
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    @if($publicSettings['favicon_path'] ?? null)<link rel="icon" href="{{ asset($publicSettings['favicon_path']) }}">@endif
    {!! $publicSettings['head_code'] ?? '' !!}
    @if($publicSettings['custom_css'] ?? null)<style>{!! $publicSettings['custom_css'] !!}</style>@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
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
        </defs>
    </svg>
    <a class="skip-link" href="#main-content">Skip to content</a>
    @include('partials.header')
    <main id="main-content" class="page-width main-content">
        @yield('content')
    </main>
    @include('partials.footer')
    @php
        $voteMember = request()->attributes->get('currentMember');
        $voteSignedIn = $voteMember !== null || session()->has('member_idx');
    @endphp
    <x-heart-vote-modal :my-hearts="$voteMember?->heart_balance ?? 12540" :signed-in="$voteSignedIn" />
    <x-sign-in-modal />
    <x-global-notification />
    {!! $publicSettings['body_end_code'] ?? '' !!}
</body>
</html>
