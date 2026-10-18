@extends('layouts.app')

@section('title', __('ui.register_idol').' | IdolRanking')

@section('content')
<div class="fan-post-create idol-request-create">
    <header class="fan-post-create-header">
        <h1>{{ __('ui.idol_request_title') }}</h1>
        <p>{{ __('ui.idol_request_description') }}</p>
    </header>

    @if (session('success'))<div class="idol-request-success" role="status">{{ session('success') }}</div>@endif

    <div class="fan-post-create-layout">
        <form class="fan-post-create-form" method="post" action="{{ route('idol-registration.store') }}" enctype="multipart/form-data" data-idol-request-form novalidate>
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            <input type="hidden" name="duplicate_search_token" value="{{ old('duplicate_search_token', '') }}" data-duplicate-search-token>
            @if ($errors->any())<div class="fan-post-create-errors" role="alert"><strong>{{ __('ui.check_form') }}</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <div class="fan-post-create-field idol-request-type-row">
                <div class="fan-post-create-label">{{ __('ui.request_type') }} <span>*</span></div>
                <div class="idol-request-type-options">
                    @foreach (['boy' => __('ui.boy'), 'boy_group' => __('ui.boy_group'), 'girl' => __('ui.girl'), 'girl_group' => __('ui.girl_group')] as $value => $label)
                        <label><input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type', 'boy') === $value) required><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>

            @php($selectedGroupLabel = $selectedGroup ? ($selectedGroup->english_name ?: $selectedGroup->name) : '')
            @if ($selectedGroup && $selectedGroup->name && mb_strtolower($selectedGroup->name) !== mb_strtolower($selectedGroupLabel))
                @php($selectedGroupLabel .= ' · '.$selectedGroup->name)
            @endif
            <div class="idol-request-fields-grid idol-request-check-fields-grid">
            <div class="fan-post-create-field idol-request-name-field idol-request-field-full"><label for="idol-request-english-name">{{ __('ui.english_name') }} <span>*</span></label><div><input id="idol-request-english-name" name="english_name" value="{{ old('english_name') }}" maxlength="200" required placeholder="{{ __('ui.enter_english_name') }}"><div class="idol-duplicate-search-controls"><button type="button" data-duplicate-search-url="{{ route('idol-registration.duplicates') }}" data-duplicate-search>{{ __('ui.idol_duplicate_search') }}</button><span>{{ __('ui.idol_duplicate_search_hint') }}</span></div></div></div>
            <section class="idol-duplicate-panel idol-request-field-full is-neutral" data-duplicate-panel data-state="neutral" aria-live="polite">
                <div class="idol-duplicate-status" role="status">
                    <h2 data-duplicate-status>{{ __('js.idol_duplicate_status_initial') }}</h2>
                    <p data-duplicate-status-description hidden></p>
                </div>
                <div class="idol-duplicate-candidates" data-duplicate-candidates></div>
                <div class="idol-duplicate-other-section" data-duplicate-other-section hidden>
                    <h2>{{ __('ui.idol_duplicate_other_category_heading') }}</h2>
                    <div class="idol-duplicate-candidates" data-duplicate-other-candidates></div>
                </div>
                <label class="idol-duplicate-confirm" data-duplicate-confirm hidden><input type="checkbox" name="not_duplicate_confirmed" value="1" data-not-duplicate-confirmed @checked(old('not_duplicate_confirmed'))><span>{{ __('ui.idol_duplicate_confirm') }}</span></label>
            </section>
            </div>
            <div class="idol-request-progressive-fields" data-registration-details hidden>
            <div class="idol-request-fields-grid">
            @php($defaultSourceLanguage = in_array(app()->getLocale(), ['ko', 'en'], true) ? app()->getLocale() : 'en')
            @php($sourceLanguage = old('source_language_code', $defaultSourceLanguage))
            <div class="fan-post-create-field idol-request-source-language-field idol-request-field-full">
                <label for="idol-request-source-language">{{ __('ui.source_language') }} <span>*</span></label>
                <div>
                    <select id="idol-request-source-language" name="source_language_code" required disabled data-progressive-required>
                        <option value="" disabled @selected(!in_array($sourceLanguage, ['ko', 'en', 'ja', 'zh-CN', 'zh-TW'], true))>{{ __('ui.source_language_select') }}</option>
                        <option value="ko" @selected($sourceLanguage === 'ko')>{{ __('ui.language_korean') }} (KO)</option>
                        <option value="en" @selected($sourceLanguage === 'en')>{{ __('ui.language_english') }} (EN)</option>
                        <option value="ja" @selected($sourceLanguage === 'ja')>{{ __('ui.language_japanese') }} (JA)</option>
                        <option value="zh-CN" @selected($sourceLanguage === 'zh-CN')>{{ __('ui.language_chinese_simplified') }} (ZH-CN)</option>
                        <option value="zh-TW" @selected($sourceLanguage === 'zh-TW')>{{ __('ui.language_chinese_traditional') }} (ZH-TW)</option>
                    </select>
                    <small>{{ __('ui.source_language_help') }}</small>
                </div>
            </div>
            <div class="fan-post-create-field idol-request-name-field idol-request-field-full"><label for="idol-request-name">{{ __('ui.name_korean') }}</label><div><input id="idol-request-name" name="name" value="{{ old('name') }}" maxlength="200" placeholder="{{ __('ui.enter_korean_name') }}"></div></div>
            <div class="fan-post-create-field idol-request-field-full" data-individual-only data-group-field>
                <label for="idol-request-group-search">{{ __('ui.group') }}</label>
                <div class="idol-group-autocomplete" data-group-autocomplete data-search-url="{{ route('idol-registration.groups') }}">
                    <input id="idol-request-group-search" type="search" value="{{ $selectedGroupLabel }}" maxlength="200" autocomplete="off" placeholder="{{ __('ui.group_search_placeholder') }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="idol-group-results" aria-describedby="idol-group-help" aria-label="{{ __('ui.group_search_label') }}" data-selected-gender="{{ $selectedGroup->gender ?? '' }}" data-group-search>
                    <input type="hidden" name="group_idx" value="{{ $selectedGroup->idx ?? '' }}" data-group-id>
                    <button type="button" class="idol-group-no-group" data-group-none>{{ __('ui.no_group_option') }}</button>
                    <div class="idol-group-results" id="idol-group-results" role="listbox" hidden data-group-results></div>
                    <small id="idol-group-help">{{ __('ui.group_search_help') }}</small>
                    <p class="idol-group-search-status" aria-live="polite" data-group-status></p>
                </div>
            </div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-birth-date">{{ __('ui.profile_label_date_of_birth') }}</label><div><input id="idol-request-birth-date" type="date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-height">{{ __('ui.height_cm') }}</label><div><input id="idol-request-height" type="number" name="height_cm" value="{{ old('height_cm') }}" min="50" max="300" step="0.1" placeholder="e.g. 178"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-weight">{{ __('ui.weight_kg') }}</label><div><input id="idol-request-weight" type="number" name="weight_kg" value="{{ old('weight_kg') }}" min="10" max="500" step="0.1" placeholder="e.g. 65"></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-blood">{{ __('ui.profile_label_blood_type') }}</label><div><select id="idol-request-blood" name="blood_type"><option value="">{{ __('ui.select_blood_type') }}</option>@foreach (['A', 'B', 'AB', 'O'] as $blood)<option value="{{ $blood }}" @selected(old('blood_type') === $blood)>{{ $blood }}</option>@endforeach</select></div></div>
            <div class="fan-post-create-field"><label for="idol-request-agency">{{ __('ui.profile_label_agency') }}</label><div><input id="idol-request-agency" name="agency" value="{{ old('agency') }}" maxlength="200" placeholder="{{ __('ui.enter_agency') }}"></div></div>
            <div class="fan-post-create-field idol-request-field-full" data-group-full><label>{{ __('ui.profile_label_debut_dates') }}</label><div>@include('partials.debut-date-inputs', ['debutDateRows' => old('debut_dates', [['country_code' => '', 'debut_date' => '']]), 'idPrefix' => 'idol-request-debut-country', 'namePrefix' => 'debut_dates', 'admin' => false])<small>{{ __('ui.debut_dates_help') }}</small></div></div>
            <div class="fan-post-create-field" data-individual-only><label for="idol-request-position">{{ __('ui.profile_label_position') }}</label><div><input id="idol-request-position" name="position" value="{{ old('position') }}" maxlength="200" placeholder="{{ __('ui.position_example') }}"></div></div>
            <div class="fan-post-create-field" data-group-only hidden><label for="idol-request-genre">{{ __('ui.profile_label_genre') }}</label><div><input id="idol-request-genre" name="genre" value="{{ old('genre') }}" maxlength="200" placeholder="{{ __('ui.enter_genre') }}"></div></div>
            <div class="fan-post-create-field" data-group-only hidden><label for="idol-request-leader">{{ __('ui.profile_label_leader') }}</label><div><input id="idol-request-leader" name="leader" value="{{ old('leader') }}" maxlength="200" placeholder="{{ __('ui.enter_leader') }}"></div></div>
            <div class="fan-post-create-field idol-request-field-full"><x-social-link-inputs id-prefix="idol-request-sns" :label="__('ui.profile_label_sns')" /></div>
            </div>

            <div class="fan-post-create-field fan-post-media-field">
                <div class="fan-post-create-label">{{ __('ui.profile_image') }} <span>*</span></div>
                <div>
                    <label class="fan-post-upload" for="idol-request-image"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg><strong>{{ __('ui.upload_image_prompt') }}</strong><span>{{ __('ui.image_jpg_png_size') }}</span></label>
                    <input id="idol-request-image" class="visually-hidden" type="file" name="profile_image" accept="image/jpeg,image/png" data-image-input required disabled data-progressive-required>
                    <div class="fan-post-image-selection" data-image-selection hidden><img alt="{{ __('ui.cropped_profile_image') }}" data-image-preview><div><button type="button" data-crop-open>{{ __('ui.edit_crop') }}</button><button type="button" data-image-remove>{{ __('ui.remove') }}</button></div></div>
                </div>
            </div>
            <div class="fan-post-create-field idol-request-introduction-field"><label for="idol-request-content">{{ __('ui.short_introduction') }} <span>*</span></label><div><textarea id="idol-request-content" name="content" maxlength="1000" rows="6" required disabled data-progressive-required placeholder="{{ __('ui.idol_introduction_placeholder') }}">{{ old('content') }}</textarea><output class="fan-post-counter" for="idol-request-content" data-intro-count>{{ mb_strlen(old('content', '')) }}/1000</output></div></div>

            <div class="fan-post-create-actions"><button type="submit">{{ __('ui.submit_request') }}</button><a href="{{ route('home') }}">{{ __('ui.cancel') }}</a></div>
            </div>
        </form>

        <aside class="fan-post-guidelines" aria-label="{{ __('ui.registration_guidelines') }}">
            <section>
                <h2><span>{{ __('ui.before_submit') }}</span></h2>
                <ul><li>{{ __('ui.check_not_registered') }}</li><li>{{ __('ui.official_profile_info') }}</li><li>{{ __('ui.requests_reviewed') }}</li></ul>
                @if ($approvalRewardAmount > 0)
                    <div class="idol-request-reward-notice">
                        <strong>{{ __('ui.approval_reward', ['amount' => number_format($approvalRewardAmount)]) }}</strong>
                        <p>{{ __('ui.reward_after_approval') }}</p>
                    </div>
                @endif
            </section>
            <section><h2><span>{{ __('ui.profile_image_guidelines') }}</span></h2><ul><li>{{ __('ui.use_clear_image') }}</li><li>{{ __('ui.one_image_upload') }}</li><li>{{ __('ui.square_crop_image') }}</li></ul></section>
            <section><h2><span>{{ __('ui.community_guidelines') }}</span></h2><ul><li>{{ __('ui.respectful_factual_intro') }}</li><li>{{ __('ui.no_private_info') }}</li></ul></section>
        </aside>
    </div>
</div>

<dialog class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="idol-request-crop-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header><h2 id="idol-request-crop-title">{{ __('ui.crop_image') }}</h2><button type="button" data-crop-cancel aria-label="{{ __('ui.close_crop_image') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button></header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom"><button type="button" data-zoom-out aria-label="{{ __('ui.zoom_out') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button><input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="{{ __('ui.image_zoom') }}"><button type="button" data-zoom-in aria-label="{{ __('ui.zoom_in') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg></button></div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>{{ __('ui.cancel') }}</button><button type="button" data-crop-apply>{{ __('ui.apply') }}</button></div>
    </form>
</dialog>
@endsection
