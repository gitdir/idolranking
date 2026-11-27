@extends('layouts.admin')
@section('title', '예약 게시물 미리보기')
@section('content')
<header class="admin-page-heading"><div><h1>예약 게시물 미리보기</h1><p>이 내용은 공개 전 관리자에게만 표시됩니다.</p></div></header>
<article class="admin-distribution-preview">
    <dl><dt>제목</dt><dd>{{ $post->title }}</dd><dt>작성자</dt><dd><x-admin-dummy-name :nickname="$post->nickname" /></dd><dt>분류</dt><dd>{{ $post->idol_name ?? '기타' }}</dd><dt>대기 등록 시각</dt><dd>{{ $post->queued_at }}</dd><dt>상태</dt><dd>예약 대기</dd>
    @if($post->media_type === 'image' && $post->image_url)<dt>이미지</dt><dd><img src="{{ $post->image_url }}" alt="게시물 이미지"></dd>@elseif($post->media_type === 'youtube' && $post->youtube_video_id)<dt>동영상</dt><dd><a href="https://www.youtube.com/watch?v={{ urlencode($post->youtube_video_id) }}" target="_blank" rel="noopener noreferrer">YouTube 영상 보기</a></dd>@endif
    <dt>내용</dt><dd class="admin-preserve-text">{{ $post->content }}</dd></dl>
    <div class="admin-distribution-actions"><a href="{{ route('admin.fan-post-distribution.index') }}">대기 목록</a>
        <form method="POST" action="{{ route('admin.fan-post-distribution.publish', $post->idx) }}">@csrf @method('PATCH')<button type="submit">즉시 공개</button></form>
        <form method="POST" action="{{ route('admin.fan-post-distribution.cancel', $post->idx) }}">@csrf @method('PATCH')<button type="submit">예약 취소</button></form>
    </div>
</article>
@endsection
