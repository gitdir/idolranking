@extends('layouts.app')

@section('title', 'Search Results | IdolRanking')

@section('content')
<div class="search-results-page">
    <h1>Search Results</h1>

    @if ($query !== '')
        <header class="search-summary">
            <h2>Search results for &ldquo;{{ $query }}&rdquo;</h2>
            <p>{{ number_format($resultTotal) }} {{ Str::plural('result', $resultTotal) }} found</p>
        </header>
    @endif

    <nav class="search-tabs" aria-label="Search result type">
        @foreach (['all' => 'All', 'idol' => 'Idol', 'group' => 'Group', 'fan-posts' => 'Fan Posts'] as $tab => $label)
            @php($count = $tab === 'all' ? $total : $counts[$tab])
            <a href="{{ route('search', array_filter(['q' => $query, 'type' => $tab === 'all' ? null : $tab])) }}" @if($type === $tab) aria-current="page" @endif>{{ $label }} <span>{{ number_format($count) }}</span></a>
        @endforeach
    </nav>

    @if ($query === '')
        <div class="search-empty"><strong>Enter a keyword to search.</strong><span>Use the search field in the header to find an Idol, Group, or Fan Post.</span></div>
    @else
        @if ($resultTotal === 0)
            <div class="search-empty"><strong>No results found for &ldquo;{{ $query }}&rdquo;</strong><span>Try another keyword.</span></div>
        @else
            @if (($type === 'all' || $type === 'idol') && $counts['idol'])
                <section class="search-section" aria-labelledby="search-idol-title">
                    <div class="search-section-heading">
                        <h2 id="search-idol-title">Idol ({{ number_format($counts['idol']) }})</h2>
                        @if($type === 'all' && $counts['idol'] > 5)<a href="{{ route('search', ['q' => $query, 'type' => 'idol']) }}">View All <span aria-hidden="true">&#8250;</span></a>@endif
                    </div>
                    <ul class="search-entity-list">
                        @foreach($idols as $idol)<li><x-idol-ranking-card :idol="$idol" /></li>@endforeach
                    </ul>
                </section>
            @endif

            @if (($type === 'all' || $type === 'group') && $counts['group'])
                <section class="search-section" aria-labelledby="search-group-title">
                    <div class="search-section-heading">
                        <h2 id="search-group-title">Group ({{ number_format($counts['group']) }})</h2>
                        @if($type === 'all' && $counts['group'] > 5)<a href="{{ route('search', ['q' => $query, 'type' => 'group']) }}">View All <span aria-hidden="true">&#8250;</span></a>@endif
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
                            <h2 id="search-fan-posts-title">Fan Posts @if($type === 'all')({{ count($fanPosts) }} of {{ number_format($counts['fan-posts']) }})@else({{ number_format($counts['fan-posts']) }})@endif</h2>
                            <div>
                                @if($type === 'all' && $counts['fan-posts'] > 10)<a href="{{ route('search', ['q' => $query, 'type' => 'fan-posts', 'sort' => $sort]) }}">View All <span aria-hidden="true">&#8250;</span></a>@endif
                                <form method="get" action="{{ route('search') }}"><input type="hidden" name="q" value="{{ $query }}"><input type="hidden" name="type" value="{{ $type === 'all' ? 'all' : 'fan-posts' }}"><label class="sr-only" for="search-fan-post-sort">Sort fan posts</label><select id="search-fan-post-sort" name="sort" onchange="this.form.submit()"><option value="latest" @selected($sort === 'latest')>Latest</option><option value="liked" @selected($sort === 'liked')>Most Liked</option><option value="commented" @selected($sort === 'commented')>Most Commented</option></select><noscript><button type="submit">Apply</button></noscript></form>
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
