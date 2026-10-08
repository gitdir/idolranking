@extends('layouts.admin')
@section('title','닉네임 금지어 관리')
@section('content')
<header class="admin-page-heading"><div><h1>닉네임 금지어 관리</h1><p>회원가입과 닉네임 변경에 적용할 금지어를 관리합니다.</p></div></header>
@if(session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
<form class="admin-compact-filter" method="post" action="{{ route('admin.restricted-nicknames.store') }}">@csrf<input name="word" required maxlength="50" placeholder="금지어"><select name="match_type"><option value="exact">완전 일치</option><option value="contains">포함</option></select><button>추가</button></form>
<section class="admin-request-table-card">
    <div class="admin-table-scroll">
        <table class="admin-management-table admin-restricted-nickname-table">
            <colgroup><col class="admin-restricted-no"><col><col class="admin-restricted-match"><col class="admin-restricted-status"><col class="admin-restricted-actions-column"></colgroup>
            <thead><tr><th>NO</th><th>금지어</th><th>일치 방식</th><th>상태</th><th>관리</th></tr></thead>
            <tbody>
            @forelse($rules as $rule)
                @php($updateFormId = 'restricted-nickname-update-'.$rule->idx)
                <tr>
                    <td><x-admin-row-number :paginator="$rules" :index="$loop->index" /></td>
                    <td><input form="{{ $updateFormId }}" name="word" value="{{ $rule->word }}" required maxlength="50"></td>
                    <td><select form="{{ $updateFormId }}" name="match_type"><option value="exact" @selected($rule->match_type==='exact')>완전 일치</option><option value="contains" @selected($rule->match_type==='contains')>포함</option></select></td>
                    <td><select form="{{ $updateFormId }}" name="is_enabled"><option value="1" @selected($rule->is_enabled)>사용</option><option value="0" @selected(!$rule->is_enabled)>중지</option></select></td>
                    <td><div class="admin-restricted-row-actions"><form id="{{ $updateFormId }}" method="post" action="{{ route('admin.restricted-nicknames.update',$rule->idx) }}">@csrf @method('put')<button type="submit">저장</button></form><form method="post" action="{{ route('admin.restricted-nicknames.destroy',$rule->idx) }}">@csrf @method('delete')<button class="admin-danger-button" type="submit">삭제</button></form></div></td>
                </tr>
            @empty
                <tr><td colspan="5" class="admin-table-empty">등록된 금지어가 없습니다.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
