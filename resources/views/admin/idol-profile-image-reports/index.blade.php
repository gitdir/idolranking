@extends('layouts.admin')

@section('title', '프로필 이미지 권리 신고')

@section('content')
<header class="admin-page-heading"><div><h1>프로필 이미지 권리 신고</h1><p>아이돌 및 그룹 프로필 이미지에 접수된 권리 침해 신고를 검토합니다.</p></div></header>
@if (session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
@if ($errors->any())<div class="admin-alert-error">{{ $errors->first() }}</div>@endif
<form class="admin-compact-filter" method="get">
    <select name="status" aria-label="신고 상태">
        <option value="">전체 상태</option>
        @foreach (['pending' => '대기', 'reviewing' => '검토 중', 'reviewed' => '처리 완료', 'rejected' => '기각'] as $value => $label)
            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit">검색</button>
</form>
<section class="admin-request-table-card">
    <div class="admin-table-scroll">
        <table class="admin-management-table">
            <thead><tr><th>접수 시각</th><th>대상</th><th>신고 이미지</th><th>신고자</th><th>사유</th><th>상태</th><th>관리</th></tr></thead>
            <tbody>
            @forelse ($reports as $report)
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($report->created_at, 'UTC')->timezone('Asia/Seoul')->format('Y-m-d H:i') }}</td>
                    <td><a class="admin-record-link" href="{{ route('admin.idol-profile-image-reports.show', $report->idx) }}">{{ $report->english_name ?: $report->idol_name }}</a><small>{{ $report->idol_type === 'group' ? '그룹' : '개인 아이돌' }} #{{ $report->idol_idx }}</small></td>
                    <td><a href="{{ route('admin.idol-profile-image-reports.evidence', $report->idx) }}" target="_blank" rel="noopener noreferrer">이미지 확인</a></td>
                    <td><x-admin-dummy-name :nickname="$report->reporter_nickname" /></td>
                    <td><strong>{{ ['copyright' => '저작권 침해', 'portrait_rights' => '초상권 침해', 'other' => '기타'][$report->reason] ?? '기타' }}</strong><small>{{ $report->details ?: '상세 내용 없음' }}</small></td>
                    <td><span class="admin-status-badge admin-status-{{ $report->status }}">{{ ['pending' => '대기', 'reviewing' => '검토 중', 'reviewed' => '처리 완료', 'rejected' => '기각'][$report->status] ?? '알 수 없음' }}</span></td>
                    <td><a class="admin-record-link" href="{{ route('admin.idol-profile-image-reports.show', $report->idx) }}">검토 및 조치</a></td>
                </tr>
            @empty
                <tr><td class="admin-table-empty" colspan="7">접수된 프로필 이미지 권리 신고가 없습니다.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-native-pagination">{{ $reports->links() }}</div>
</section>
@endsection
