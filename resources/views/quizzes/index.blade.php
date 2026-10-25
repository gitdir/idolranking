@extends('layouts.app')

@section('title', __('quiz.list_title'))

@section('content')
@php
    $idolCategories = ['boy', 'boy_group', 'girl', 'girl_group'];
    $selectedIdolCategory = request()->query('idol_category');
    $selectedIdolCategory = is_string($selectedIdolCategory) && in_array($selectedIdolCategory, $idolCategories, true)
        ? $selectedIdolCategory
        : '';
    $categoryLabels = [
        '' => __('quiz.idol_category_all'),
        'boy' => __('quiz.idol_category_boy'),
        'girl' => __('quiz.idol_category_girl'),
        'boy_group' => __('quiz.idol_category_boy_group'),
        'girl_group' => __('quiz.idol_category_girl_group'),
    ];
    $selectedCategoryLabel = $categoryLabels[$selectedIdolCategory];
@endphp
<section class="quiz-page quiz-list-page" data-quiz-page>
    <header class="quiz-list-hero" aria-labelledby="quiz-list-title">
        <div class="quiz-list-hero-copy">
            <h1 id="quiz-list-title">{{ __('quiz.list_hero_title') }}</h1>
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

    <section class="quiz-weekly-ranking" aria-label="{{ __('quiz.popular_quiz_rankings') }}">
        <div class="quiz-ranking-columns">
            <section class="quiz-ranking-column" aria-labelledby="quiz-weekly-ranking-title">
                <div class="quiz-weekly-ranking-heading">
                    <div>
                        <h2 id="quiz-weekly-ranking-title">{{ __('quiz.weekly_quiz_ranking_title') }}</h2>
                    </div>
                </div>
                @if($weeklyQuizRanking->isEmpty())
                    <p class="quiz-weekly-ranking-empty">{{ __('quiz.weekly_quiz_ranking_empty') }}</p>
                @else
                    <ol class="quiz-weekly-ranking-list">
                        @foreach($weeklyQuizRanking as $quiz)
                            <li>
                                <span class="quiz-weekly-ranking-rank">{{ __('quiz.popular_quiz_rank_'.$quiz->rank) }}</span>
                                <a class="quiz-weekly-ranking-name" href="{{ route('quizzes.show', $quiz->quiz_idx) }}">{{ $quiz->title }}</a>
                                <span class="quiz-weekly-ranking-reward">@if(($weeklyQuizRewards[$quiz->rank] ?? 0) > 0)<svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('quiz.weekly_quiz_reward_pending', ['amount' => number_format($weeklyQuizRewards[$quiz->rank])]) }}@else{{ __('quiz.weekly_quiz_reward_unavailable') }}@endif</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <section class="quiz-ranking-column quiz-ranking-column--monthly" aria-labelledby="quiz-monthly-ranking-title">
                <div class="quiz-weekly-ranking-heading">
                    <div>
                        <h2 id="quiz-monthly-ranking-title">{{ __('quiz.monthly_quiz_ranking_title') }}</h2>
                    </div>
                </div>
                @if($monthlyQuizRanking->isEmpty())
                    <p class="quiz-weekly-ranking-empty">{{ __('quiz.monthly_quiz_ranking_empty') }}</p>
                @else
                    <ol class="quiz-weekly-ranking-list">
                        @foreach($monthlyQuizRanking as $quiz)
                            <li>
                                <span class="quiz-weekly-ranking-rank">{{ __('quiz.popular_quiz_rank_'.$quiz->rank) }}</span>
                                <a class="quiz-weekly-ranking-name" href="{{ route('quizzes.show', $quiz->quiz_idx) }}">{{ $quiz->title }}</a>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </section>

    {{-- Temporarily hidden; keep the existing quiz filters and search markup for later reactivation.
    <div class="quiz-list-controls" data-quiz-list-filters
        data-selected-category="{{ $selectedIdolCategory }}">
        <div class="quiz-list-category-row">
            <nav class="quiz-list-filters" aria-label="{{ __('quiz.idol_category_label') }}">
                <button class="quiz-list-category-btn quiz-list-category-btn--all {{ $selectedIdolCategory === '' ? 'is-active' : '' }}" type="button" data-idol-category="" data-category-label="{{ $categoryLabels[''] }}" aria-pressed="{{ $selectedIdolCategory === '' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                    <span class="quiz-list-category-copy"><span class="quiz-list-category-name">{{ $categoryLabels[''] }}</span><span class="quiz-list-category-count">{{ number_format($quizTotal) }}</span></span>
                </button>
                <button class="quiz-list-category-btn quiz-list-category-btn--boy {{ $selectedIdolCategory === 'boy' ? 'is-active' : '' }}" type="button" data-idol-category="boy" data-category-label="{{ $categoryLabels['boy'] }}" aria-pressed="{{ $selectedIdolCategory === 'boy' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/></svg>
                    <span class="quiz-list-category-copy"><span class="quiz-list-category-name">{{ $categoryLabels['boy'] }}</span><span class="quiz-list-category-count">{{ number_format($categoryTotals['boy'] ?? 0) }}</span></span>
                </button>
                <button class="quiz-list-category-btn quiz-list-category-btn--girl {{ $selectedIdolCategory === 'girl' ? 'is-active' : '' }}" type="button" data-idol-category="girl" data-category-label="{{ $categoryLabels['girl'] }}" aria-pressed="{{ $selectedIdolCategory === 'girl' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2Z"/></svg>
                    <span class="quiz-list-category-copy"><span class="quiz-list-category-name">{{ $categoryLabels['girl'] }}</span><span class="quiz-list-category-count">{{ number_format($categoryTotals['girl'] ?? 0) }}</span></span>
                </button>
                <button class="quiz-list-category-btn quiz-list-category-btn--boy-group {{ $selectedIdolCategory === 'boy_group' ? 'is-active' : '' }}" type="button" data-idol-category="boy_group" data-category-label="{{ $categoryLabels['boy_group'] }}" aria-pressed="{{ $selectedIdolCategory === 'boy_group' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 21v-1.5a6.5 6.5 0 0 1 13 0V21Zm13.5-6a5.5 5.5 0 0 1 6 5.5v.5h-4"/></svg>
                    <span class="quiz-list-category-copy"><span class="quiz-list-category-name">{{ $categoryLabels['boy_group'] }}</span><span class="quiz-list-category-count">{{ number_format($categoryTotals['boy_group'] ?? 0) }}</span></span>
                </button>
                <button class="quiz-list-category-btn quiz-list-category-btn--girl-group {{ $selectedIdolCategory === 'girl_group' ? 'is-active' : '' }}" type="button" data-idol-category="girl_group" data-category-label="{{ $categoryLabels['girl_group'] }}" aria-pressed="{{ $selectedIdolCategory === 'girl_group' ? 'true' : 'false' }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.5"/><path d="M2.5 21v-1.5a6.5 6.5 0 0 1 13 0V21Zm13.5-6a5.5 5.5 0 0 1 6 5.5v.5h-4"/></svg>
                    <span class="quiz-list-category-copy"><span class="quiz-list-category-name">{{ $categoryLabels['girl_group'] }}</span><span class="quiz-list-category-count">{{ number_format($categoryTotals['girl_group'] ?? 0) }}</span></span>
                </button>
            </nav>

            <form class="quiz-list-search" method="GET" action="{{ route('quizzes.index') }}" role="search">
                <input type="hidden" name="questions" value="{{ $questionCount ?: '' }}">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="idol_category" value="{{ $selectedIdolCategory }}" data-idol-category-state>
                <label class="sr-only" for="quiz-list-search-input">{{ __('quiz.search_label') }}</label>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                <input id="quiz-list-search-input" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="{{ __('quiz.search_placeholder') }}">
                <button type="submit">{{ __('quiz.search_button') }}</button>
            </form>
        </div>

    </div>
    --}}

    <div class="quiz-list-heading-row">
        <h2 data-quiz-category-heading
            data-all-heading="{{ __('quiz.list_results', ['count' => number_format($quizzes->total())]) }}"
            data-category-template="{{ __('quiz.category_list_results') }}"
            data-result-count="{{ number_format($quizzes->total()) }}">{{ $selectedIdolCategory === '' ? __('quiz.list_results', ['count' => number_format($quizzes->total())]) : __('quiz.category_list_results', ['category' => $selectedCategoryLabel, 'count' => number_format($quizzes->total())]) }}</h2>
        <div class="quiz-list-heading-actions">
            <form method="GET" action="{{ route('quizzes.index') }}" class="quiz-list-sort-form">
                <input type="hidden" name="questions" value="{{ $questionCount ?: '' }}">
                <input type="hidden" name="q" value="{{ $search }}">
                <input type="hidden" name="idol_category" value="{{ $selectedIdolCategory }}" data-idol-category-state>
                <label class="sr-only" for="quiz-list-sort">{{ __('quiz.sort_label') }}</label>
                <select class="fan-post-sort-select quiz-list-sort" name="sort" id="quiz-list-sort" data-quiz-list-sort>
                    <option value="latest" @selected($sort === 'latest')>{{ __('quiz.sort_latest') }}</option>
                    <option value="oldest" @selected($sort === 'oldest')>{{ __('quiz.sort_oldest') }}</option>
                    <option value="most_questions" @selected($sort === 'most_questions')>{{ __('quiz.sort_question_count') }}</option>
                </select>
            </form>
            @if($member)
                <a class="idol-detail-create-post quiz-list-create-action" href="{{ route('quizzes.create') }}">+ {{ __('quiz.create_quiz') }}</a>
            @else
                <button class="idol-detail-create-post quiz-list-create-action" type="button" data-sign-in-open data-sign-in-return-url="{{ route('quizzes.create') }}">+ {{ __('quiz.create_quiz') }}</button>
            @endif
        </div>
    </div>

    @if($quizzes->isEmpty())
        <div class="quiz-empty-state">{{ __('quiz.empty_list') }}</div>
    @else
        <div class="quiz-list">
            @foreach($quizzes as $quiz)
                <article class="quiz-list-card">
                    <a class="quiz-list-card-image-link" href="{{ route('quizzes.show', $quiz->idx) }}" aria-label="{{ $quiz->title }}">
                        <img class="quiz-list-card-image" src="{{ $quiz->cover_image_url }}" alt="" loading="lazy" decoding="async">
                        @if($quiz->idol_name)<span class="quiz-list-idol-badge" title="{{ $quiz->idol_name }}" translate="no">{{ $quiz->idol_name }}</span>@endif
                        <span class="quiz-list-question-badge">{{ __('quiz.question_badge', ['count' => (int) $quiz->question_count]) }}</span>
                        @if($quiz->completed_today)
                            <span class="quiz-list-completed-badge">{{ __('quiz.today_completed_badge') }}</span>
                        @endif
                        <div class="post-statistics quiz-list-card-statistics">
                            @if($isAdmin)
                                <span class="post-stat post-stat--views" aria-label="{{ __('ui.views_aria', ['amount' => number_format((int) $quiz->quiz_view_count)]) }}" title="{{ __('ui.views_aria', ['amount' => number_format((int) $quiz->quiz_view_count)]) }}"><svg aria-hidden="true"><use href="#icon-eye"/></svg><span>{{ number_format((int) $quiz->quiz_view_count) }}</span></span>
                                <span class="post-stat post-stat--comments" aria-label="{{ __('quiz.participants_count_aria', ['count' => number_format((int) $quiz->quiz_participant_count)]) }}" title="{{ __('quiz.participants_count_aria', ['count' => number_format((int) $quiz->quiz_participant_count)]) }}"><svg aria-hidden="true"><use href="#icon-users"/></svg><span>{{ number_format((int) $quiz->quiz_participant_count) }}</span></span>
                            @endif
                            <span class="post-stat post-stat--recommendations" aria-label="{{ __('ui.recommendations_aria', ['amount' => number_format((int) $quiz->quiz_like_count)]) }}" title="{{ __('ui.recommendations_aria', ['amount' => number_format((int) $quiz->quiz_like_count)]) }}"><svg aria-hidden="true"><use href="#icon-recommend"/></svg><span>{{ number_format((int) $quiz->quiz_like_count) }}</span></span>
                        </div>
                    </a>
                    <div class="quiz-list-card-content">
                        <h3><a href="{{ route('quizzes.show', $quiz->idx) }}">{{ $quiz->title }}</a></h3>
                        <div class="quiz-list-card-author">
                            <x-member-link :member-code="$quiz->creator_member_code" :label="__('ui.member_view_posts_label', ['name' => $quiz->creator_nickname])" class="post-author-avatar-link quiz-list-author-avatar-link"><img src="{{ $quiz->creator_image_url }}" alt="" width="48" height="48" loading="lazy" decoding="async"></x-member-link>
                            <span class="quiz-list-author-copy">
                                <x-member-link :member-code="$quiz->creator_member_code" class="quiz-list-author-name"><x-country-flag :code="$quiz->creator_country_code" /><span translate="no">{{ $quiz->creator_nickname }}</span></x-member-link>
                            </span>
                            @if($rewardEnabled)
                                <span class="quiz-list-reward" aria-label="{{ __('quiz.reward_range', ['label' => __('quiz.perfect_score_reward_label'), 'range' => number_format($rewardMin).'–'.number_format($rewardMax)]) }}">
                                    <svg class="heart-icon--small" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>
                                    <span>+{{ number_format($rewardMin) }}@if($rewardMax !== $rewardMin)~{{ number_format($rewardMax) }}@endif</span>
                                </span>
                            @endif
                        </div>
                        @if(!$quiz->is_owner && $quiz->attempt_status === 'in_progress')
                            <span class="quiz-list-attempt-status is-{{ $quiz->attempt_status }}">{{ __('quiz.status_'.$quiz->attempt_status) }}</span>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="quiz-list-pagination">{{ $quizzes->links() }}</div>
    @endif
</section>
@endsection
