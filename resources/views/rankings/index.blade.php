@extends('layouts.app')

@section('title', $title.' | IdolRanking')

@section('content')
    <div class="ranking-page ranking-page--{{ $theme }}">
        <x-ranking-hero :title="$title" :type="$heroType" />

        <div class="ranking-controls">
            <nav class="ranking-gender-filter" aria-label="Ranking gender filter">
                @foreach (['all' => 'All', 'boy' => 'Boy', 'girl' => 'Girl'] as $value => $label)
                    <a href="{{ route('ranking.'.$rankingType, ['gender' => $value, 'month' => $monthValue]) }}" @if($gender === $value) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <nav class="ranking-month-selector" aria-label="Ranking month">
                <a href="{{ route('ranking.'.$rankingType, ['gender' => $gender, 'month' => $previousMonth]) }}" aria-label="Previous month">&#8249;</a>
                <span>{{ $monthLabel }}</span>
                @if($nextMonth)
                    <a href="{{ route('ranking.'.$rankingType, ['gender' => $gender, 'month' => $nextMonth]) }}" aria-label="Next month">&#8250;</a>
                @else
                    <span class="is-disabled" aria-label="Next month unavailable" aria-disabled="true">&#8250;</span>
                @endif
            </nav>
        </div>

        @if ($featured)
            <div class="ranking-featured-layout">
                <x-ranking-featured :idol="$featured" :images="$featuredImages" :vote-month="$monthValue" />
                <x-ranking-top-voters :voters="$topVoters" :idol-name="$featured['name']" :month-label="$monthLabel" />
            </div>
        @endif

        <ol class="idol-ranking-grid" start="2" aria-label="{{ $title }}: rank 2 onward">
            @foreach (array_slice($idols, 1) as $idol)
                <li>
                    <x-idol-ranking-card :idol="$idol" :rank="$loop->iteration + 1" :vote-month="$monthValue" />
                </li>
            @endforeach
        </ol>
    </div>
@endsection
