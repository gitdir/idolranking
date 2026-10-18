@extends('layouts.app')

@section('title', $seo['title'])

@section('content')
    <div class="ranking-page ranking-page--{{ $theme }}">
        <x-ranking-hero :title="$title" :type="$heroType" />

        <div class="ranking-controls">
            <nav class="ranking-gender-filter" aria-label="{{ __('ui.ranking_filter') }}">
                @foreach (['all' => __('ui.all'), 'boy' => __('ui.boy'), 'girl' => __('ui.girl')] as $value => $label)
                    <a href="{{ route('ranking.'.$rankingType, ['gender' => $value, 'month' => $monthValue]) }}" @if($gender === $value) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <nav class="ranking-month-selector" aria-label="{{ __('ui.month') }}">
                <a href="{{ route('ranking.'.$rankingType, ['gender' => $gender, 'month' => $previousMonth]) }}" aria-label="{{ __('ui.previous_month') }}"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 5-7 7 7 7"/></svg></a>
                <span>{{ $monthLabel }}</span>
                @if($nextMonth)
                    <a href="{{ route('ranking.'.$rankingType, ['gender' => $gender, 'month' => $nextMonth]) }}" aria-label="{{ __('ui.next_month') }}"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></a>
                @else
                    <span class="is-disabled" aria-label="{{ __('ui.next_month_unavailable') }}" aria-disabled="true"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></span>
                @endif
            </nav>
        </div>

        @if ($featured)
            <div class="ranking-featured-layout">
                <x-ranking-featured :idol="$featured" :images="$featuredImages" :vote-month="$monthValue" />
                <x-ranking-top-voters :voters="$topVoters" :idol-name="$featured['name']" :month-label="$monthLabel" />
            </div>
        @else
            <p class="fan-post-empty" role="status">{{ __('ui.no_active_rankings', ['type' => $rankingType === 'idol' ? __('ui.idols') : __('ui.groups')]) }}</p>
        @endif

        @if (count($idols) > 1)
            <ol class="idol-ranking-grid" start="2" aria-label="{{ $title }}: rank 2 onward">
                @foreach (array_slice($idols, 1) as $idol)
                    <li>
                        <x-idol-ranking-card :idol="$idol" :rank="$loop->iteration + 1" :vote-month="$monthValue" />
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection
