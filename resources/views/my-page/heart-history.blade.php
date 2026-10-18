@extends('layouts.app')
@section('title', __('ui.heart_history').' | IdolRanking')
@section('content')
    <div class="my-page heart-history-page">
        <header class="my-page-title">
            <h1>{{ __('ui.heart_history') }}</h1>
            <p>{{ __('ui.heart_history_description') }}</p>
        </header>
        <div class="my-page-content">
            <a class="heart-history-back" href="{{ route('my-page') }}">{{ __('ui.back_to_my_page') }}</a>
            <section class="heart-history-balance" aria-label="{{ __('ui.current_heart_balance') }}">
                <span class="my-hearts-icon"><svg aria-hidden="true"><use href="#icon-heart"/></svg></span>
                <span>{{ __('ui.current_heart_balance') }}</span>
                <strong>{{ number_format((int) $currentMember->heart_balance) }} {{ __('ui.heart_unit') }}</strong>
            </section>
            <nav class="heart-history-filters" aria-label="{{ __('ui.heart_history_filters') }}">
                @foreach (['all', 'earned', 'spent'] as $option)
                    <a href="{{ route('my-page.heart-history', ['filter' => $option]) }}" @class(['is-active' => $filter === $option]) @if($filter === $option) aria-current="page" @endif>{{ __('ui.heart_filter_'.$option) }}</a>
                @endforeach
            </nav>
            <section class="my-heart-history" aria-labelledby="heart-history-list-title">
                <h2 id="heart-history-list-title">{{ __('ui.heart_history') }}</h2>
                @include('my-page.partials.heart-history-entries', ['heartHistories' => $heartHistories])
            </section>
        </div>
    </div>
@endsection
