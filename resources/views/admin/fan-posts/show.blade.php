@extends('layouts.admin')
@section('title', '팬포스트 확인')
@section('content')
<header class="admin-page-heading"><div><h1>팬포스트 확인</h1><p>비공개 콘텐츠를 포함한 관리자 검토 화면입니다.</p></div></header>
<article class="admin-detail-card"><dl><dt>제목</dt><dd>{{ $post->title }}</dd><dt>작성자</dt><dd><x-admin-dummy-name :nickname="$post->nickname" /></dd><dt>아이돌</dt><dd>{{ $post->idol_name }}</dd><dt>공개 상태</dt><dd>{{ $post->status === 'published' ? '공개' : '비공개' }}</dd><dt>작성일</dt><dd>{{ $post->created_at }}</dd><dt>내용</dt><dd class="admin-preserve-text">{{ $post->content }}</dd></dl></article>
@endsection
