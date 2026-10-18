@extends('layouts.admin')
@section('title','회원 상세')
@section('content')
<header class="admin-page-heading"><div><h1>회원 상세</h1><p>{{ $member->nickname }} · {{ $member->member_code }}</p></div></header>
<section class="admin-detail-card"><dl><dt>이메일</dt><dd>{{ $member->email }}</dd><dt>국가</dt><dd>{{ $member->country_code ?: '-' }}</dd><dt>계정 구분</dt><dd>{{ (int)$member->is_dummy===1?'더미 계정':((int)$member->member_level===10?'관리자':'일반 회원') }}</dd>@if((int)$member->is_dummy===1)<dt>생성 관리자 회원 ID</dt><dd>{{ $member->created_by_member_idx ?: '-' }}</dd>@endif<dt>상태</dt><dd>{{ $member->status==='active'?'정상':'접속제한' }}</dd><dt>현재 하트</dt><dd>{{ number_format($member->heart_balance) }}</dd><dt>가입일</dt><dd>{{ $member->created_at }}</dd><dt>최근 로그인</dt><dd>{{ $member->last_login_at ?: '-' }}</dd></dl></section>
@foreach([['하트 획득/사용 내역',$hearts,'reason'],['팬포스트',$posts,'title'],['로그인 이력',$logins,'result'],['관련 관리자 조치',$actions,'action']] as [$heading,$rows,$field])<section class="admin-dashboard-card admin-member-detail-section"><h2>{{ $heading }}</h2>@forelse($rows as $row)<p><strong>{{ $field === 'action' ? (['dummy_member_created' => '더미 계정 생성', 'member_restricted' => '회원 접근 제한', 'member_restored' => '회원 접근 복원'][$row->action] ?? '관리자 조치') : $row->{$field} }}</strong><span>{{ $row->created_at }}</span></p>@empty<p class="admin-muted">내역이 없습니다.</p>@endforelse</section>@endforeach
@endsection
