@extends('layouts.admin')

@php($editing = $banner !== null)

@section('title', ($editing ? '파트너 배너 수정' : '파트너 배너 등록').' | 관리자')

@section('content')
    <header class="admin-page-heading">
        <div>
            <h1>{{ $editing ? '파트너 배너 수정' : '파트너 배너 등록' }}</h1>
            <p>이미지와 노출 정보를 입력해 주세요.</p>
        </div>
    </header>

    @if ($errors->any())
        <div class="admin-alert-error" role="alert">
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form class="admin-settings-form admin-partner-banner-form" method="post" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.partner-banners.update', $banner->idx) : route('admin.partner-banners.store') }}">
        @csrf
        @if ($editing) @method('put') @endif

        <label>
            배너 제목
            <input type="text" name="title" value="{{ old('title', $banner->title ?? '') }}" maxlength="255" required>
        </label>

        <label>
            이동 주소 (HTTP/HTTPS)
            <input type="url" name="target_url" value="{{ old('target_url', $banner->target_url ?? '') }}" maxlength="2048" required>
        </label>

        @if ($editing && \App\Support\ManagedImageStorage::isAvailable($banner->image_path))
            <img class="admin-partner-banner-form-preview" src="{{ \App\Support\ManagedImageStorage::publicUrl($banner->image_path) }}" alt="현재 파트너 배너 이미지">
        @endif

        <label>
            배너 이미지{{ $editing ? ' 교체 (선택)' : '' }}
            <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp,image/gif" @required(!$editing)>
            <small>권장 이미지 크기: 800 × 180px (가로 × 세로)</small>
            <small>JPG, PNG, WEBP, GIF 형식, 최대 5MB입니다. {{ $editing ? '파일을 선택하지 않으면 기존 이미지를 유지합니다.' : '' }}</small>
        </label>

        <div class="admin-partner-banner-fields">
            <label>
                하트 보상량
                <input type="number" name="heart_reward" value="{{ old('heart_reward', $banner->heart_reward ?? 10) }}" min="0" max="4294967295" required>
            </label>
            <label>
                노출 순서
                <input type="number" name="display_order" value="{{ old('display_order', $banner->display_order ?? 0) }}" min="0" max="2147483647" required>
            </label>
        </div>

        <label class="admin-partner-banner-check">
            <input type="hidden" name="reward_enabled" value="0">
            <input type="checkbox" name="reward_enabled" value="1" @checked((bool) old('reward_enabled', $banner->reward_enabled ?? false))>
            하트 보상 사용 (배너 참여 시 설정한 하트 지급)
        </label>

        <label>
            노출 상태
            <select name="is_active" required>
                <option value="1" @selected((string) old('is_active', $banner->is_active ?? 1) === '1')>활성</option>
                <option value="0" @selected((string) old('is_active', $banner->is_active ?? 1) === '0')>비활성</option>
            </select>
        </label>

        <div class="admin-partner-banner-fields">
            <label>
                시작 일시 (선택)
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', !empty($banner?->starts_at) ? \Illuminate\Support\Carbon::parse($banner->starts_at)->format('Y-m-d\\TH:i') : '') }}">
            </label>
            <label>
                종료 일시 (선택)
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', !empty($banner?->ends_at) ? \Illuminate\Support\Carbon::parse($banner->ends_at)->format('Y-m-d\\TH:i') : '') }}">
            </label>
        </div>

        <p class="admin-partner-banner-note">시작 또는 종료 일시를 비워 두면 해당 기간 제한이 없습니다.</p>
        <div class="admin-partner-banner-form-actions">
            <button class="admin-settings-save" type="submit">{{ $editing ? '수정 내용 저장' : '배너 등록' }}</button>
            <a href="{{ route('admin.partner-banners.index') }}">목록으로</a>
        </div>
    </form>
@endsection
