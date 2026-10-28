@extends('layouts.admin')

@section('title', '프로필 이미지 권리 신고 검토')

@section('content')
<header class="admin-page-heading"><div><h1>프로필 이미지 권리 신고 검토</h1><p>{{ $report->english_name ?: $report->idol_name }} · 신고 #{{ $report->idx }}</p></div><a href="{{ route('admin.idol-profile-image-reports.index') }}">신고 목록</a></header>
@if (session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
@if ($errors->any())<div class="admin-alert-error">{{ $errors->first() }}</div>@endif
<section class="admin-request-table-card admin-image-report-detail">
    <h2>신고된 이미지</h2>
    <img src="{{ route('admin.idol-profile-image-reports.evidence', $report->idx) }}" alt="신고된 프로필 이미지">
    <dl>
        <dt>대상</dt><dd>{{ $report->idol_type === 'group' ? '그룹' : '개인 아이돌' }}: {{ $report->idol_name }} ({{ $report->english_name }})</dd>
        <dt>공개 상태</dt><dd>{{ $report->idol_status === 'active' ? '공개 중' : '비활성' }}</dd>
        <dt>현재 프로필 이미지</dt><dd>{{ $report->current_image_path === $report->reported_image_path ? '신고 이미지와 일치' : ($report->current_image_path ? '신고 접수 후 이미지 변경됨' : '현재 프로필 이미지 없음') }}</dd>
        <dt>신고자</dt><dd><x-admin-dummy-name :nickname="$report->reporter_nickname" /> (회원 #{{ $report->member_idx }})@if($report->actor_member_idx !== $report->member_idx) · 실제 제출자 {{ $report->actor_nickname }}@endif</dd>
        <dt>신고 접수</dt><dd>{{ \Illuminate\Support\Carbon::parse($report->created_at, 'UTC')->timezone('Asia/Seoul')->format('Y-m-d H:i') }} KST</dd>
        <dt>사유</dt><dd>{{ ['copyright' => '저작권 침해', 'portrait_rights' => '초상권 침해', 'other' => '기타'][$report->reason] ?? '기타' }}</dd>
        <dt>상세 내용</dt><dd>{{ $report->details ?: '상세 내용 없음' }}</dd>
        @if ($report->moderator_nickname)<dt>최근 관리자 조치</dt><dd>{{ $report->moderator_nickname }} · {{ \Illuminate\Support\Carbon::parse($report->moderated_at, 'UTC')->timezone('Asia/Seoul')->format('Y-m-d H:i') }} KST</dd>@endif
    </dl>
</section>
<section class="admin-request-table-card admin-image-report-actions">
    <h2>신고 상태</h2>
    <form class="admin-inline-action" method="post" action="{{ route('admin.idol-profile-image-reports.status', $report->idx) }}">
        @csrf @method('patch')
        <select name="status" aria-label="신고 상태">
            @foreach (['pending' => '대기', 'reviewing' => '검토 중', 'reviewed' => '처리 완료', 'rejected' => '기각'] as $value => $label)
                <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input name="moderation_note" maxlength="1000" placeholder="처리 메모">
        <button type="submit">상태 저장</button>
    </form>
    @if ($report->current_image_path === $report->reported_image_path && str_starts_with($report->reported_image_path, 'storage/'))
        <h2>프로필 이미지 조치</h2>
        <p>이미지를 공개 프로필에서 제거하고 기존 파일은 관리자만 볼 수 있도록 비공개 보관합니다. 아이돌/그룹은 계속 공개됩니다.</p>
        <form method="post" action="{{ route('admin.idol-profile-image-reports.hide-image', $report->idx) }}" onsubmit="return confirm('신고된 프로필 이미지를 비공개 처리하시겠습니까?')">
            @csrf @method('patch')
            <label>조치 사유<input name="moderation_note" required maxlength="1000" placeholder="비공개 처리 사유"></label>
            <button type="submit">프로필 이미지 비공개 처리</button>
        </form>
    @elseif ($report->current_image_path !== $report->reported_image_path)
        <p class="admin-alert-success">신고된 이미지가 현재 프로필 이미지와 다르므로 새 이미지는 변경하지 않습니다.</p>
    @else
        <p class="admin-alert-error">관리 대상 저장소의 이미지가 아니어서 자동으로 제거할 수 없습니다.</p>
    @endif
</section>
@endsection
