@extends('layouts.admin')

@section('title', $title.' | IDOL RANKING 관리자')

@section('content')
    <div class="admin-page-heading"><div><h1>{{ $title }}</h1><p>{{ $description }}</p></div></div>
    <section class="admin-placeholder-card">
        <span aria-hidden="true">◇</span>
        <h2>{{ $title }}</h2>
        <p>향후 관리 기능을 추가할 수 있도록 메뉴와 경로가 준비되어 있습니다.</p>
    </section>
@endsection
