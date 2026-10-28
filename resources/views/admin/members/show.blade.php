@extends('layouts.admin')
@section('title','회원 상세')
@section('content')
<header class="admin-page-heading"><div><h1>회원 상세</h1><p><span translate="no"><x-admin-dummy-name :nickname="$member->nickname" /></span> · {{ $member->member_code }}</p></div></header>
<section class="admin-detail-card"><dl><dt>이메일</dt><dd>{{ $member->email }}</dd><dt>국가</dt><dd>{{ $member->country_code ?: '-' }}</dd><dt>계정 구분</dt><dd>{{ (int)$member->is_dummy===1?'더미 계정':((int)$member->member_level===10?'관리자':'일반 회원') }}</dd>@if((int)$member->is_dummy===1)<dt>생성 관리자 회원 ID</dt><dd>{{ $member->created_by_member_idx ?: '-' }}</dd>@endif<dt>상태</dt><dd>{{ $member->status==='active'?'정상':'접속제한' }}</dd><dt>현재 하트</dt><dd>{{ number_format($member->heart_balance) }}</dd><dt>가입일</dt><dd>{{ $member->created_at }}</dd><dt>최근 로그인</dt><dd>{{ $member->last_login_at ?: '-' }}</dd></dl></section>
@foreach([['하트 획득/사용 내역',$hearts,'reason'],['팬포스트',$posts,'title'],['로그인 이력',$logins,'result'],['관련 관리자 조치',$actions,'action']] as [$heading,$rows,$field])<section class="admin-dashboard-card admin-member-detail-section"><h2>{{ $heading }}</h2>@forelse($rows as $row)<p><strong>{{ $field === 'action' ? (['dummy_member_created' => '더미 계정 생성', 'member_restricted' => '회원 접근 제한', 'member_restored' => '회원 접근 복원'][$row->action] ?? '관리자 조치') : $row->{$field} }}</strong><span>{{ $row->created_at }}</span></p>@empty<p class="admin-muted">내역이 없습니다.</p>@endforelse</section>@endforeach
@if ((int) $member->is_dummy === 1)
<section class="admin-dashboard-card admin-member-detail-section">
    <h2>최애 아이돌</h2>
    <p>최애는 회원 공통 정보입니다. 현재는 최애가 하나 이상 등록된 활성 더미 계정의 자동투표에 사용합니다.</p>
    @if (session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
    @if ($errors->any())<div class="admin-alert-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="admin-settings-form" method="post" action="{{ route('admin.members.favorites', $member->idx) }}">
        @csrf
        @method('put')
        @foreach ([1, 2, 3] as $rank)
            @php($field = 'favorite_idol_'.$rank.'_idx')
            <label>최애 {{ $rank }} (선택)
                <select name="{{ $field }}">
                    <option value="">선택 안 함</option>
                    @foreach ($favoriteIdols as $idol)
                        <option value="{{ $idol->idx }}" @selected((string) old($field, $member->{$field}) === (string) $idol->idx)>{{ $idol->name }} #{{ $idol->idx }}{{ $idol->status !== 'active' ? ' (비활성)' : '' }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach
        <button class="admin-settings-save" type="submit">최애 저장</button>
    </form>
</section>
@endif
@endsection
