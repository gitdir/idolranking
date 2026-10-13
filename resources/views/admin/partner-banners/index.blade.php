@extends('layouts.admin')

@section('title', '파트너 배너 관리 | 관리자')

@section('content')
    <header class="admin-page-heading">
        <div>
            <h1>파트너 배너 관리</h1>
            <p>하트 받기 페이지에 표시할 파트너 배너를 관리합니다.</p>
        </div>
        <a class="admin-partner-banner-create" href="{{ route('admin.partner-banners.create') }}">배너 등록</a>
    </header>

    @if (session('admin_success'))
        <div class="admin-alert-success" role="status">{{ session('admin_success') }}</div>
    @endif

    <section class="admin-request-table-card">
        <div class="admin-table-scroll">
            <table class="admin-management-table admin-partner-banner-table">
                <thead>
                    <tr>
                        <th class="admin-partner-banner-image-cell">이미지</th>
                        <th>배너 제목</th>
                        <th>하트 보상</th>
                        <th>유효 클릭</th>
                        <th>노출 기간</th>
                        <th>상태</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($banners as $banner)
                        <tr>
                            <td class="admin-partner-banner-image-cell">
                                <img class="admin-partner-banner-preview" src="{{ \App\Support\ManagedImageStorage::publicUrl($banner->image_path) }}" alt="{{ $banner->title }} 미리보기">
                                <a class="admin-partner-banner-url" href="{{ $banner->target_url }}" title="{{ $banner->target_url }}" target="_blank" rel="noopener noreferrer">{{ $banner->target_url }}</a>
                            </td>
                            <td><strong>{{ $banner->title }}</strong></td>
                            <td class="admin-partner-banner-reward">
                                @if ($banner->reward_enabled && $banner->heart_reward > 0)
                                    ❤️ {{ number_format($banner->heart_reward) }}
                                @else
                                    보상 없음
                                @endif
                            </td>
                            <td>{{ number_format($banner->total_valid_clicks) }}</td>
                            <td class="admin-partner-banner-period">
                                {{ $banner->starts_at ? \Illuminate\Support\Carbon::parse($banner->starts_at)->format('Y.m.d H:i') : '시작일 제한 없음' }}
                                <br>~ {{ $banner->ends_at ? \Illuminate\Support\Carbon::parse($banner->ends_at)->format('Y.m.d H:i') : '종료일 제한 없음' }}
                            </td>
                            <td>
                                @php
                                    $bannerNow = now();
                                    $bannerStarted = !$banner->starts_at || \Illuminate\Support\Carbon::parse($banner->starts_at)->lte($bannerNow);
                                    $bannerNotEnded = !$banner->ends_at || \Illuminate\Support\Carbon::parse($banner->ends_at)->gte($bannerNow);
                                @endphp
                                {{ !$banner->is_active ? '비활성' : ($bannerStarted && $bannerNotEnded ? '노출 중' : ($banner->starts_at && !$bannerStarted ? '노출 예정' : '노출 종료')) }}
                            </td>
                            <td>
                                <div class="admin-partner-banner-actions">
                                    <a href="{{ route('admin.partner-banners.edit', $banner->idx) }}">수정</a>
                                    <form method="post" action="{{ route('admin.partner-banners.destroy', $banner->idx) }}" onsubmit="return confirm('이 파트너 배너를 삭제하시겠습니까?')">
                                        @csrf
                                        @method('delete')
                                        <button class="admin-danger-button" type="submit">삭제</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="admin-table-empty">등록된 파트너 배너가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="admin-native-pagination">{{ $banners->links() }}</div>
    </section>
@endsection

