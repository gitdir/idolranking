@extends('layouts.admin')

@section('title', ($idol->type === 'group' ? '그룹' : '아이돌').' 정보 수정')

@section('content')
@php($isIndividual = $idol->type === 'individual')
<header class="admin-page-heading">
    <div><h1>{{ $isIndividual ? '아이돌 정보 수정' : '그룹 정보 수정' }}</h1><p>등록 정보와 공개 상태를 수정합니다.</p></div>
</header>

@if(session('admin_success'))<div class="admin-alert-success">{{ session('admin_success') }}</div>@endif
@if($errors->any())<div class="admin-alert-error"><strong>입력 내용을 확인해 주세요.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<form class="admin-idol-request-form" method="post" enctype="multipart/form-data" action="{{ route('admin.idols.update', $idol->idx) }}" data-admin-image-crop="#admin-idol-crop-dialog" data-existing-image="{{ \App\Support\ManagedImageStorage::isAvailable($idol->image_path) ? asset($idol->image_path) : '' }}">
    @csrf
    @method('put')
    <section class="admin-idol-request-summary">
        <img src="{{ \App\Support\ManagedImageStorage::publicUrl($idol->image_path) }}" alt="{{ $idol->name }} 프로필 이미지">
        <dl>
            <div><dt>번호</dt><dd>#{{ $idol->idx }}</dd></div>
            <div><dt>유형</dt><dd>{{ $idol->gender === 'male' ? '남자' : '여자' }}{{ $isIndividual ? '' : ' 그룹' }}</dd></div>
            <div><dt>현재 상태</dt><dd><span class="admin-status-badge admin-status-{{ $idol->status }}">{{ $idol->status === 'active' ? '공개' : '비공개' }}</span></dd></div>
        </dl>
    </section>

    <div class="admin-review-grid">
        <label><span>이름 *</span><input name="name" value="{{ old('name', $idol->name) }}" maxlength="200" required></label>
        <label><span>영문 이름 *</span><input name="english_name" value="{{ old('english_name', $idol->english_name) }}" maxlength="200" required></label>

        @if($isIndividual)
            <label class="admin-review-full"><span>그룹</span><select name="group_idx"><option value="">소속 그룹 없음</option>@foreach($groups as $group)<option value="{{ $group->idx }}" @selected((string) old('group_idx', $groupIdx) === (string) $group->idx)>{{ $group->name }}{{ $group->status === 'inactive' ? ' (비공개)' : '' }}</option>@endforeach</select></label>
            <label><span>생년월일</span><input type="date" name="birth_date" value="{{ old('birth_date', $idol->birth_date) }}"></label>
            <label><span>국적</span><input name="nationality" value="{{ old('nationality', $idol->nationality) }}" maxlength="100"></label>
            <label><span>키 (cm)</span><input type="number" step="0.01" min="50" max="300" name="height_cm" value="{{ old('height_cm', $idol->height_cm) }}"></label>
            <label><span>몸무게 (kg)</span><input type="number" step="0.01" min="10" max="500" name="weight_kg" value="{{ old('weight_kg', $idol->weight_kg) }}"></label>
            <label><span>혈액형</span><select name="blood_type"><option value="">선택 안 함</option>@foreach(['A', 'B', 'AB', 'O'] as $blood)<option value="{{ $blood }}" @selected(old('blood_type', $idol->blood_type) === $blood)>{{ $blood }}</option>@endforeach</select></label>
            <label><span>포지션</span><input name="position" value="{{ old('position', $idol->position) }}" maxlength="200"></label>
        @else
            <label class="admin-review-full"><span>국가</span><input name="nationality" value="{{ old('nationality', $idol->nationality) }}" maxlength="100"></label>
        @endif

        <label><span>소속사</span><input name="agency" value="{{ old('agency', $idol->agency) }}" maxlength="200"></label>
        <label><span>데뷔일</span><input type="date" name="debut_date" value="{{ old('debut_date', $idol->debut_date) }}"></label>
        <label class="admin-review-full"><span>SNS</span><input name="sns" value="{{ old('sns', $idol->sns) }}" maxlength="1000"></label>

        <div class="admin-review-full admin-image-field">
            <span>프로필 이미지 변경</span>
            <label class="admin-image-upload" for="admin-idol-profile-image">JPG 또는 PNG 이미지 선택</label>
            <input id="admin-idol-profile-image" class="square-image-input" type="file" name="profile_image" accept="image/jpeg,image/png" data-image-input>
            <div class="fan-post-image-selection" data-image-selection @if(!\App\Support\ManagedImageStorage::isAvailable($idol->image_path)) hidden @endif>
                <img src="{{ \App\Support\ManagedImageStorage::isAvailable($idol->image_path) ? asset($idol->image_path) : '' }}" alt="선택한 프로필 이미지 미리보기" data-image-preview>
                <div><button type="button" data-crop-open>편집 / 자르기</button><button type="button" data-image-remove>제거</button></div>
            </div>
            <small>변경하지 않으면 현재 이미지를 유지합니다. JPG/PNG, 최대 5MB</small>
        </div>

        <label class="admin-review-full"><span>짧은 소개 *</span><textarea name="content" rows="6" maxlength="1000" required>{{ old('content', $idol->content) }}</textarea></label>
        <label><span>공개 상태</span><select name="status"><option value="active" @selected(old('status', $idol->status) === 'active')>공개</option><option value="inactive" @selected(old('status', $idol->status) === 'inactive')>비공개</option></select></label>
        <label><span>상태 변경 사유</span><input name="visibility_reason" maxlength="1000" placeholder="공개 상태를 바꿀 때 입력"></label>
    </div>

    <div class="admin-review-actions">
        <a class="admin-action-button" href="{{ route($isIndividual ? 'admin.idols.index' : 'admin.groups.index') }}">목록으로</a>
        <button class="admin-primary-button" type="submit">저장</button>
    </div>
</form>

<x-square-image-crop-dialog id="admin-idol-crop-dialog" />
@endsection
