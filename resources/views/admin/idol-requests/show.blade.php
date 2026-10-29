@extends('layouts.admin')

@section('title', '아이돌 등록 신청 상세 | IDOL RANKING 관리자')

@section('content')
    @php
        $isPending = $registrationRequest->status === 'pending';
        $isIndividual = $registrationRequest->type === 'individual';
        $statusLabels = ['pending' => '대기', 'approved' => '승인', 'rejected' => '반려'];
        $typeLabel = ($registrationRequest->gender === 'male' ? '남자' : '여자').($isIndividual ? '' : ' 그룹');
        $submittedLanguageRows = $requestLocalizations->filter(fn ($rows) => $rows->contains('text_role', 'submitted'));
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

    <section class="admin-duplicate-review" aria-labelledby="admin-duplicate-review-title">
        <header>
            <div><h2 id="admin-duplicate-review-title">중복 후보 확인</h2><p>이름이 일치하거나 비슷한 활성 아이돌 및 그룹입니다. 다른 유형의 결과는 참고용이며 승인 제한에 사용되지 않습니다.</p></div>
        </header>
        <div class="admin-duplicate-review-sections">
            <section aria-labelledby="admin-same-category-title">
                <h3 id="admin-same-category-title">같은 유형</h3>
                @forelse ($sameCategoryCandidates as $candidate)
                    <article class="admin-duplicate-candidate">
                        <a class="admin-duplicate-candidate-image" href="{{ $candidate['url'] }}"><img src="{{ $candidate['image_url'] }}" alt="" loading="lazy"></a>
                        <div><small>{{ $candidate['category'] }}</small><strong>{{ $candidate['name'] }}</strong>
                            @if ($candidate['english_name'] && $candidate['english_name'] !== $candidate['name'])<span>{{ $candidate['english_name'] }}</span>@endif
                            @if ($candidate['group_name'])<span>소속 그룹: {{ $candidate['group_name'] }}</span>@endif
                            @if ($candidate['exact'])<b>동일 이름</b>@endif
                        </div>
                        <a class="admin-duplicate-candidate-detail" href="{{ $candidate['url'] }}">상세 보기</a>
                    </article>
                @empty
                    <p class="admin-duplicate-empty">같은 유형에서 일치하는 후보가 없습니다.</p>
                @endforelse
            </section>
            <section aria-labelledby="admin-other-category-title">
                <h3 id="admin-other-category-title">다른 유형에 등록된 대상</h3>
                @forelse ($otherCategoryCandidates as $candidate)
                    <article class="admin-duplicate-candidate">
                        <a class="admin-duplicate-candidate-image" href="{{ $candidate['url'] }}"><img src="{{ $candidate['image_url'] }}" alt="" loading="lazy"></a>
                        <div><small>{{ $candidate['category'] }}</small><strong>{{ $candidate['name'] }}</strong>
                            @if ($candidate['english_name'] && $candidate['english_name'] !== $candidate['name'])<span>{{ $candidate['english_name'] }}</span>@endif
                            @if ($candidate['group_name'])<span>소속 그룹: {{ $candidate['group_name'] }}</span>@endif
                        </div>
                        <a class="admin-duplicate-candidate-detail" href="{{ $candidate['url'] }}">상세 보기</a>
                    </article>
                @empty
                    <p class="admin-duplicate-empty">다른 유형에서 일치하는 후보가 없습니다.</p>
                @endforelse
            </section>
        </div>
    </section>

    <section class="admin-idol-request-form admin-request-localization-panel" aria-labelledby="submitted-localizations-title">
        <h2 id="submitted-localizations-title">신청자가 제출한 원문</h2>
        <p>아래 원문은 신청 당시 내용이며 수정되지 않습니다. 언어별 검토 내용은 승인 정보에 반영됩니다.</p>
        @forelse ($submittedLanguageRows as $languageCode => $rows)
            @php
                $submitted = $rows->firstWhere('text_role', 'submitted');
                $languageLabels = ['en' => '영어', 'ko' => '한국어', 'ja' => '일본어', 'zh-CN' => '중국어 (간체)', 'zh-TW' => '중국어 (번체)', 'und' => '언어 미확인'];
            @endphp
            @if ($submitted)
                <article class="admin-submitted-language">
                    <h3>{{ $languageLabels[$languageCode] ?? $languageCode }}</h3>
                    @if ($submitted->localized_name !== null)<div><strong>이름</strong><p>{{ $submitted->localized_name }}</p></div>@endif
                    @if ($submitted->description !== null)<div><strong>소개</strong><p>{{ $submitted->description }}</p></div>@endif
                    @foreach (['agency' => '소속사', 'position' => '포지션', 'genre' => '장르', 'leader' => '리더'] as $field => $label)
                        @if ($submitted->{$field} !== null)<div><strong>{{ $label }}</strong><p>{{ $submitted->{$field} }}</p></div>@endif
                    @endforeach
                </article>
            @endif
        @empty
            <div class="admin-submitted-language">
                <h3>기존 신청 내용 (언어 미확인)</h3>
                <div><strong>이름</strong><p>{{ $registrationRequest->name }}</p></div>
                <div><strong>영문 이름</strong><p>{{ $registrationRequest->english_name }}</p></div>
                <div><strong>소개</strong><p>{{ $registrationRequest->content }}</p></div>
            </div>
        @endforelse
    </section>

    <form class="admin-idol-request-form" method="post" enctype="multipart/form-data" action="{{ route('admin.idol-requests.approve', $registrationRequest->idx) }}" @if($isPending) data-admin-image-crop="#admin-request-crop-dialog" data-existing-image="{{ \App\Support\ManagedImageStorage::isAvailable($registrationRequest->image_path) ? asset($registrationRequest->image_path) : '' }}" @endif>
        @csrf
        <div class="admin-review-notice">
            {{ $isPending ? '아래에 표시된 최종 값으로 실제 아이돌 또는 그룹을 등록합니다.' : '처리 시점에 확정된 등록 정보입니다.' }}
            @if ($isPending)
                <br>{{ $approvalRewardAmount > 0 ? '승인 시 신청자에게 '.number_format($approvalRewardAmount).' Heart가 지급됩니다.' : '현재 승인 보상이 없어 Heart가 지급되지 않습니다.' }}
            @endif
        </div>

        <section class="admin-reviewed-localizations" aria-labelledby="reviewed-localizations-title">
            <h2 id="reviewed-localizations-title">관리자 검토 다국어 정보</h2>
            <p>제출 원문은 보존됩니다. 입력한 검토 값이 승인 정보에 반영되며, 비워 둔 값은 해당 언어의 제출 원문을 사용합니다.</p>
            <div class="admin-review-grid">
                @foreach ($reviewLanguageCodes as $languageCode)
                    @php
                        $languageLabels = ['en' => '영어', 'ko' => '한국어', 'ja' => '일본어', 'zh-CN' => '중국어 (간체)', 'zh-TW' => '중국어 (번체)', 'und' => '언어 미확인'];
                        $languageLabel = $languageLabels[$languageCode] ?? $languageCode;
                    @endphp
                    @php $reviewedRow = $requestLocalizations->get($languageCode, collect())->firstWhere('text_role', 'reviewed'); @endphp
                    <fieldset class="admin-reviewed-language">
                        <legend>{{ $languageLabel }}</legend>
                        @if (in_array($languageCode, ['en', 'ko'], true))
                            <label><span>이름</span><input name="reviewed_localizations[{{ $languageCode }}][localized_name]" value="{{ old('reviewed_localizations.'.$languageCode.'.localized_name', $reviewedRow->localized_name ?? '') }}" maxlength="200" @disabled(!$isPending)></label>
                            <label><span>소개</span><textarea name="reviewed_localizations[{{ $languageCode }}][description]" rows="4" maxlength="1000" @disabled(!$isPending)>{{ old('reviewed_localizations.'.$languageCode.'.description', $reviewedRow->description ?? '') }}</textarea></label>
                        @endif
                        <label><span>소속사</span><input name="reviewed_localizations[{{ $languageCode }}][agency]" value="{{ old('reviewed_localizations.'.$languageCode.'.agency', $reviewedRow->agency ?? '') }}" maxlength="200" @disabled(!$isPending)></label>
                        @if ($isIndividual)
                            <label><span>포지션</span><input name="reviewed_localizations[{{ $languageCode }}][position]" value="{{ old('reviewed_localizations.'.$languageCode.'.position', $reviewedRow->position ?? '') }}" maxlength="200" @disabled(!$isPending)></label>
                        @else
                            <label><span>장르</span><input name="reviewed_localizations[{{ $languageCode }}][genre]" value="{{ old('reviewed_localizations.'.$languageCode.'.genre', $reviewedRow->genre ?? '') }}" maxlength="200" @disabled(!$isPending)></label>
                            <label><span>리더</span><input name="reviewed_localizations[{{ $languageCode }}][leader]" value="{{ old('reviewed_localizations.'.$languageCode.'.leader', $reviewedRow->leader ?? '') }}" maxlength="200" @disabled(!$isPending)></label>
                        @endif
                    </fieldset>
                @endforeach
            </div>
        </section>

        <div class="admin-review-grid">
            <label><span>영문 이름 *</span><input name="english_name" value="{{ old('english_name', $registrationRequest->english_name) }}" maxlength="200" @disabled(!$isPending) required></label>
            <label><span>등록 이름</span><input name="name" value="{{ old('name', $registrationRequest->name) }}" maxlength="200" @disabled(!$isPending)></label>

            @if ($isIndividual)
                <label class="admin-review-full"><span>그룹</span><select name="group_idx" @disabled(!$isPending)><option value="">소속 그룹 없음</option>@foreach ($groups as $group)<option value="{{ $group->idx }}" @selected((string) old('group_idx', $registrationRequest->group_idx) === (string) $group->idx)>{{ $group->name }}</option>@endforeach</select></label>
                <label><span>생년월일</span><input type="date" name="birth_date" value="{{ old('birth_date', $registrationRequest->birth_date) }}" @disabled(!$isPending)></label>
                <label><span>키 (cm)</span><input type="number" name="height_cm" value="{{ old('height_cm', $registrationRequest->height_cm) }}" min="50" max="300" step="0.01" @disabled(!$isPending)></label>
                <label><span>몸무게 (kg)</span><input type="number" name="weight_kg" value="{{ old('weight_kg', $registrationRequest->weight_kg) }}" min="10" max="500" step="0.01" @disabled(!$isPending)></label>
                <label><span>혈액형</span><select name="blood_type" @disabled(!$isPending)><option value="">선택 안 함</option>@foreach (['A', 'B', 'AB', 'O'] as $bloodType)<option value="{{ $bloodType }}" @selected(old('blood_type', $registrationRequest->blood_type) === $bloodType)>{{ $bloodType }}</option>@endforeach</select></label>
            @else
            @endif

            <label><span>소속사</span><input name="agency" value="{{ old('agency', $registrationRequest->agency) }}" maxlength="200" @disabled(!$isPending)></label>
            @if ($requestDebuts->isNotEmpty())
                <section class="admin-review-full admin-request-debuts"><h2>데뷔일</h2><ul>@foreach ($requestDebuts as $debut)<li>{{ \App\Support\Countries::localizedName($debut->country_code, 'ko') }} · {{ \Illuminate\Support\Carbon::parse($debut->debut_date)->format('Y-m-d') }}</li>@endforeach</ul></section>
            @endif
            @if ($isIndividual)
                <label><span>포지션</span><input name="position" value="{{ old('position', $registrationRequest->position) }}" maxlength="200" @disabled(!$isPending)></label>
            @else
                <label><span>장르</span><input name="genre" value="{{ old('genre', $registrationRequest->genre) }}" maxlength="200" @disabled(!$isPending)></label>
                <label><span>리더</span><input name="leader" value="{{ old('leader', $registrationRequest->leader) }}" maxlength="200" @disabled(!$isPending)></label>
            @endif
            <div class="admin-review-full"><x-social-link-inputs :values="$registrationRequest->snsLinkValues" :disabled="!$isPending" label="SNS" id-prefix="admin-request-sns" :admin="true" /></div>

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

            <label class="admin-review-full"><span>짧은 소개</span><textarea name="content" rows="6" maxlength="1000" @disabled(!$isPending)>{{ old('content', $registrationRequest->content) }}</textarea></label>
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
