@extends('layouts.admin')

@section('title', '관리 설정')

@section('content')
    <header class="admin-page-heading">
        <div>
            <h1>관리 설정</h1>
            <p>공개 사이트의 기본 정보와 메타데이터, 추가 코드를 관리합니다.</p>
        </div>
    </header>

    <nav class="admin-settings-tabs">
        <a href="{{ route('admin.settings.edit', 'general') }}" @if ($section === 'general') aria-current="page" @endif>기본 설정</a>
        <a href="{{ route('admin.settings.edit', 'seo') }}" @if ($section === 'seo') aria-current="page" @endif>SEO 설정</a>
        <a href="{{ route('admin.settings.edit', 'management') }}" @if ($section === 'management') aria-current="page" @endif>기능 관리</a>
        <a href="{{ route('admin.scraping.index') }}">스크래핑</a>
        <a href="{{ route('admin.settings.edit', 'custom-code') }}" @if ($section === 'custom-code') aria-current="page" @endif>추가 코드 설정</a>
    </nav>

    @if (session('admin_success'))
        <div class="admin-alert-success">{{ session('admin_success') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-alert-error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form class="admin-settings-form" method="post" enctype="multipart/form-data" action="{{ route('admin.settings.update', $section) }}">
        @csrf
        @method('put')

        @if ($section === 'general')
            <label>
                사이트 제목
                <input name="site_title" maxlength="100" value="{{ old('site_title', $settings['site_title'] ?? 'IDOL RANKING') }}">
            </label>
            <label>
                사이트 설명
                <textarea name="site_description" maxlength="500">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
            </label>
        @elseif ($section === 'seo')
            <h2>기본 SEO</h2>
            <label>
                사이트 제목
                <input name="site_title" maxlength="100" value="{{ old('site_title', $settings['site_title'] ?? '') }}">
            </label>
            <label>
                사이트 설명
                <textarea name="site_description" maxlength="500">{{ old('site_description', $settings['site_description'] ?? '') }}</textarea>
            </label>
            <label>
                키워드
                <input name="site_keywords" maxlength="500" value="{{ old('site_keywords', $settings['site_keywords'] ?? '') }}">
            </label>

            <h2>SNS 공유 설정</h2>
            <label>
                SNS 공유 제목
                <input name="social_title" maxlength="100" value="{{ old('social_title', $settings['social_title'] ?? '') }}">
            </label>
            <label>
                SNS 공유 설명
                <textarea name="social_description" maxlength="500">{{ old('social_description', $settings['social_description'] ?? '') }}</textarea>
            </label>
            <label>
                SNS 공유 기본 이미지
                <input type="file" name="social_image" accept="image/jpeg,image/png,image/webp">
            </label>
            @if ($settings['social_image_path'] ?? null)
                <img class="admin-setting-preview" src="{{ \App\Support\ManagedImageStorage::publicUrl($settings['social_image_path']) }}" alt="현재 SNS 공유 기본 이미지">
                <label class="admin-check"><input type="checkbox" name="remove_social_image" value="1"> 이미지 제거</label>
            @endif

            <h2>사이트 아이콘</h2>
            <label>
                파비콘
                <input type="file" name="favicon" accept="image/png,image/x-icon">
            </label>
            @if ($settings['favicon_path'] ?? null)
                <img class="admin-setting-preview admin-setting-favicon" src="{{ \App\Support\ManagedImageStorage::publicUrl($settings['favicon_path']) }}" alt="현재 파비콘">
                <label class="admin-check"><input type="checkbox" name="remove_favicon" value="1"> 파비콘 제거</label>
            @endif
        @elseif ($section === 'management')
            <h2>팬포스트 조회수 자동 증가</h2>
            <p>켜면 매분 공개된 팬포스트 중 하나의 조회수를 1~5 사이의 무작위 값만큼 올립니다. 대상 글이 없으면 아무 작업도 하지 않습니다.</p>
            <label class="admin-check">
                <input type="hidden" name="fan_post_view_boost_enabled" value="0">
                <input type="checkbox" name="fan_post_view_boost_enabled" value="1" @checked(old('fan_post_view_boost_enabled', $settings['fan_post_view_boost_enabled'] ?? '0') === '1')>
                팬포스트 조회수 자동 증가 사용
            </label>
            <h2>더미 계정 자동투표</h2>
            <p>활성 더미 계정 한 명과 활성 아이돌 또는 그룹 한 명을 각각 무작위로 골라 투표합니다. 하트가 부족하면 부족분만 지급하고 이력에 남깁니다.</p>
            <label class="admin-check">
                <input type="hidden" name="dummy_auto_vote_enabled" value="0">
                <input type="checkbox" name="dummy_auto_vote_enabled" value="1" @checked((string) old('dummy_auto_vote_enabled', $settings['dummy_auto_vote_enabled'] ?? '0') === '1')>
                더미 계정 자동투표 사용
            </label>
            <h2>더미 회원 자동 생성</h2>
            <p>매주 월요일 오전 4시(한국 시간)에 운영 관리용 더미 회원을 한 명 생성합니다. 일반 회원 통계에는 포함되지 않습니다.</p>
            <label class="admin-check">
                <input type="hidden" name="dummy_auto_create_enabled" value="0">
                <input type="checkbox" name="dummy_auto_create_enabled" value="1" @checked((string) old('dummy_auto_create_enabled', $settings['dummy_auto_create_enabled'] ?? '0') === '1')>
                더미 회원 자동 생성 사용
            </label>
            @if ($weeklyDummyCreations->isNotEmpty())
                <h3>최근 자동 생성 내역</h3>
                <ul>
                    @foreach ($weeklyDummyCreations as $creation)
                        <li>{{ $creation->week_start_date }} · {{ $creation->nickname }} ({{ $creation->member_code }}) · {{ $creation->created_at }}</li>
                    @endforeach
                </ul>
            @endif
            <div class="admin-auto-vote-fields">
                <span>매</span>
                <input type="number" name="dummy_auto_vote_interval_minutes" aria-label="투표 간격 (분)" min="1" max="1440" required value="{{ old('dummy_auto_vote_interval_minutes', $settings['dummy_auto_vote_interval_minutes'] ?? '5') }}">
                <span>분 하트</span>
                <input type="number" name="dummy_auto_vote_min_hearts" aria-label="최소 투표 하트" min="1" max="100000" required value="{{ old('dummy_auto_vote_min_hearts', $settings['dummy_auto_vote_min_hearts'] ?? '10') }}">
                <span>개 ~</span>
                <input type="number" name="dummy_auto_vote_max_hearts" aria-label="최대 투표 하트" min="1" max="100000" required value="{{ old('dummy_auto_vote_max_hearts', $settings['dummy_auto_vote_max_hearts'] ?? '50') }}">
                <span>개 투표</span>
            </div>
        @else
            <div class="admin-code-warning">추가한 코드는 사이트 전체 화면에 영향을 줄 수 있습니다.</div>
            <label>
                HEAD 추가 코드
                <textarea class="admin-code-input" name="head_code" rows="10">{{ old('head_code', $settings['head_code'] ?? '') }}</textarea>
            </label>
            <label>
                추가 CSS
                <textarea class="admin-code-input" name="custom_css" rows="12">{{ old('custom_css', $settings['custom_css'] ?? '') }}</textarea>
            </label>
            <label>
                BODY 종료 전 추가 스크립트
                <textarea class="admin-code-input" name="body_end_code" rows="10">{{ old('body_end_code', $settings['body_end_code'] ?? '') }}</textarea>
            </label>
        @endif

        <button class="admin-settings-save">설정 저장</button>
    </form>
@endsection
