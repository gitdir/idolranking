@extends('layouts.admin')
@section('title', '하트 지급 설정')
@section('content')
<header class="admin-page-heading"><div><h1>하트 지급 설정</h1><p>앞으로 지급되는 하트의 금액, 일일 제한, 사용 여부를 관리합니다.</p></div></header>
@if(session('admin_success'))<div class="admin-alert-success" role="status">{{ session('admin_success') }}</div>@endif
@if($errors->any())<div class="admin-alert-error" role="alert">{{ $errors->first() }}</div>@endif
@if(session('admin_success'))<div class="admin-alert-success" role="status">{{ session('admin_success') }}</div>@endif
@if($errors->any())<div class="admin-alert-error" role="alert">{{ $errors->first() }}</div>@endif
<section class="admin-request-table-card">
    <div class="admin-table-scroll"><table class="admin-management-table"><thead><tr><th>지급 액션</th><th>지급 하트</th><th>1일 제한 (0:무제한)</th><th>사용 여부</th><th>설명</th><th>관리</th></tr></thead><tbody>
    @foreach($actions as $action)
        @php($formId = 'reward-setting-'.$action->action_code)
        <tr>
            <td><strong>{{ $action->name }}</strong><small>{{ $action->action_code }}</small></td>
            <td>@if(in_array($action->action_code, ['daily_attendance', 'comment_create'], true))<input form="{{ $formId }}" type="hidden" name="heart_amount" value="{{ $action->heart_amount }}"><div class="admin-heart-range-inputs"><label>{{ __('ui.minimum_hearts', [], 'ko') }} <input form="{{ $formId }}" class="admin-compact-input" type="number" name="min_heart_amount" min="0" max="2147483647" value="{{ $action->min_heart_amount }}" required></label><label>{{ __('ui.maximum_hearts', [], 'ko') }} <input form="{{ $formId }}" class="admin-compact-input" type="number" name="max_heart_amount" min="0" max="2147483647" value="{{ $action->max_heart_amount }}" required></label></div>@else<input form="{{ $formId }}" class="admin-compact-input" type="number" name="heart_amount" min="0" value="{{ $action->heart_amount }}" required>@endif</td>
            <td>@if($action->action_code === 'daily_attendance')<input form="{{ $formId }}" type="hidden" name="daily_limit" value="1">{{ __('ui.once_per_utc_day', [], 'ko') }}@elseif($action->limit_type === 'daily')<input form="{{ $formId }}" class="admin-compact-input" type="number" name="daily_limit" min="0" value="{{ $action->daily_limit }}" required>@elseif($action->limit_type === 'account')계정당 1회@else 신청 건당 1회 @endif</td>
            <td><label class="admin-inline-check"><input form="{{ $formId }}" type="checkbox" name="is_enabled" value="1" @checked($action->is_enabled)> 사용</label></td>
            <td>{{ $action->description }}</td><td><form id="{{ $formId }}" method="POST" action="{{ route('admin.hearts.settings.update', $action->action_code) }}">@csrf @method('PUT')<button class="admin-primary-button admin-heart-reward-save" type="submit">저장</button></form></td>
        </tr>
    @endforeach
    </tbody></table></div>
</section>
@endsection
