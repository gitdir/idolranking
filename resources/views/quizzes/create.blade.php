@extends('layouts.app')

@section('title', isset($editingQuiz) ? __('quiz.edit_title') : __('quiz.create_title'))

@section('content')
@php
    $oldQuestions = old('questions');
    if (!is_array($oldQuestions)) $oldQuestions = $existingQuestions ?? [];
    $visibleQuestionCount = is_array($oldQuestions) ? min(3, max(1, count($oldQuestions))) : 1;
    $existingQuestionMap = collect($existingQuestions ?? [])->keyBy('idx');
    $isEditing = isset($editingQuiz);
    $quizTitle = old('title', $editingQuiz->title ?? '');
    $coverImagePath = $editingQuiz->cover_image_path ?? null;
    $coverImageUrl = $coverImagePath ? \App\Support\ManagedImageStorage::publicUrl($coverImagePath) : '';
    $removeCoverImage = old('remove_cover_image', '0') === '1';
@endphp
<section class="quiz-page quiz-create-page" data-quiz-create
    data-validation-message="{{ __('quiz.validation_failed') }}"
    data-question-label="{{ __('quiz.question_tab', ['number' => ':number']) }}"
    data-question-count-label="{{ __('quiz.question_count_badge', ['current' => ':current']) }}"
    data-idol-choose-category="{{ __('quiz.idol_choose_category') }}"
    data-idol-choose-option="{{ __('quiz.idol_choose_option') }}"
    data-idol-loading="{{ __('quiz.idol_loading') }}"
    data-idol-none-available="{{ __('quiz.idol_none_available') }}"
    data-idol-options-unavailable="{{ __('quiz.idol_options_unavailable') }}"
    data-image-invalid-message="{{ __('quiz.image_invalid') }}"
    data-cover-no-file="{{ __('quiz.no_file_selected') }}">
    <header class="quiz-list-hero" aria-labelledby="quiz-create-title">
        <div class="quiz-list-hero-copy">
            <h1 id="quiz-create-title">{{ $isEditing ? __('quiz.edit_title') : __('quiz.create_title') }}</h1>
            <p>{{ __('quiz.create_description') }}</p>
        </div>
        <svg class="quiz-list-hero-art" viewBox="0 0 420 160" aria-hidden="true" focusable="false">
            <circle class="quiz-list-hero-orbit quiz-list-hero-orbit--lavender" cx="85" cy="168" r="91" />
            <circle class="quiz-list-hero-orbit quiz-list-hero-orbit--pink" cx="348" cy="8" r="72" />
            <circle class="quiz-list-hero-orbit quiz-list-hero-orbit--purple" cx="532" cy="155" r="112" />
            <path class="quiz-list-hero-lines" d="M112 165c34-67 91-101 160-101s126 34 160 101M136 165c30-52 76-78 136-78s106 26 136 78M160 165c24-37 63-56 112-56s88 19 112 56" />
            <circle class="quiz-list-hero-dot quiz-list-hero-dot--blue" cx="184" cy="68" r="5" />
            <circle class="quiz-list-hero-dot quiz-list-hero-dot--blue" cx="211" cy="108" r="7" />
            <circle class="quiz-list-hero-dot quiz-list-hero-dot--blue" cx="232" cy="83" r="4" />
            <circle class="quiz-list-hero-dot quiz-list-hero-dot--pink" cx="399" cy="22" r="8" />
            <path class="quiz-list-hero-sparkle" d="M248 39v12m-6-6h12m-29 31v8m-4-4h8" />
            <g class="quiz-list-hero-speckles">
                <circle cx="389" cy="82" r="2.2" /><circle cx="402" cy="82" r="2.2" /><circle cx="415" cy="82" r="2.2" />
                <circle cx="389" cy="95" r="2.2" /><circle cx="402" cy="95" r="2.2" /><circle cx="415" cy="95" r="2.2" />
                <circle cx="389" cy="108" r="2.2" /><circle cx="402" cy="108" r="2.2" /><circle cx="415" cy="108" r="2.2" />
            </g>
        </svg>
    </header>

    @if($alert)<script>window.alert(@json($alert));</script>@endif

    <div class="quiz-create-layout">
        <div class="quiz-create-editor-column">
            <a class="quiz-secondary-button quiz-create-back-button" href="{{ route('quizzes.index') }}">{{ __('quiz.back_to_quiz_list') }}</a>
            <form class="quiz-form" method="POST" action="{{ $isEditing ? route('quizzes.update', $editingQuiz->idx) : route('quizzes.store') }}" enctype="multipart/form-data" novalidate data-quiz-form>
                @csrf
                @if($isEditing) @method('PUT') @endif
                <input type="hidden" name="quiz_version" value="{{ $editingQuiz->quiz_version ?? 1 }}">
                @if(!$isEditing && $randomDummyMode)<input type="hidden" name="request_key" value="{{ $randomRequestKey }}">@endif
                <section class="quiz-create-basic-section" aria-labelledby="quiz-basic-info-heading">
                    <div class="quiz-create-editor-heading">
                        <h2 id="quiz-basic-info-heading">{{ __('quiz.basic_info_heading') }}</h2>
                    </div>
                    @if($creationQuota !== null)
                        <p class="quiz-create-quota" role="status">
                            @if($creationQuota['remaining'] > 0)
                                {{ __('quiz.daily_creation_remaining', ['remaining' => $creationQuota['remaining'], 'limit' => $creationQuota['limit']]) }}
                            @else
                                {{ __('quiz.daily_creation_limit_reached', ['limit' => $creationQuota['limit'], 'remaining' => 0]) }}
                            @endif
                        </p>
                    @endif
                    @if($randomDummyMode)
                        <p class="quiz-create-quota" role="status">{{ __('quiz.random_dummy_creation_quota') }}</p>
                    @endif
                    <div class="quiz-create-idol-fields">
                        <div class="fan-post-create-field idol-request-type-row quiz-create-selection-row">
                            <div class="fan-post-create-label">{{ __('ui.request_type') }} <span>*</span></div>
                            <div class="idol-request-type-options quiz-create-category-options" role="radiogroup" aria-label="{{ __('quiz.idol_category_label') }}" data-idol-category-options data-idol-category-url="{{ route('fan-post.create.idols') }}" data-group-label="{{ __('ui.group_type') }}">
                                @foreach([
                                    'boy' => __('quiz.idol_category_boy'),
                                    'boy_group' => __('quiz.idol_category_boy_group'),
                                    'girl' => __('quiz.idol_category_girl'),
                                    'girl_group' => __('quiz.idol_category_girl_group'),
                                ] as $category => $label)
                                    <label><input type="radio" name="idol_category" value="{{ $category }}" @checked($selectedIdolCategory === $category) required><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </div>
                        <div class="fan-post-create-field quiz-create-selection-row">
                            <label for="quiz-idol-select">{{ __('ui.idol') }} <span>*</span></label>
                            <div>
                                <select id="quiz-idol-select" class="fan-post-sort-select" name="idol_idx" required data-idol-select @disabled(!$selectedIdolCategory || !$selectedIdol)>
                                    <option value="" @selected(!$selectedIdol)>{{ $selectedIdolCategory ? __('quiz.idol_choose_option') : __('quiz.idol_choose_category') }}</option>
                                    @if($selectedIdol)
                                        <option value="{{ $selectedIdol->idx }}" selected translate="no">{{ $selectedIdol->name }}{{ $selectedIdol->type === 'group' ? ' ('.__('ui.group_type').')' : '' }}</option>
                                    @endif
                                </select>
                                <small>{{ __('quiz.idol_selection_help') }}</small>
                                <x-idol-registration-prompt :approval-reward-amount="$approvalRewardAmount" />
                            </div>
                        </div>
                    </div>
                    <div class="fan-post-create-field quiz-create-cover-field">
                        <label class="fan-post-create-label" for="quiz-title">{{ __('quiz.title_label') }} <span>*</span></label>
                        <div>
                            <input id="quiz-title" class="fan-post-create-input" type="text" name="title" maxlength="255" value="{{ $quizTitle }}" required>
                            @error('title')<small class="field-error">{{ $message }}</small>@enderror
                        </div>
                    </div>
                    <div class="fan-post-create-field quiz-create-cover-field">
                        <label class="fan-post-create-label" for="quiz-cover-image">{{ __('quiz.cover_image_label') }} ({{ __('quiz.optional') }})</label>
                        <div>
                            <div class="quiz-cover-file-control">
                                <label class="quiz-cover-file-button" for="quiz-cover-image">{{ __('quiz.choose_file') }}</label>
                                <span class="quiz-cover-file-name" data-cover-filename>{{ $coverImagePath && !$removeCoverImage ? basename($coverImagePath) : __('quiz.no_file_selected') }}</span>
                                <input id="quiz-cover-image" class="quiz-cover-file-input" type="file" name="cover_image" accept="image/jpeg,image/png,image/webp" data-cover-image>
                            </div>
                            <input type="hidden" name="remove_cover_image" value="{{ $removeCoverImage ? '1' : '0' }}" data-remove-cover-image>
                            <div class="fan-post-image-selection quiz-image-selection" data-cover-selection data-existing-image="{{ $coverImageUrl }}" @if(!$coverImagePath || $removeCoverImage) hidden @endif>
                                <img src="{{ $coverImageUrl }}" alt="{{ __('quiz.cover_image_preview') }}" data-cover-preview>
                                <div><button type="button" data-crop-open>{{ __('ui.edit_crop') }}</button><button type="button" data-image-remove>{{ __('ui.remove') }}</button></div>
                            </div>
                            <small>{{ __('quiz.cover_image_help') }}</small>
                        </div>
                    </div>
                </section>

                <section class="quiz-create-question-section" aria-labelledby="quiz-question-config-heading">
                    <div class="quiz-create-editor-heading">
                        <h2 id="quiz-question-config-heading">{{ __('quiz.editor_heading') }}</h2>
                        <span class="quiz-create-count" data-question-count>{{ __('quiz.question_count_badge', ['current' => $visibleQuestionCount]) }}</span>
                    </div>

                    <div class="quiz-question-toolbar">
                        <div class="quiz-question-tabs" role="tablist" aria-label="{{ __('quiz.create_title') }}" data-question-tabs>
                            @for($index = 0; $index < 3; $index++)
                                <button class="quiz-question-tab {{ $index === 0 ? 'is-active' : '' }}" type="button" role="tab" id="quiz-question-tab-{{ $index }}" aria-controls="quiz-question-panel-{{ $index }}" data-question-tab="{{ $index }}" @if($index >= $visibleQuestionCount) hidden @endif aria-selected="{{ $index === 0 ? 'true' : 'false' }}">{{ __('quiz.question_tab', ['number' => $index + 1]) }}</button>
                            @endfor
                        </div>
                        <button class="quiz-secondary-button" type="button" data-add-question>{{ __('quiz.add_question') }}</button>
                    </div>

                    <div data-question-panels>
                        @for($index = 0; $index < 3; $index++)
                        @php
                            $questionData = $oldQuestions[$index] ?? [];
                            $questionData = is_object($questionData) ? (array) $questionData : (is_array($questionData) ? $questionData : []);
                            $questionType = is_string($questionData['type'] ?? null) && in_array($questionData['type'], ['image', 'choice', 'ox'], true)
                                ? $questionData['type']
                                : ($index === 0 && $oldQuestions === [] ? 'choice' : '');
                            $questionPrompt = is_string($questionData['prompt'] ?? null) ? $questionData['prompt'] : '';
                            $oldChoices = is_array($questionData['choices'] ?? null) ? $questionData['choices'] : [];
                            $correctChoiceOrder = is_scalar($questionData['correct_choice_order'] ?? null) ? (string) $questionData['correct_choice_order'] : '';
                            $correctOxAnswer = in_array($questionData['correct_answer'] ?? null, ['O', 'X'], true) ? $questionData['correct_answer'] : '';
                            $existingQuestionIdx = is_scalar($questionData['existing_question_idx'] ?? null) ? (int) $questionData['existing_question_idx'] : null;
                            $existingImagePath = $questionData['image_path'] ?? ($existingQuestionMap->get($existingQuestionIdx)?->image_path ?? null);
                            $existingImageUrl = $existingImagePath ? \App\Support\ManagedImageStorage::publicUrl($existingImagePath) : '';
                        @endphp
                        <section class="quiz-question-panel" id="quiz-question-panel-{{ $index }}" role="tabpanel" aria-labelledby="quiz-question-tab-{{ $index }}" data-question-panel="{{ $index }}" @if($index >= $visibleQuestionCount) hidden @endif>
                            <div class="quiz-create-question-card">
                                <div class="quiz-field">
                                    <span class="quiz-field-label">{{ __('quiz.question_type') }} <span aria-hidden="true">*</span></span>
                                    <input type="hidden" name="questions[{{ $index }}][type]" value="{{ $questionType }}" data-question-type>
                                    <div class="quiz-type-buttons" role="group" aria-label="{{ __('quiz.question_type') }}">
                                        @foreach(['image' => __('quiz.type_image'), 'choice' => __('quiz.type_choice'), 'ox' => __('quiz.type_ox')] as $type => $label)
                                            <button class="quiz-type-button {{ $questionType === $type ? 'is-active' : '' }}" type="button" data-select-type="{{ $type }}" aria-pressed="{{ $questionType === $type ? 'true' : 'false' }}">
                                                @if($type === 'image')
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5 17 5-5 3.5 3 2.5-2 3 4"/></svg>
                                                @elseif($type === 'choice')
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3.5 6 .8.8L5.8 5M3.5 12l.8.8L5.8 11M3.5 18l.8.8L5.8 17"/></svg>
                                                @else
                                                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/></svg>
                                                @endif
                                                <span>{{ $label }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <label class="quiz-field">
                                    <span class="quiz-field-label">{{ __('quiz.question_text') }} <span aria-hidden="true">*</span></span>
                                    <input type="text" name="questions[{{ $index }}][prompt]" maxlength="500" placeholder="{{ __('quiz.question_placeholder') }}" value="{{ $questionPrompt }}" data-question-prompt>
                                </label>

                                <div class="quiz-type-fields" data-type-fields="image choice" @if(!in_array($questionType, ['image', 'choice'], true)) hidden @endif>
                                    <div class="quiz-image-field" data-image-field @if($questionType !== 'image') hidden @endif>
                                        <label class="quiz-field">
                                            <span class="quiz-field-label">{{ __('quiz.question_image') }} <span aria-hidden="true">*</span></span>
                                            <input id="quiz-question-image-{{ $index }}" type="file" name="questions[{{ $index }}][image]" accept="image/jpeg,image/png,image/webp" data-question-image>
                                            <input type="hidden" name="questions[{{ $index }}][existing_question_idx]" value="{{ $existingQuestionIdx }}" data-existing-question-idx>
                                            <div class="fan-post-image-selection quiz-image-selection" data-question-image-selection data-existing-image="{{ $existingImageUrl }}" @if(!$existingImagePath) hidden @endif>
                                                <img src="{{ $existingImageUrl }}" alt="{{ __('quiz.question_image_preview') }}" data-question-image-preview>
                                                <div><button type="button" data-crop-open>{{ __('ui.edit_crop') }}</button><button type="button" data-image-remove>{{ __('ui.remove') }}</button></div>
                                            </div>
                                            <small>{{ __('quiz.image_help') }}</small>
                                        </label>
                                    </div>
                                    <div class="quiz-create-answer-heading">
                                        <span class="quiz-field-label">{{ __('quiz.choices_required') }} <span aria-hidden="true">*</span></span>
                                        <small>{{ __('quiz.answer_help') }}</small>
                                    </div>
                                    <div class="quiz-choice-grid">
                                        @for($choice = 1; $choice <= 5; $choice++)
                                            @php $choiceId = 'quiz-'.$index.'-choice-'.$choice; @endphp
                                            <div class="quiz-choice-row {{ $correctChoiceOrder === (string) $choice ? 'is-correct' : '' }}">
                                                <input type="radio" name="questions[{{ $index }}][correct_choice_order]" value="{{ $choice }}" aria-label="{{ __('quiz.mark_correct_choice', ['number' => $choice]) }}" data-correct-choice @checked($correctChoiceOrder === (string) $choice)>
                                                <span class="quiz-choice-number">{{ $choice }}</span>
                                                <input id="{{ $choiceId }}" type="text" name="questions[{{ $index }}][choices][{{ $choice - 1 }}]" maxlength="200" value="{{ is_string($oldChoices[$choice - 1] ?? null) ? $oldChoices[$choice - 1] : '' }}" placeholder="{{ __('quiz.choice_placeholder', ['number' => $choice]) }}" aria-label="{{ __('quiz.choice_number', ['number' => $choice]) }}" data-question-choice>
                                                <span class="quiz-correct-marker" @if($correctChoiceOrder !== (string) $choice) hidden @endif>{{ __('quiz.correct_marker') }}</span>
                                            </div>
                                        @endfor
                                    </div>
                                </div>

                                <fieldset class="quiz-type-fields quiz-ox-fields" data-type-fields="ox" @if($questionType !== 'ox') hidden @endif>
                                    <legend class="quiz-field-label">{{ __('quiz.correct_answer') }} <span aria-hidden="true">*</span></legend>
                                    @foreach(['O', 'X'] as $answer)
                                        <label class="quiz-answer-choice"><input type="radio" name="questions[{{ $index }}][correct_answer]" value="{{ $answer }}" @checked($correctOxAnswer === $answer)> <span>{{ $answer }}</span></label>
                                    @endforeach
                                </fieldset>
                            </div>
                            <div class="quiz-create-panel-footer">
                                <span>{{ __('quiz.question_limit_note') }}</span>
                                <button class="quiz-text-link" type="button" data-delete-question>{{ __('quiz.delete_question') }}</button>
                            </div>
                        </section>
                        @endfor
                    </div>

                    <div class="quiz-form-actions">
                        <a class="quiz-secondary-button" href="{{ route('quizzes.index') }}">{{ __('quiz.cancel') }}</a>
                        <button class="quiz-primary-button" type="submit" @disabled(!$isEditing && !$randomDummyMode && $creationQuota['remaining'] === 0)>{{ $isEditing ? __('quiz.update_quiz') : __('quiz.register_quiz') }}</button>
                    </div>
                </section>
            </form>
        </div>

        <aside class="quiz-create-guidance">
            <section class="quiz-create-side-card">
                <h2>{{ __('quiz.supported_types') }}</h2>
                <div class="quiz-create-supported-type">
                    <span class="quiz-create-type-icon is-image" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.5"/><path d="m5 17 5-5 3.5 3 2.5-2 3 4"/></svg></span>
                    <span><strong>{{ __('quiz.type_image') }}</strong><small>{{ __('quiz.type_image_description') }}</small></span>
                </div>
                <div class="quiz-create-supported-type">
                    <span class="quiz-create-type-icon is-choice" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 6h12M8 12h12M8 18h12"/><path d="m3.5 6 .8.8L5.8 5M3.5 12l.8.8L5.8 11M3.5 18l.8.8L5.8 17"/></svg></span>
                    <span><strong>{{ __('quiz.type_choice') }}</strong><small>{{ __('quiz.type_choice_description') }}</small></span>
                </div>
                <div class="quiz-create-supported-type">
                    <span class="quiz-create-type-icon is-ox" aria-hidden="true">O/X</span>
                    <span><strong>{{ __('quiz.type_ox') }}</strong><small>{{ __('quiz.type_ox_description') }}</small></span>
                </div>
            </section>

            <section class="quiz-create-guide-card">
                <h2>{{ __('quiz.writing_guide') }}</h2>
                <ol>
                    <li class="quiz-create-guide-reward">
                        <div class="quiz-create-guide-reward-content">
                            <span>{{ __('quiz.creator_reward_guide_intro') }}</span>
                            <div class="quiz-create-guide-reward-items">
                                @foreach($creatorRewardSettings as $questionCount => $creatorReward)
                                    <span class="quiz-create-guide-reward-item">
                                        <span>{{ __('quiz.creator_reward_for_count', ['count' => $questionCount]) }}</span>
                                        @if($creatorReward['enabled'])
                                            <span class="quiz-create-reward-amount"><svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg><span>+{{ number_format($creatorReward['amount']) }}</span></span>
                                        @else
                                            <span>{{ __('quiz.creator_reward_disabled') }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </li>
                    <li>{{ __('quiz.guide_one') }}</li>
                    <li>{{ __('quiz.guide_two') }}</li>
                    <li>{{ __('quiz.guide_daily_creation_limit') }}</li>
                    <li>{{ __('quiz.guide_uncomfortable_content') }}</li>
                    <li>{{ __('quiz.guide_unverified_information') }}</li>
                    <li>{{ __('quiz.guide_duplicate_content') }}</li>
                    <li>{{ __('quiz.guide_policy_violation') }}</li>
                </ol>
            </section>

            <section class="quiz-create-reward-card">
                <h2><svg class="heart-icon--small" width="23" height="23" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('quiz.reward_guide_title') }}</h2>
                <p>{{ __('quiz.reward_guide_description') }}</p>
                <div class="quiz-create-reward-note">
                    @if($rewardEnabled && $rewardMax > 0)
                        <p class="quiz-reward-note">{{ __('quiz.perfect_score_reward_label') }}<span class="quiz-create-reward-amount"><svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg><span>+{{ number_format($rewardMin) }}~{{ number_format($rewardMax) }}</span></span></p>
                    @else
                        <p class="quiz-reward-note">{{ __('quiz.reward_unavailable') }}</p>
                    @endif
                    <p class="quiz-create-daily-rule">{{ __('quiz.daily_rule') }}</p>
                </div>
            </section>
        </aside>
    </div>
    <x-square-image-crop-dialog id="quiz-cover-crop-dialog" :title="__('quiz.cover_crop_title')" />
    @for($index = 0; $index < 3; $index++)
        <x-square-image-crop-dialog id="quiz-question-crop-dialog-{{ $index }}" :title="__('quiz.question_crop_title')" />
    @endfor
</section>
@endsection
