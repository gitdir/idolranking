<!DOCTYPE html>
{{-- 관리자 화면의 사용자 노출 문구는 한국어로 유지하고 내부 식별자는 영어를 사용합니다. --}}
<html lang="ko">
<head>
    @php($publicSettings = \App\Support\SiteSettings::all())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#f8faff">
    <meta name="default-image" content="{{ \App\Support\ManagedImageStorage::defaultUrl() }}">
    <title>@yield('title', '관리자 | IdolRanking')</title>
    <link rel="icon" href="{{ asset($publicSettings['favicon_path'] ?? 'favicon.ico') }}">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="admin-body">
    <a class="admin-skip-link" href="#admin-content">본문으로 바로가기</a>
    @include('admin.partials.topbar')
    <div class="admin-shell">
        @include('admin.partials.sidebar')
        <main id="admin-content" class="admin-main">
            @yield('content')
        </main>
    </div>
</body>
</html>
