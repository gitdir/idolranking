@extends('layouts.app')
@php($editing = $editingPost !== null)
@section('title', ($editing ? __('ui.edit_fan_post') : __('ui.create_fan_post_title')).' | IdolRanking')

@section('content')
<div class="fan-post-create">
    <header class="fan-post-create-header">
        <h1>{{ $editing ? __('ui.edit_fan_post') : __('ui.create_fan_post_title') }}</h1>
        <p>{{ __('ui.share_favorite') }}</p>
    </header>
    <div class="fan-post-create-layout">
        <form class="fan-post-create-form{{ $editing ? ' fan-post-edit-form' : '' }}" method="POST" action="{{ $editing ? route('fan-post.update', ['idx' => $editingPost['idx']]) : route('fan-post.store') }}" @unless($editing) enctype="multipart/form-data" data-existing-image="" data-validation-alert-message="{{ __('ui.fan_post_validation_alert') }}" @endunless data-fan-post-create novalidate>
            @csrf
            @unless($editing)
                <input type="hidden" name="request_key" value="{{ $requestKey }}">
            @endunless
            @if($editing) @method('PUT') @endif
            @if ($editing && $errors->any())<div class="fan-post-create-errors" role="alert"><strong>{{ __('ui.check_form') }}</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @unless($editing)
            <div class="fan-post-create-field idol-request-type-row">
                <div class="fan-post-create-label">{{ __('ui.request_type') }} <span>*</span></div>
                <div class="idol-request-type-options" data-idol-category-options data-idol-category-url="{{ route('fan-post.create.idols') }}" data-group-label="{{ __('ui.group_type') }}">
                    @foreach (['boy' => __('ui.boy'), 'boy_group' => __('ui.boy_group'), 'girl' => __('ui.girl'), 'girl_group' => __('ui.girl_group')] as $value => $label)
                        <label><input type="radio" name="idol_category" value="{{ $value }}" @checked($idolCategory === $value) required><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </div>
            @endunless
            <div class="fan-post-create-field">
                <label for="fan-post-idol">{{ __('ui.idol') }} <span>*</span></label>
                <div>
                    @if($editing)
                        <select id="fan-post-idol" class="fan-post-sort-select" disabled aria-disabled="true">
                            <option translate="no" selected>{{ $editingPost['idol_name'] }}{{ $editingPost['idol_type'] === 'group' ? ' ('.__('ui.group_type').')' : '' }}</option>
                        </select>
                        <small>{{ __('ui.fan_post_edit_fields_immutable') }}</small>
                    @else
                        <select id="fan-post-idol" class="fan-post-sort-select" name="idol_idx" required data-idol-select @disabled($idolCategory === null || $idols->isEmpty())><option value="" @selected(!$selectedIdolIdx)>{{ __('ui.select_idol') }}</option>@foreach($idols as $idol)<option translate="no" value="{{ $idol->idx }}" @selected((string) $selectedIdolIdx === (string) $idol->idx)>{{ $idol->name }}{{ $idol->type === 'group' ? ' ('.__('ui.group_type').')' : '' }}</option>@endforeach</select><small>{{ __('ui.choose_post_idol') }}</small>
                        <div class="fan-post-registration-help" data-idol-registration-help>
                            <button class="fan-post-registration-toggle" type="button" data-registration-toggle aria-expanded="false">{{ __('ui.fan_post_registration_prompt') }}</button>
                            <section class="fan-post-registration-panel" data-registration-panel hidden>
                                <h3>{{ __('ui.fan_post_registration_title') }}</h3>
                                <p>{{ __('ui.fan_post_registration_description') }}</p>
                                <div class="idol-request-reward-notice fan-post-registration-reward">
                                    <strong><span class="idol-request-reward-label">{{ __('ui.approval_reward') }}</span><span class="idol-request-reward-amount"><svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg><span>+{{ number_format($approvalRewardAmount) }}</span></span></strong>
                                    <p>{{ __('ui.reward_after_approval') }}</p>
                                </div>
                                <a class="fan-post-registration-link" href="{{ route('idol-registration.create') }}">{{ __('ui.fan_post_registration_button') }}</a>
                            </section>
                        </div>
                    @endif
                </div>
            </div>
            @unless($editing)
            <div data-dependent-fields @if(!$idolCategory || !$selectedIdolIdx) hidden @endif>
            @endunless
            <div class="fan-post-create-field">
                <label for="fan-post-title">{{ __('ui.title') }} <span>*</span></label>
                <div><input id="fan-post-title" name="title" maxlength="100" value="{{ old('title', $editingPost['title'] ?? '') }}" placeholder="{{ __('ui.title_placeholder') }}" required><output class="fan-post-counter" for="fan-post-title">{{ mb_strlen(old('title', $editingPost['title'] ?? '')) }}/100</output></div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-language">{{ __('ui.post_language') }} <span>*</span></label>
                <div>
                    @if($editing)
                        <select id="fan-post-language" class="fan-post-sort-select" disabled aria-disabled="true">
                            <option selected>{{ $editingPost['language_name'] }}{{ $editingPost['language_code'] !== '' ? ' ('.strtoupper($editingPost['language_code']).')' : '' }}</option>
                        </select>
                        <small>{{ __('ui.fan_post_edit_fields_immutable') }}</small>
                    @else
                        <select id="fan-post-language" class="fan-post-sort-select" name="language_code" required>
                            @foreach($postLanguages as $code => $name)
                                <option value="{{ $code }}" @selected(old('language_code', ($randomDummyMode ?? false) ? 'ko' : app()->getLocale()) === $code)>{{ $name }} ({{ strtoupper($code) }})</option>
                            @endforeach
                        </select><small>{{ __('ui.post_language_help') }}</small>
                    @endif
                </div>
            </div>
            <div class="fan-post-create-field fan-post-media-field">
                <div id="fan-post-media-label" class="fan-post-create-label">{{ __('ui.media') }}</div>
                <div role="group" aria-labelledby="fan-post-media-label">
                    @if ($editing)
                        @if (($editingPost['media_type'] ?? null) === 'image')
                            <div class="fan-post-image-selection fan-post-image-readonly">
                                <img src="{{ $editingPost['image_url'] }}" alt="{{ __('ui.selected_fan_post_image') }}">
                                <p>{{ __('ui.fan_post_media_immutable') }}</p>
                            </div>
                        @elseif (($editingPost['media_type'] ?? null) === 'youtube')
                            <p class="fan-post-media-readonly-note"><a href="{{ $editingPost['youtube_url'] }}" target="_blank" rel="noopener noreferrer">{{ __('ui.youtube_video') }}</a> · {{ __('ui.fan_post_media_immutable') }}</p>
                        @else
                            <p class="fan-post-media-readonly-note">{{ __('ui.fan_post_media_immutable') }}</p>
                        @endif
                    @else
                        <input type="hidden" name="media_type" value="{{ old('media_type', $editingPost['media_type'] ?? 'text') }}" data-media-type>
                        <div class="fan-post-media-tabs" role="tablist" aria-label="{{ __('ui.media_type') }}">
                            <button type="button" role="tab" data-media-tab="image" @disabled($editing && empty($editingPost['image_url']))><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg>{{ __('ui.image') }}</button>
                            <button type="button" role="tab" data-media-tab="youtube"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3Z"/></svg>{{ __('ui.youtube_video') }}</button>
                        </div>
                        <section data-media-panel="image">
                            @unless($editing)<label class="fan-post-upload" for="fan-post-image"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg><strong>{{ __('ui.upload_image_prompt') }}</strong><span>{{ __('ui.image_help') }}</span></label>@endunless
                            <input id="fan-post-image" class="visually-hidden" type="file" name="image" accept="image/jpeg,image/png,image/gif" data-image-input @disabled($editing)>
                            <div class="fan-post-image-selection" data-image-selection @if(empty($editingPost['image_url'])) hidden @endif><img src="{{ $editingPost['image_url'] ?? '' }}" alt="{{ __('ui.selected_fan_post_image') }}" data-image-preview><div><button type="button" data-crop-open @disabled($editing)>{{ __('ui.edit_crop') }}</button><button type="button" data-image-remove @disabled($editing)>{{ __('ui.remove') }}</button></div></div>
                        </section>
                        <section data-media-panel="youtube" hidden><label class="visually-hidden" for="fan-post-youtube">{{ __('ui.youtube_url') }}</label><input id="fan-post-youtube" name="youtube_url" value="{{ old('youtube_url', $editingPost['youtube_url'] ?? '') }}" placeholder="https://www.youtube.com/watch?v=..."><small>{{ __('ui.youtube_url_help') }}</small></section>
                    @endif
                </div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-content">{{ __('ui.content') }} <span>*</span></label>
                <div><textarea id="fan-post-content" name="content" maxlength="2000" rows="6" placeholder="{{ __('ui.content_placeholder') }}" required>{{ old('content', $editingPost['content'] ?? '') }}</textarea><output class="fan-post-counter" for="fan-post-content">{{ mb_strlen(old('content', $editingPost['content'] ?? '')) }}/2000</output></div>
            </div>
            <div class="fan-post-create-field">
                <label for="fan-post-tag-entry">{{ __('ui.tags') }}</label>
                <div><div class="fan-post-tags-input" data-tags-input><div data-tag-chips></div><input id="fan-post-tag-entry" placeholder="{{ __('ui.tag_placeholder') }}"></div><input type="hidden" name="tags" value="{{ old('tags', $editingPost['tags'] ?? '') }}" data-tags-value><small>{{ __('ui.tag_help') }}</small></div>
            </div>
            @if(!$editing && ($isAdmin ?? false))
                <div class="fan-post-create-field fan-post-auto-distribute">
                    <span aria-hidden="true"></span>
                    <div><label><input type="checkbox" name="auto_distribute" value="1" @checked(old('auto_distribute', true))> 자동 분산 발행</label><small>선택하면 게시물이 즉시 공개되지 않고 자동 분산 발행 대기열에 등록됩니다.</small></div>
                </div>
            @endif
            <div class="fan-post-create-actions"><button type="submit">{{ $editing ? __('ui.save_changes') : __('ui.post_fan_post') }}</button><a href="{{ $editing ? route('fan-post.show', ['idx' => $editingPost['idx']]) : route('home') }}">{{ __('ui.cancel') }}</a></div>
            @unless($editing)
            </div>
            @endunless
        </form>

        <aside class="fan-post-guidelines" aria-label="{{ __('ui.fan_post_guidelines') }}">
            <section><h2>{{ __('ui.posting_tips') }}</h2><ul><li>{{ __('ui.be_respectful_positive') }}</li><li>{{ __('ui.use_relevant_tags') }}</li><li>{{ __('ui.avoid_harmful_content') }}</li><li>{{ __('ui.fan_post_representative_image_tip') }}</li></ul>
                @if(!$editing && $fanPostRewardAmount > 0)
                    <div class="idol-request-reward-notice fan-post-writing-reward"><p>{{ __('ui.fan_post_reward_tip_before') }}<span class="fan-post-writing-reward-amount"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>+{{ number_format($fanPostRewardAmount) }}</span>{{ __('ui.fan_post_reward_tip_after') }}@if($fanPostRewardDailyLimit > 0){{ ' ' }}<span>{{ __('ui.fan_post_reward_daily_limit', ['limit' => number_format($fanPostRewardDailyLimit)]) }}</span>@endif</p></div>
                @endif
            </section>
            @unless($editing)<section><h2>{{ __('ui.media_guidelines') }}</h2><h3>{{ __('ui.image') }}</h3><ul><li>JPG, PNG or GIF</li><li>{{ __('ui.maximum_5mb') }}</li><li>{{ __('ui.one_image') }}</li><li>{{ __('ui.images_editable') }}</li></ul><h3>{{ __('ui.youtube_video') }}</h3><ul><li>{{ __('ui.youtube_url_help') }}</li><li>{{ __('ui.one_youtube_video') }}</li><li>{{ __('ui.youtube_embedded') }}</li></ul></section>@endunless
            <section><h2>{{ __('ui.community_guidelines') }}</h2><ul><li>{{ __('ui.no_hate_speech') }}</li><li>{{ __('ui.no_illegal_content') }}</li><li>{{ __('ui.friendly_community') }}</li></ul></section>
        </aside>
    </div>
</div>

@unless($editing)
<dialog class="fan-post-crop-dialog" data-crop-dialog aria-labelledby="crop-image-title">
    <form method="dialog" class="fan-post-crop-modal">
        <header><h2 id="crop-image-title">{{ __('ui.crop_image') }}</h2><button type="button" data-crop-cancel aria-label="{{ __('ui.close_crop_image') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 6 12 12M18 6 6 18"/></svg></button></header>
        <div class="fan-post-crop-stage" data-crop-stage><canvas data-crop-canvas></canvas><span aria-hidden="true"></span></div>
        <div class="fan-post-crop-zoom"><button type="button" data-zoom-out aria-label="{{ __('ui.zoom_out') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 12h14"/></svg></button><input type="range" min="1" max="3" step="0.01" value="1" data-crop-zoom aria-label="{{ __('ui.image_zoom') }}"><button type="button" data-zoom-in aria-label="{{ __('ui.zoom_in') }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 5v14M5 12h14"/></svg></button></div>
        <div class="fan-post-crop-actions"><button type="button" data-crop-cancel>{{ __('ui.cancel') }}</button><button type="button" data-crop-apply>{{ __('ui.apply') }}</button></div>
    </form>
</dialog>
@endunless
@if(!$editing && $errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-fan-post-create]');
            const errors = @json($errors->all());
            const firstErrorKey = @json($errors->keys()[0] ?? null);
            window.alert([@json(__('ui.fan_post_validation_alert')), '', ...errors.map((message) => `- ${message}`)].join('\n'));
            const field = firstErrorKey ? form?.elements.namedItem(firstErrorKey) : null;
            const target = field instanceof RadioNodeList ? field[0] : field;
            if (target instanceof HTMLElement && !target.disabled) {
                target.focus({ preventScroll: true });
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
@endif
@endsection
