@props(['idol', 'rank' => null, 'voteMonth' => null])
@php($detailUrl = route('idol.show', ['idx' => $idol['idx']]))

<article class="idol-ranking-card">
    <a class="idol-ranking-media" href="{{ $detailUrl }}" aria-label="View {{ $idol['name'] }} details">
        <img class="idol-ranking-image" src="{{ $idol['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" width="320" height="320" alt="{{ $idol['name'] }} placeholder" loading="lazy">
        @if ($rank !== null)
            {{-- Independent badge slot: the number may later be replaced by an image asset. --}}
            <x-ranking-badge :rank="$rank" />
        @endif
    </a>
    <div class="idol-ranking-info">
        <div class="idol-ranking-name">
            <h2><a href="{{ $detailUrl }}">{{ $idol['name'] }}</a></h2>
            <span>@if (!empty($idol['group_idx']))<a href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $idol['group'] }}</a>@else{{ $idol['group'] }}@endif</span>
        </div>
        <div class="idol-ranking-stats">
            <span class="idol-ranking-hearts" data-vote-total-display="{{ $idol['idx'] }}" aria-label="{{ number_format($idol['hearts']) }} Hearts">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ number_format($idol['hearts']) }}
            </span>
            @if ($idol['posts'] > 0)
                <span class="idol-ranking-posts" aria-label="{{ number_format($idol['posts']) }} fan posts">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['posts']) }}
                </span>
            @endif
        </div>
        <button class="idol-ranking-vote" type="button" data-heart-vote-open data-vote-target-id="{{ $idol['idx'] }}" data-vote-name="{{ $idol['name'] }}" data-vote-meta="{{ $idol['group'] }}" data-vote-image="{{ $idol['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" data-vote-total="{{ $idol['hearts'] }}" data-vote-posts="{{ $idol['posts'] }}" data-vote-month="{{ $voteMonth ?? now()->format('Y-m') }}" aria-label="Vote for {{ $idol['name'] }}" aria-haspopup="dialog" aria-controls="heart-vote-modal">
            <svg aria-hidden="true"><use href="#icon-heart"/></svg>Vote
        </button>
    </div>
</article>
