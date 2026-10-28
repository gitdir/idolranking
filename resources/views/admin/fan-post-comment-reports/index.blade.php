@extends('layouts.admin')

@section('title', '댓글 신고 관리')

@section('content')
<header class="admin-page-heading">
    <div><h1>댓글 신고 관리</h1><p>접수된 댓글 신고를 확인하고 댓글 공개 상태를 관리합니다.</p></div>
</header>

@if (session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
@if ($errors->any())<div class="admin-alert-error">{{ $errors->first() }}</div>@endif

<form class="admin-compact-filter" method="get">
    <input name="q" value="{{ $search }}" placeholder="댓글, 팬 포스트 또는 회원 검색">
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
        <table class="admin-management-table admin-comment-report-table">
            <thead><tr><th>접수일</th><th>신고된 댓글</th><th>작성자</th><th>신고자</th><th>사유 / 상세</th><th>상태</th><th>관리</th></tr></thead>
            <tbody>
            @forelse ($reports as $report)
                <tr>
                    @php($receivedAt = \Illuminate\Support\Carbon::parse($report->created_at, 'UTC')->timezone('Asia/Seoul'))
                    <td><time datetime="{{ $receivedAt->toIso8601String() }}" title="Asia/Seoul (KST)">{{ $receivedAt->format('Y-m-d H:i') }}</time></td>
                    <td>
                        <a class="admin-record-link" href="{{ route('fan-post.show', ['idx' => $report->fan_post_idx]) }}#comments">{{ $report->post_title }}</a>
                        <p class="admin-comment-report-content">{{ $report->content }}</p>
                        @if ($report->comment_deleted_at)
                            <small>{{ $report->last_comment_moderation_action === 'fan_post_comment_hidden' ? '관리자 비공개 처리' : '작성자 삭제' }}</small>
                        @endif
                    </td>
                    <td><x-admin-dummy-name :nickname="$report->author_nickname" /></td>
                    <td><x-admin-dummy-name :nickname="$report->reporter_nickname" /></td>
                    <td>
                        <strong>{{ ['spam' => '스팸', 'harassment_hate' => '괴롭힘 / 혐오', 'inappropriate_content' => '부적절한 콘텐츠', 'other' => '기타'][$report->reason] ?? '기타' }}</strong>
                        <small>{{ $report->details ?: '상세 내용 없음' }}</small>
                    </td>
                    <td><span class="admin-status-badge admin-status-{{ $report->status }}">{{ ['pending' => '대기', 'reviewing' => '검토 중', 'reviewed' => '처리 완료', 'rejected' => '기각'][$report->status] ?? '알 수 없음' }}</span></td>
                    <td class="admin-comment-report-management">
                        <form class="admin-inline-action" method="post" action="{{ route('admin.fan-post-comment-reports.status', $report->idx) }}">
                            @csrf @method('patch')
                            <select name="status" aria-label="신고 상태">
                                @foreach (['pending' => '대기', 'reviewing' => '검토 중', 'reviewed' => '처리 완료', 'rejected' => '기각'] as $value => $label)
                                    <option value="{{ $value }}" @selected($report->status === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit">상태 저장</button>
                        </form>
                        @if ($report->comment_status === 'published' && !$report->comment_deleted_at)
                            <form class="admin-inline-action" method="post" action="{{ route('admin.fan-post-comments.visibility', $report->comment_idx) }}">
                                @csrf @method('patch')
                                <input name="reason" required maxlength="1000" placeholder="비공개 사유">
                                <button type="submit">비공개 처리</button>
                            </form>
                        @elseif ($report->comment_status === 'hidden' && $report->comment_deleted_at && $report->last_comment_moderation_action === 'fan_post_comment_hidden')
                            <form class="admin-inline-action" method="post" action="{{ route('admin.fan-post-comments.restore', $report->comment_idx) }}">
                                @csrf @method('patch')
                                <input name="reason" required maxlength="1000" placeholder="공개 복구 사유">
                                <button type="submit">공개 복구</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td class="admin-table-empty" colspan="7">접수된 댓글 신고가 없습니다.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="admin-native-pagination">{{ $reports->links() }}</div>
</section>
@endsection
