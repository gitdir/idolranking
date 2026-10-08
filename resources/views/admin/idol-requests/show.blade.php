@extends('layouts.admin')

@section('title', '아이돌 등록 신청 상세 | IDOL RANKING 관리자')

@section('content')
    @php
        $isPending = $registrationRequest->status === 'pending';
        $isIndividual = $registrationRequest->type === 'individual';
        $statusLabels = ['pending' => '대기', 'approved' => '승인', 'rejected' => '반려'];
        $typeLabel = ($registrationRequest->gender === 'male' ? '남자' : '여자').($isIndividual ? '' : ' 그룹');
    @endphp

    <div class="admin-page-heading">
        <div><h1>아이돌 등록 신청 상세</h1><p>신청 정보를 확인하고 최종 등록 값을 검토합니다.</p></div>
        <nav aria-label="현재 위치"><a href="{{ route('admin.idol-requests.index') }}">아이돌 등록 신청</a><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg><strong>상세</strong></nav>
    </div>

    @if (session('admin_success'))
        <div class="admin-alert-success" role="status">{{ session('admin_success') }}</div>
    @endif
    @if ($errors->any())
        <div class="admin-alert-error" role="alert">
            <strong>입력 내용을 확인해 주세요.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="admin-idol-request-summary">
        <x-admin-request-image :path="$registrationRequest->image_path" :type="$registrationRequest->type" alt="{{ $registrationRequest->name }} 프로필 이미지" />
        <dl>
            <div><dt>신청 번호</dt><dd>#{{ $registrationRequest->idx }}</dd></div>
            <div><dt>신청 상태</dt><dd><span class="admin-status-badge admin-status-{{ $registrationRequest->status }}">{{ $statusLabels[$registrationRequest->status] ?? $registrationRequest->status }}</span></dd></div>
            <div><dt>신청일</dt><dd>{{ \Illuminate\Support\Carbon::parse($registrationRequest->created_at)->format('Y-m-d H:i') }}</dd></div>
            <div><dt>신청자</dt><dd>{{ $registrationRequest->applicant_name ?: '회원' }}</dd></div>
            <div><dt>신청자 이메일</dt><dd>{{ $registrationRequest->applicant_email ?: '-' }}</dd></div>
            <div><dt>유형</dt><dd>{{ $typeLabel }}</dd></div>
            @if (!$isPending)
                <div><dt>처리일</dt><dd>{{ $registrationRequest->reviewed_at ? \Illuminate\Support\Carbon::parse($registrationRequest->reviewed_at)->format('Y-m-d H:i') : '-' }}</dd></div>
                <div><dt>처리 관리자</dt><dd>{{ $registrationRequest->reviewer_name ?: '-' }}</dd></div>
            @endif
            @if ($registrationRequest->status === 'approved')
                <div><dt>승인 결과</dt><dd>{{ $isIndividual ? '아이돌' : '그룹' }} #{{ $registrationRequest->approved_idol_idx }} {{ $registrationRequest->approved_idol_name }}</dd></div>
            @elseif ($registrationRequest->status === 'rejected')
                <div class="admin-summary-wide"><dt>반려 사유</dt><dd class="admin-preserve-text">{{ $registrationRequest->rejection_reason }}</dd></div>
            @endif
        </dl>
    </section>

    <form class="admin-idol-request-form" method="post" enctype="multipart/form-data" action="{{ route('admin.idol-requests.approve', $registrationRequest->idx) }}" @if($isPending) data-admin-image-crop="#admin-request-crop-dialog" data-existing-image="{{ \App\Support\ManagedImageStorage::isAvailable($registrationRequest->image_path) ? asset($registrationRequest->image_path) : '' }}" @endif>
        @csrf
        <div class="admin-review-notice">
            {{ $isPending ? '아래에 표시된 최종 값으로 실제 아이돌 또는 그룹을 등록합니다.' : '처리 시점에 확정된 등록 정보입니다.' }}
            @if ($isPending)
                <br>{{ $approvalRewardAmount > 0 ? '승인 시 신청자에게 '.number_format($approvalRewardAmount).' Heart가 지급됩니다.' : '현재 승인 보상이 없어 Heart가 지급되지 않습니다.' }}
            @endif
        </div>

        <div class="admin-review-grid">
            <label><span>이름 *</span><input name="name" value="{{ old('name', $registrationRequest->name) }}" maxlength="200" @disabled(!$isPending) required></label>
            <label><span>영문 이름 *</span><input name="english_name" value="{{ old('english_name', $registrationRequest->english_name) }}" maxlength="200" @disabled(!$isPending) required></label>

            @if ($isIndividual)
                <label class="admin-review-full"><span>그룹</span><select name="group_idx" @disabled(!$isPending)><option value="">소속 그룹 없음</option>@foreach ($groups as $group)<option value="{{ $group->idx }}" @selected((string) old('group_idx', $registrationRequest->group_idx) === (string) $group->idx)>{{ $group->name }}</option>@endforeach</select></label>
                <label><span>생년월일</span><input type="date" name="birth_date" value="{{ old('birth_date', $registrationRequest->birth_date) }}" @disabled(!$isPending)></label>
                <label><span>국적</span><input name="nationality" value="{{ old('nationality', $registrationRequest->nationality) }}" maxlength="100" @disabled(!$isPending)></label>
                <label><span>키 (cm)</span><input type="number" name="height_cm" value="{{ old('height_cm', $registrationRequest->height_cm) }}" min="50" max="300" step="0.01" @disabled(!$isPending)></label>
                <label><span>몸무게 (kg)</span><input type="number" name="weight_kg" value="{{ old('weight_kg', $registrationRequest->weight_kg) }}" min="10" max="500" step="0.01" @disabled(!$isPending)></label>
                <label><span>혈액형</span><select name="blood_type" @disabled(!$isPending)><option value="">선택 안 함</option>@foreach (['A', 'B', 'AB', 'O'] as $bloodType)<option value="{{ $bloodType }}" @selected(old('blood_type', $registrationRequest->blood_type) === $bloodType)>{{ $bloodType }}</option>@endforeach</select></label>
                <label><span>포지션</span><input name="position" value="{{ old('position', $registrationRequest->position) }}" maxlength="200" @disabled(!$isPending)></label>
            @else
                <label class="admin-review-full"><span>국가</span><input name="nationality" value="{{ old('nationality', $registrationRequest->nationality) }}" maxlength="100" @disabled(!$isPending)></label>
            @endif

            <label><span>소속사</span><input name="agency" value="{{ old('agency', $registrationRequest->agency) }}" maxlength="200" @disabled(!$isPending)></label>
            <label><span>데뷔일</span><input type="date" name="debut_date" value="{{ old('debut_date', $registrationRequest->debut_date) }}" @disabled(!$isPending)></label>
            <label class="admin-review-full"><span>SNS</span><input name="sns" value="{{ old('sns', $registrationRequest->sns) }}" maxlength="1000" @disabled(!$isPending)></label>

            @if($isPending)
                <div class="admin-review-full admin-image-field">
                    <span>프로필 이미지 변경</span>
                    <label class="admin-image-upload" for="admin-request-profile-image">JPG 또는 PNG 이미지 선택</label>
                    <input id="admin-request-profile-image" class="square-image-input" type="file" name="profile_image" accept="image/jpeg,image/png" data-image-input>
                    <div class="fan-post-image-selection" data-image-selection>
                        <x-admin-request-image :path="$registrationRequest->image_path" :type="$registrationRequest->type" alt="선택한 프로필 이미지 미리보기" data-image-preview />
                        <div><button type="button" data-crop-open>편집 / 자르기</button><button type="button" data-image-remove>제거</button></div>
                    </div>
                    <small>변경하지 않으면 신청 이미지를 그대로 사용합니다. JPG/PNG, 최대 5MB</small>
                </div>
            @endif

            <label class="admin-review-full"><span>짧은 소개 *</span><textarea name="content" rows="6" maxlength="1000" @disabled(!$isPending) required>{{ old('content', $registrationRequest->content) }}</textarea></label>
        </div>

        @if ($isPending)
            <div class="admin-review-actions">
                <a class="admin-action-button" href="{{ route('admin.idol-requests.index') }}">목록으로</a>
                <button class="admin-danger-button" type="button" data-open-admin-dialog="reject-idol-request-dialog">반려</button>
                <button class="admin-action-button" type="submit" formaction="{{ route('admin.idol-requests.save', $registrationRequest->idx) }}">수정 내용 저장</button>
                <button class="admin-primary-button" type="submit">승인</button>
            </div>
        @else
            <div class="admin-review-actions"><a class="admin-action-button" href="{{ route('admin.idol-requests.index', ['tab' => $registrationRequest->status]) }}">목록으로</a></div>
        @endif
    </form>

    @if ($isPending)
        <x-square-image-crop-dialog id="admin-request-crop-dialog" />

        <dialog class="admin-dialog" id="reject-idol-request-dialog" aria-labelledby="reject-idol-request-title" @if($errors->has('rejection_reason')) data-open-on-load @endif>
            <form method="post" action="{{ route('admin.idol-requests.reject', $registrationRequest->idx) }}">
                @csrf
                <h2 id="reject-idol-request-title">아이돌 등록 신청 반려</h2>
                <p>반려 사유는 신청 회원에게 안내할 수 있는 내용으로 입력해 주세요.</p>
                <label><span>반려 사유 *</span><textarea name="rejection_reason" rows="5" maxlength="1000" placeholder="반려 사유를 입력해 주세요." required>{{ old('rejection_reason') }}</textarea></label>
                <div class="admin-dialog-actions">
                    <button class="admin-action-button" type="button" data-close-admin-dialog>취소</button>
                    <button class="admin-danger-button" type="submit">반려 처리</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
