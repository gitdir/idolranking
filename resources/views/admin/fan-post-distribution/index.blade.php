@extends('layouts.admin')
@section('title', '예약발행 관리')
@section('content')
<header class="admin-page-heading"><div><h1>예약발행 관리</h1><p>관리자 작성 게시물을 대기열 순서대로 자동 분산 공개합니다.</p></div></header>

@if($errors->any())
    <div class="admin-distribution-errors" role="alert"><strong>입력 내용을 확인해 주세요.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if(session('admin_success'))<p class="admin-distribution-success" role="status">{{ session('admin_success') }}</p>@endif

<section class="admin-distribution-settings">
    <h2>발행 설정</h2>
    <form method="POST" action="{{ route('admin.fan-post-distribution.settings') }}">
        @csrf @method('PUT')
        <label>발행 간격(분)<input type="number" name="interval_minutes" min="1" max="1440" required value="{{ old('interval_minutes', $settings['interval_minutes']) }}"></label>
        <label>간격당 최대 게시물 수<input type="number" name="max_posts" min="1" max="100" required value="{{ old('max_posts', $settings['max_posts']) }}"></label>
        <label>발행 시작 시각<input type="time" name="start_time" required value="{{ old('start_time', $settings['start_time']) }}"></label>
        <label>발행 종료 시각<input type="time" name="end_time" required value="{{ old('end_time', $settings['end_time']) }}"></label>
        <p>허용 시각은 한국 표준시(Asia/Seoul) 기준이며, 설정 변경은 이미 대기 중인 게시물 순서를 바꾸지 않습니다.</p>
        <button type="submit">설정 저장</button>
    </form>
</section>

<header class="admin-distribution-list-heading"><h2>미공개 대기 게시물</h2><span>{{ number_format($posts->total()) }}건</span></header>
<section class="admin-request-table-card admin-distribution-table"><div class="admin-table-scroll"><table><thead><tr><th>순번</th><th>제목</th><th>작성자</th><th>대기 등록 시각</th><th>상태</th><th>관리</th></tr></thead><tbody>
@forelse($posts as $post)
<tr>
    <td>{{ $posts->firstItem() + $loop->index }}</td><td>{{ $post->title }}</td><td>{{ $post->nickname }}</td>
    <td>{{ $post->queued_at }}</td><td><span class="admin-distribution-status">예약 대기</span></td>
    <td><div class="admin-distribution-actions">
        <a href="{{ route('admin.fan-post-distribution.preview', $post->idx) }}">미리보기</a>
        <form method="POST" action="{{ route('admin.fan-post-distribution.publish', $post->idx) }}">@csrf @method('PATCH')<button type="submit">즉시 공개</button></form>
        <form method="POST" action="{{ route('admin.fan-post-distribution.cancel', $post->idx) }}">@csrf @method('PATCH')<button type="submit">예약 취소</button></form>
    </div></td>
</tr>
@empty
<tr><td colspan="6" class="admin-table-empty">대기 중인 게시물이 없습니다.</td></tr>
@endforelse
</tbody></table></div><div class="admin-native-pagination">{{ $posts->links() }}</div></section>
@endsection
