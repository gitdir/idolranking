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
        <a href="{{ route('admin.settings.edit', 'custom-code') }}" @if ($section === 'custom-code') aria-current="page" @endif>추가 코드 설정</a>
    </nav>

    @if (session('admin_success'))
        <div class="admin-alert-success">{{ session('admin_success') }}</div>
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
