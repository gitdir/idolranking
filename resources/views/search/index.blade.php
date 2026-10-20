@extends('layouts.app')

@section('title', __('ui.search_results').' | IdolRanking')

@section('content')
<div class="search-results-page">
    <h1>{{ __('ui.search_results') }}</h1>
    <form class="search-results-form" action="{{ route('search') }}" method="get" role="search">
        <label class="sr-only" for="search-results-query">{{ __('ui.search_placeholder') }}</label>
        <div class="search-dialog-input-wrap">
            <svg class="search-dialog-input-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
            <input id="search-results-query" name="q" type="search" value="{{ $query }}" placeholder="{{ __('ui.search_placeholder') }}" spellcheck="false">
        </div>
        <button class="search-dialog-submit" type="submit" aria-label="{{ __('ui.search') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
        </button>
    </form>

    @if ($query !== '')
        <header class="search-summary">
            <h2>{{ __('ui.search_results_for', ['query' => $query]) }}</h2>
            <p>{{ __('ui.results_found', ['amount' => number_format($resultTotal)]) }}</p>
        </header>
    @endif

    <nav class="search-tabs" aria-label="{{ __('ui.search_result_type') }}">
        @foreach (['all' => __('ui.all'), 'idol' => __('ui.idol'), 'group' => __('ui.group'), 'fan-posts' => __('ui.fan_posts')] as $tab => $label)
            @php($count = $tab === 'all' ? $total : $counts[$tab])
            <a href="{{ route('search', array_filter(['q' => $query, 'type' => $tab === 'all' ? null : $tab])) }}" @if($type === $tab) aria-current="page" @endif>{{ $label }} <span>{{ number_format($count) }}</span></a>
        @endforeach
    </nav>

    @if ($query === '')
        <div class="search-empty"><strong>{{ __('ui.enter_keyword') }}</strong><span>{{ __('ui.search_help') }}</span></div>
    @else
        @if ($resultTotal === 0)
            <div class="search-empty"><strong>{{ __('ui.no_results_for', ['query' => $query]) }}</strong><span>{{ __('ui.try_keyword') }}</span></div>
        @else
            @if (($type === 'all' || $type === 'idol') && $counts['idol'])
                <section class="search-section" aria-labelledby="search-idol-title">
                    <div class="search-section-heading">
                        <h2 id="search-idol-title">{{ __('ui.result_count', ['name' => __('ui.idol'), 'amount' => number_format($counts['idol'])]) }}</h2>
                        @if($type === 'all' && $counts['idol'] > 5)<a href="{{ route('search', ['q' => $query, 'type' => 'idol']) }}">{{ __('ui.view_all') }} <span aria-hidden="true">&#8250;</span></a>@endif
                    </div>
                    <ul class="search-entity-list">
                        @foreach($idols as $idol)<li><x-idol-ranking-card :idol="$idol" /></li>@endforeach
                    </ul>
                </section>
            @endif

            @if (($type === 'all' || $type === 'group') && $counts['group'])
                <section class="search-section" aria-labelledby="search-group-title">
                    <div class="search-section-heading">
                        <h2 id="search-group-title">{{ __('ui.result_count', ['name' => __('ui.group'), 'amount' => number_format($counts['group'])]) }}</h2>
                        @if($type === 'all' && $counts['group'] > 5)<a href="{{ route('search', ['q' => $query, 'type' => 'group']) }}">{{ __('ui.view_all') }} <span aria-hidden="true">&#8250;</span></a>@endif
                    </div>
                    <ul class="search-entity-list">
                        @foreach($groups as $group)<li><x-idol-ranking-card :idol="$group" /></li>@endforeach
                    </ul>
                </section>
            @endif

            @if (($type === 'all' || $type === 'fan-posts') && $counts['fan-posts'])
                <x-fan-post-list :posts="$fanPosts" heading-id="search-fan-posts-title" layout="search">
                    <x-slot:heading>
                        <div class="search-section-heading search-fan-post-heading">
                            <h2 id="search-fan-posts-title"><span translate="no">Fan Posts</span> @if($type === 'all')({{ __('ui.result_count_partial', ['shown' => count($fanPosts), 'total' => number_format($counts['fan-posts'])]) }})@else({{ number_format($counts['fan-posts']) }})@endif</h2>
                            <div>
                                @if($type === 'all' && $counts['fan-posts'] > 10)<a href="{{ route('search', ['q' => $query, 'type' => 'fan-posts', 'sort' => $sort]) }}">{{ __('ui.view_all') }} <span aria-hidden="true">&#8250;</span></a>@endif
                                <form method="get" action="{{ route('search') }}"><input type="hidden" name="q" value="{{ $query }}"><input type="hidden" name="type" value="{{ $type === 'all' ? 'all' : 'fan-posts' }}"><label class="sr-only" for="search-fan-post-sort">{{ __('ui.sort_fan_posts') }}</label><select id="search-fan-post-sort" name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>{{ __('ui.latest') }}</option><option value="monthly_liked" @selected($sort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option><option value="liked" @selected($sort === 'liked')>{{ __('ui.most_liked') }}</option><option value="commented" @selected($sort === 'commented')>{{ __('ui.most_commented') }}</option></select><noscript><button type="submit">{{ __('ui.apply') }}</button></noscript></form>
                            </div>
                        </div>
                    </x-slot:heading>
                </x-fan-post-list>
            @endif

            @if($paginator)<x-number-pagination :paginator="$paginator" />@endif
        @endif
    @endif
</div>
@endsection
