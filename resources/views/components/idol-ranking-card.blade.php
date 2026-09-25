@props(['idol', 'rank'])

<article class="idol-ranking-card">
    <div class="idol-ranking-media">
        <img class="idol-ranking-image" src="{{ asset('images/placeholders/'.$idol['image'].'.svg') }}" width="320" height="320" alt="{{ $idol['name'] }} placeholder" loading="lazy">
        {{-- Independent badge slot: the number may later be replaced by an image asset. --}}
        <x-ranking-badge :rank="$rank" />
    </div>
    <div class="idol-ranking-info">
        <div class="idol-ranking-name">
            <h2>{{ $idol['name'] }}</h2>
            <span>{{ $idol['group'] }}</span>
        </div>
        <div class="idol-ranking-stats">
            <span class="idol-ranking-hearts" aria-label="{{ number_format($idol['hearts']) }} Hearts">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ number_format($idol['hearts']) }}
            </span>
            @if ($idol['posts'] > 0)
                <span class="idol-ranking-posts" aria-label="{{ number_format($idol['posts']) }} fan posts">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['posts']) }}
                </span>
            @endif
        </div>
        <button class="idol-ranking-vote" type="button" disabled aria-label="Vote for {{ $idol['name'] }} (unavailable in preview)" title="Voting is not available in this preview">
            <svg aria-hidden="true"><use href="#icon-heart"/></svg>Vote
        </button>
    </div>
</article>
