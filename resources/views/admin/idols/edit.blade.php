@extends('layouts.admin')

@section('title', ($idol->type === 'group' ? '그룹' : '아이돌').' 정보 수정')

@section('content')
@php
    $isIndividual = $idol->type === 'individual';
@endphp
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
        <label><span>영문 이름 *</span><input name="english_name" value="{{ old('english_name', $idol->english_name) }}" maxlength="200" required></label>
        <label><span>한국어 이름</span><input name="name" value="{{ old('name', $idol->name) }}" maxlength="200"></label>

        @if($isIndividual)
            @php
                $selectedGroupLabel = '';
                if ($selectedGroup) {
                    $selectedEnglish = trim((string) ($selectedGroup->localized_english_name ?: $selectedGroup->english_name ?: $selectedGroup->name));
                    $selectedKorean = trim((string) ($selectedGroup->localized_korean_name ?: $selectedGroup->name));
                    $selectedGroupLabel = $selectedEnglish;
                    if ($selectedKorean !== '' && mb_strtolower($selectedKorean) !== mb_strtolower($selectedEnglish)) $selectedGroupLabel .= ' · '.$selectedKorean;
                    if ($selectedGroup->status !== 'active') $selectedGroupLabel .= ' (비공개)';
                }
            @endphp
            <div class="admin-review-full admin-idol-group-field">
                <label for="admin-idol-group-search">그룹</label>
                <div class="idol-group-autocomplete" data-admin-group-autocomplete data-search-url="{{ route('idol-registration.groups') }}" data-request-type="{{ $idol->gender === 'male' ? 'boy' : 'girl' }}">
                    <input id="admin-idol-group-search" name="group_search" type="search" value="{{ old('group_search', $selectedGroupLabel) }}" maxlength="200" autocomplete="off" placeholder="그룹 이름을 검색하세요" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="admin-idol-group-results" aria-describedby="admin-idol-group-help" data-group-search translate="no" data-selected-gender="{{ $selectedGroup->gender ?? '' }}" data-no-group-selected="false">
                    <input type="hidden" name="group_idx" value="{{ old('group_idx', $groupIdx) }}" data-group-id>
                    <button type="button" class="idol-group-no-group" data-group-none>소속 그룹 없음</button>
                    <div class="idol-group-results" id="admin-idol-group-results" role="listbox" hidden data-group-results></div>
                    <small id="admin-idol-group-help">영문명, 한글명, 다국어 이름 또는 별칭으로 검색할 수 있습니다.</small>
                    <p class="idol-group-search-status" aria-live="polite" data-group-status></p>
                </div>
            </div>
            <label><span>생년월일</span><input type="date" name="birth_date" value="{{ old('birth_date', $idol->birth_date) }}"></label>
            <label><span>키 (cm)</span><input type="number" step="0.01" min="50" max="300" name="height_cm" value="{{ old('height_cm', $idol->height_cm) }}"></label>
            <label><span>몸무게 (kg)</span><input type="number" step="0.01" min="10" max="500" name="weight_kg" value="{{ old('weight_kg', $idol->weight_kg) }}"></label>
            <label><span>혈액형</span><select name="blood_type"><option value="">선택 안 함</option>@foreach(['A', 'B', 'AB', 'O'] as $blood)<option value="{{ $blood }}" @selected(old('blood_type', $idol->blood_type) === $blood)>{{ $blood }}</option>@endforeach</select></label>
        @else
        @endif

        <label><span>소속사</span><input name="agency" value="{{ old('agency', $idol->agency) }}" maxlength="200"></label>
        <div class="admin-review-full"><label class="idol-debut-date-label">데뷔일</label>@include('partials.debut-date-inputs', ['debutDateRows' => $debutDateRows, 'idPrefix' => 'admin-idol-debut-country', 'namePrefix' => 'debut_dates', 'admin' => true])</div>
        @if($isIndividual)
            <label><span>포지션</span><input name="position" value="{{ old('position', $idol->position) }}" maxlength="200"></label>
        @else
            <label><span>장르</span><input name="genre" value="{{ old('genre', $idol->genre) }}" maxlength="200"></label>
            <label><span>리더</span><input name="leader" value="{{ old('leader', $idol->leader) }}" maxlength="200"></label>
        @endif
        <div class="admin-review-full"><x-social-link-inputs :values="$idol->snsLinkValues" label="SNS" id-prefix="admin-idol-sns" :admin="true" /></div>

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

    @php($nextLocalizationIndex = count($multilingualLocalizations))
    @php($canonicalEnglishName = old('english_name', $idol->english_name))
    <section class="admin-multilingual-editor" data-localization-editor data-canonical-english-name="{{ $canonicalEnglishName }}">
        <header class="admin-multilingual-heading">
            <div><h2>다국어 정보 관리</h2><p>아래 항목은 선택 언어에만 적용되는 선택 정보입니다. 비어 있는 항목은 기본 프로필 정보를 유지하며, 영어 대표 이름은 위 영문 이름과 연동됩니다.</p></div>
            <button class="admin-action-button" type="button" data-add-localization>언어 추가</button>
        </header>
        <input type="hidden" name="multilingual_data_present" value="1">
        <p class="admin-multilingual-feedback" data-language-feedback role="status" aria-live="polite"></p>
        <p class="admin-multilingual-empty" data-localization-empty @if(count($multilingualLocalizations) > 0) hidden @endif>등록된 다국어 정보가 없습니다. 오른쪽 위 언어 추가 버튼으로 첫 언어를 추가할 수 있습니다.</p>
        <div class="admin-multilingual-rows" data-localization-rows data-next-index="{{ $nextLocalizationIndex }}">
            @foreach($multilingualLocalizations as $index => $localization)
                @include('admin.idols.partials.localization-row', ['index' => $index, 'localization' => $localization, 'canonicalEnglishName' => $canonicalEnglishName, 'isIndividual' => $isIndividual])
            @endforeach
        </div>
        <template data-localization-template>
            @include('admin.idols.partials.localization-row', [
                'index' => '__INDEX__',
                'localization' => ['language_code' => '', 'localized_name' => '', 'description' => '', 'aliases' => []],
                'canonicalEnglishName' => $canonicalEnglishName,
                'isIndividual' => $isIndividual,
                'template' => true,
            ])
        </template>
    </section>

    <div class="admin-review-actions">
        <a class="admin-action-button" href="{{ route($isIndividual ? 'admin.idols.index' : 'admin.groups.index') }}">목록으로</a>
        <button class="admin-primary-button" type="submit">저장</button>
    </div>
</form>

<x-square-image-crop-dialog id="admin-idol-crop-dialog" />
@endsection
