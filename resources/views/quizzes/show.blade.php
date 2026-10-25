@extends('layouts.app')

@section('title', $quiz->title)

@section('content')
@php
    $creatorImageUrl = \App\Support\ManagedImageStorage::publicUrl($quiz->creator_image_path ?? null);
    $coverImageUrl = \App\Support\ManagedImageStorage::publicUrl($quiz->cover_image_path);
    $rewardText = $rewardEnabled && $rewardMax > 0
        ? number_format($rewardMin).'~'.number_format($rewardMax)
        : __('quiz.reward_unavailable');
    $creatorReward = $creatorRewardSettings[(int) $quiz->question_count] ?? ['amount' => 0, 'enabled' => false];
    $dateFormat = app()->getLocale() === 'ko' ? 'Y.m.d H:i' : 'M j, Y H:i';
    $idolCategory = match ([$quiz->idol_type ?? null, $quiz->idol_gender ?? null]) {
        ['individual', 'male'] => __('quiz.idol_category_boy'),
        ['individual', 'female'] => __('quiz.idol_category_girl'),
        ['group', 'male'] => __('quiz.idol_category_boy_group'),
        ['group', 'female'] => __('quiz.idol_category_girl_group'),
        default => __('quiz.idol_category_all'),
    };
    $registeredAt = \Illuminate\Support\Carbon::parse($quiz->created_at);
@endphp
<section class="quiz-page quiz-detail-page" data-quiz-detail>
    <header class="quiz-list-hero quiz-detail-hero" aria-labelledby="quiz-detail-hero-title">
        <div class="quiz-list-hero-copy">
            <h1 id="quiz-detail-hero-title">{{ __('quiz.list_hero_title') }}</h1>
            <p>{{ __('quiz.list_hero_description') }}</p>
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

    @if(session('quiz_created'))<p class="quiz-success-note" role="status">{{ __('quiz.created_message') }}</p>@endif
    @if(session('quiz_updated'))<p class="quiz-success-note" role="status">{{ __('quiz.updated_message') }}</p>@endif
    @if($errors->has('random_dummy'))<p class="quiz-status-note" role="alert">{{ $errors->first('random_dummy') }}</p>@endif

    <div class="quiz-detail-layout">
        <div class="quiz-detail-main">
            <nav class="quiz-detail-actions quiz-detail-content-actions" aria-label="{{ __('quiz.detail_title') }}">
                @if($canEdit)<a class="quiz-secondary-button quiz-detail-button quiz-detail-button--outline" href="{{ route('quizzes.edit', $quiz->idx) }}">{{ __('quiz.edit_quiz') }}</a>@endif
                @if($canEdit)
                    <form method="POST" action="{{ route('quizzes.destroy', $quiz->idx) }}" onsubmit="return confirm(@js(__('quiz.delete_confirm')))">
                        @csrf
                        @method('DELETE')
                        <button class="quiz-secondary-button quiz-detail-delete-button" type="submit">{{ __('quiz.delete_quiz') }}</button>
                    </form>
                @endif
                <a class="quiz-secondary-button quiz-detail-back-button" href="{{ route('quizzes.index') }}">{{ __('quiz.back_to_quiz_list') }}</a>
            </nav>
            @if($state === 'completed')
                <section class="quiz-result-card" aria-labelledby="quiz-result-title">
                    <div class="quiz-result-summary">
                        <svg class="quiz-result-art" viewBox="0 0 840 260" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                            <path class="quiz-result-art-heart quiz-result-art-heart--left" d="M103 208C44 165 40 115 75 93c27-17 53-2 63 20 10-22 36-37 63-20 35 22 31 72-28 115l-35 25z" />
                            <path class="quiz-result-art-heart quiz-result-art-heart--right" d="M738 186c-42-31-45-66-20-82 19-12 38-1 45 15 7-16 26-27 45-15 25 16 22 51-20 82l-25 17z" />
                            <path class="quiz-result-art-heart quiz-result-art-heart--small" d="M245 83c-24-17-26-37-12-46 11-7 22-1 26 9 4-10 15-16 26-9 14 9 12 29-12 46l-14 10z" />
                            <path class="quiz-result-art-sparkle" d="M171 53v20m-10-10h20m427 42v17m-8.5-8.5h17M320 33v12m-6-6h12" />
                            <circle class="quiz-result-art-dot" cx="310" cy="78" r="5" /><circle class="quiz-result-art-dot" cx="542" cy="57" r="4" />
                            <path class="quiz-result-art-confetti" d="m207 41 9 13m-2-23 9 13m370 67 9 13m-2-23 9 13" />
                        </svg>
                        <svg class="quiz-result-icon quiz-result-icon--success" viewBox="0 0 64 64" aria-hidden="true">
                            <circle cx="32" cy="32" r="30" fill="#eee5ff" />
                            <circle cx="32" cy="32" r="22" fill="#8b5be3" />
                            <path d="m21 32 7 7 15-16" fill="none" stroke="#fff" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M32 0v6M32 58v6M0 32h6M58 32h6M9 9l5 5m36 36 5 5M55 9l-5 5M14 50l-5 5" fill="none" stroke="#c7a9f4" stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                        <h2 id="quiz-result-title">{{ __('quiz.result_title') }}</h2>
                        <p class="quiz-result-score"><span>{{ __('quiz.result_score_prefix', ['total' => $attempt->question_count]) }}</span><strong>{{ $attempt->score }}</strong><span>{{ __('quiz.result_score_suffix', ['total' => $attempt->question_count]) }}</span></p>
                        <p class="quiz-result-explanation">{{ $attempt->is_perfect ? __('quiz.result_perfect') : __('quiz.result_incorrect_explanation') }}</p>
                        <p class="quiz-result-reward {{ (int) $attempt->awarded_hearts > 0 ? '' : 'quiz-result-reward--muted' }}">
                            <svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>
                            {{ __('quiz.result_earned', ['amount' => number_format((int) $attempt->awarded_hearts)]) }}
                        </p>
                        @if(!$randomDummyMode || $canReplayDummy)
                            @include('quizzes.partials.like-button')
                        @endif
                        @if($isOwner)<p class="quiz-result-test-note">{{ __('quiz.result_creator_test') }}</p>@endif
                    </div>
                    <ol class="quiz-result-answers">
                        @foreach($resultQuestions as $result)
                            <li class="quiz-result-answer {{ $result->is_correct ? 'quiz-result-answer--correct' : 'quiz-result-answer--incorrect' }}">
                                <span class="quiz-result-question-number"><strong>{{ $result->question_order }}</strong><span>{{ __('quiz.result_question_word') }}</span></span>
                                <div class="quiz-result-review">
                                    <h3>{{ $result->prompt }}</h3>
                                    <div class="quiz-result-answer-row">
                                        <span class="quiz-result-answer-label quiz-result-answer-label--selected">{{ __('quiz.selected_answer_short') }}</span>
                                        <span class="quiz-result-answer-text">{{ $result->selected_text }}</span>
                                    </div>
                                    <div class="quiz-result-answer-row">
                                        <span class="quiz-result-answer-label quiz-result-answer-label--correct">{{ __('quiz.correct_answer_short') }}</span>
                                        <span class="quiz-result-answer-text">{{ $result->correct_text }}</span>
                                    </div>
                                </div>
                                <div class="quiz-result-answer-status">
                                    @if($result->is_correct)
                                        <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22"/><path d="m13 24 7 7 15-16"/></svg>
                                        <span>{{ __('quiz.answer_correct') }}</span>
                                    @else
                                        <svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="24" cy="24" r="22"/><path d="m17 17 14 14m0-14L17 31"/></svg>
                                        <span>{{ __('quiz.answer_incorrect') }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <div class="quiz-result-actions">
                        <a class="quiz-secondary-button quiz-detail-back-button" href="{{ route('quizzes.index') }}">{{ __('quiz.back_to_quiz_list') }}</a>
                        @if($isOwner || $canReplayDummy)
                            <form method="POST" action="{{ route('quizzes.answer', $quiz->idx) }}" class="quiz-answer-form">
                                @csrf
                                <input type="hidden" name="restart_attempt" value="1">
                                <input type="hidden" name="quiz_version" value="{{ $quiz->quiz_version }}">
                                @if($canReplayDummy)<input type="hidden" name="request_key" value="{{ $randomRetryRequestKey }}">@endif
                                <button class="quiz-primary-button" type="submit">{{ $canReplayDummy ? __('quiz.random_dummy_retry') : __('quiz.creator_retry') }}</button>
                            </form>
                        @endif
                    </div>
                </section>
            @else
                @if(!$member && !$randomDummyMode)<p class="quiz-status-note">{{ __('quiz.sign_in_to_play') }}</p>
                @endif

                @foreach($questions as $question)
                    <article class="quiz-question-card" data-quiz-question="{{ $question->question_order }}" @if((int) $question->question_order !== 1) hidden @endif>
                        <div class="quiz-question-heading">
                            <p class="quiz-card-eyebrow">{{ $quiz->title }}</p>
                            <span class="quiz-question-progress" aria-label="{{ __('quiz.question_progress_badge', ['current' => $question->question_order, 'total' => $displayQuestionCount]) }}">{{ __('quiz.question_progress_badge', ['current' => $question->question_order, 'total' => $displayQuestionCount]) }}</span>
                        </div>
                        <h2>{{ $question->prompt }}</h2>
                        @if($question->image_path)<img class="quiz-question-image" src="{{ \App\Support\ManagedImageStorage::publicUrl($question->image_path) }}" alt="{{ __('quiz.question_image_preview') }}">@endif
                        @if($canParticipate)
                            <form method="POST" action="{{ route('quizzes.answer', $quiz->idx) }}" class="quiz-answer-form" data-quiz-answer-form data-question-order="{{ $question->question_order }}">
                                @csrf
                                <input type="hidden" name="question_order" value="{{ $question->question_order }}">
                                <input type="hidden" name="quiz_version" value="{{ $questionVersion }}">
                                @if($randomDummyMode)<input type="hidden" name="request_key" value="{{ $randomRequestKey }}">@endif
                                @if($ownerRetryToken)<input type="hidden" name="owner_retry_token" value="{{ $ownerRetryToken }}">@endif
                                @foreach($question->choices as $choice)
                                    <label class="quiz-play-choice"><input type="radio" name="selected_choice_order" value="{{ $choice->choice_order }}" required><span>{{ $choice->choice_text }}</span></label>
                                @endforeach
                                <div class="quiz-question-navigation">
                                    @if((int) $question->question_order > 1)
                                        <button class="quiz-secondary-button quiz-question-previous" type="button" data-quiz-previous="{{ $question->question_order }}">{{ __('quiz.previous_question') }}</button>
                                    @endif
                                    @if($displayQuestionCount === 1 || (int) $question->question_order === (int) $displayQuestionCount)
                                        <button class="quiz-primary-button" type="submit">{{ $displayQuestionCount === 1 ? __('quiz.confirm_answer') : __('quiz.view_results') }}</button>
                                    @else
                                        <button class="quiz-primary-button" type="button" data-quiz-next="{{ $question->question_order }}">{{ __('quiz.next_question') }} <span aria-hidden="true">→</span></button>
                                    @endif
                                </div>
                            </form>
                        @else
                            <ul class="quiz-preview-choices">@foreach($question->choices as $choice)<li>{{ $choice->choice_text }}</li>@endforeach</ul>
                        @endif
                    </article>
                @endforeach
            @endif
            @if($state !== 'completed' && (!$randomDummyMode || $canReplayDummy))
                @include('quizzes.partials.like-button')
            @endif
        </div>

        <aside class="quiz-detail-sidebar">
            <section class="quiz-detail-info-card" aria-labelledby="quiz-info-title">
                <h2 id="quiz-info-title">{{ __('quiz.information_title') }}</h2>
                <div class="quiz-detail-cover">
                    <img src="{{ $coverImageUrl }}" alt="{{ __('quiz.cover_image_preview') }}">
                    @if($quiz->idol_name)<span class="quiz-list-idol-badge" title="{{ $quiz->idol_name }}" translate="no">{{ $quiz->idol_name }}</span>@endif
                    <span class="quiz-detail-question-badge">{{ __('quiz.question_badge', ['count' => $quiz->question_count]) }}</span>
                    <div class="post-statistics quiz-list-card-statistics">
                        @if($showQuizStatistics)
                            <span class="post-stat post-stat--views" aria-label="{{ __('ui.views_aria', ['amount' => number_format($quizViewCount)]) }}" title="{{ __('ui.views_aria', ['amount' => number_format($quizViewCount)]) }}"><svg aria-hidden="true"><use href="#icon-eye"/></svg><span>{{ number_format($quizViewCount) }}</span></span>
                            <span class="post-stat post-stat--comments" aria-label="{{ __('quiz.participants_count_aria', ['count' => number_format($quizParticipantCount)]) }}" title="{{ __('quiz.participants_count_aria', ['count' => number_format($quizParticipantCount)]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21a8 8 0 0 1 16 0Z"/></svg><span>{{ number_format($quizParticipantCount) }}</span></span>
                        @endif
                        <span class="post-stat post-stat--recommendations" aria-label="{{ __('ui.recommendations_aria', ['amount' => number_format($quizLikeCount)]) }}" title="{{ __('ui.recommendations_aria', ['amount' => number_format($quizLikeCount)]) }}"><svg aria-hidden="true"><use href="#icon-recommend"/></svg><span>{{ number_format($quizLikeCount) }}</span></span>
                    </div>
                </div>
                <h3>{{ $quiz->title }}</h3>
                <div class="quiz-detail-info-author"><x-member-link :member-code="$quiz->creator_member_code" :label="__('ui.member_view_posts_label', ['name' => $quiz->creator_nickname])" class="post-author-avatar-link quiz-detail-info-author-avatar"><img src="{{ $creatorImageUrl }}" alt="" width="48" height="48"></x-member-link><x-member-link :member-code="$quiz->creator_member_code" class="quiz-list-author-name"><x-country-flag :code="$quiz->creator_country_code" /><span translate="no">{{ $quiz->creator_nickname }}</span></x-member-link>@if(!$isOwner && $rewardEnabled && $rewardMax > 0)<strong><svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>+{{ $rewardText }}</strong>@endif</div>
                <dl class="quiz-detail-meta">
                    <div><dt>{{ __('quiz.idol_category_label') }}</dt><dd>{{ $idolCategory }}</dd></div>
                    <div><dt>{{ __('quiz.idol_selector_label') }}</dt><dd>@if($quiz->idol_idx && $quiz->idol_name && $quiz->idol_status === 'active')<a href="{{ route('idol.show', $quiz->idol_idx) }}" translate="no">{{ $quiz->idol_name }}</a>@else—@endif</dd></div>
                    <div><dt>{{ __('quiz.total_questions_label') }}</dt><dd>{{ __('quiz.question_count', ['count' => $quiz->question_count]) }}</dd></div>
                </dl>
            </section>
            <section class="quiz-detail-guide" aria-labelledby="quiz-guide-title">
                <h2 id="quiz-guide-title">{{ __('quiz.participation_guide_title') }}</h2>
                <ol><li>{{ $isOwner ? __('quiz.creator_unlimited_retries') : __('quiz.daily_rule') }}</li><li>{{ $isOwner ? __('quiz.creator_no_reward') : __('quiz.reward_guide_description') }}</li><li>{{ $creatorReward['enabled'] ? __('quiz.creator_reward_detail', ['amount' => number_format($creatorReward['amount'])]) : __('quiz.creator_reward_disabled') }}</li></ol>
            </section>
        </aside>
    </div>
</section>
@endsection
