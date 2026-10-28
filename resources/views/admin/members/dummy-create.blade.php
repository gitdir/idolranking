@extends('layouts.admin')
@section('title', '더미 계정 생성')
@section('content')
<header class="admin-page-heading">
    <div><h1>더미 계정 생성</h1><p>더미 계정은 일반 회원 권한으로 생성되며, 활동 보상 하트를 받을 수 없습니다.</p></div>
    <a class="admin-secondary-button" href="{{ route('admin.members.index') }}">회원 목록</a>
</header>

@if ($errors->any())
    <div class="admin-alert-error" role="alert">{{ $errors->first() }}</div>
@endif

<form class="admin-settings-form admin-dummy-account-form" method="post" action="{{ route('admin.members.dummy.store') }}">
    @csrf
    <label>이메일
        <input type="email" name="email" value="{{ old('email') }}" maxlength="254" required autocomplete="off">
    </label>
    <label>닉네임
        <input type="text" name="nickname" value="{{ old('nickname') }}" minlength="2" maxlength="20" required autocomplete="off">
    </label>
    <label>국가
        <select name="country_code" required>
            <option value="">국가 선택</option>
            @foreach ($countries as $country)
                <option value="{{ $country['code'] }}" @selected(old('country_code') === $country['code'])>{{ \App\Support\Countries::localizedName($country['code'], 'ko') }}</option>
            @endforeach
        </select>
    </label>
    @foreach ([1, 2, 3] as $rank)
        <label>최애 {{ $rank }} (선택)
            <select name="favorite_idol_{{ $rank }}_idx">
                <option value="">선택 안 함</option>
                @foreach ($favoriteIdols as $idol)
                    <option value="{{ $idol->idx }}" @selected((string) old('favorite_idol_'.$rank.'_idx') === (string) $idol->idx)>{{ $idol->name }} #{{ $idol->idx }}</option>
                @endforeach
            </select>
        </label>
    @endforeach
    <label>비밀번호
        <input type="password" name="password" minlength="8" maxlength="64" required autocomplete="new-password">
    </label>
    <label>비밀번호 확인
        <input type="password" name="password_confirmation" minlength="8" maxlength="64" required autocomplete="new-password">
    </label>
    <p class="admin-muted">비밀번호는 8~64자이며 영문자와 숫자를 각각 하나 이상 포함해야 합니다. 생성 후 계정 상세에서 접근을 제한하거나 복원할 수 있습니다.</p>
    <div class="admin-settings-actions">
        <a class="admin-secondary-button" href="{{ route('admin.members.index') }}">취소</a>
        <button class="admin-primary-button" type="submit">더미 계정 생성</button>
    </div>
</form>
@endsection
