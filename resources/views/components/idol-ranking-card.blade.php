@props(['idol', 'rank' => null, 'voteMonth' => null])
@php($detailUrl = route('idol.show', ['idx' => $idol['idx']]))

<article class="idol-ranking-card">
    <a class="idol-ranking-media" href="{{ $detailUrl }}" aria-label="{{ __('ui.idol_details', ['name' => $idol['name']]) }}">
        <img class="idol-ranking-image" src="{{ $idol['representative_image_url'] ?? $idol['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" width="320" height="320" alt="{{ __('ui.profile_image_alt', ['name' => $idol['name']]) }}" loading="lazy">
        @if ($rank !== null)
            {{-- Independent badge slot: the number may later be replaced by an image asset. --}}
            <x-ranking-badge :rank="$rank" />
        @endif
    </a>
    <div class="idol-ranking-info">
        <div class="idol-ranking-name">
            <h2><a translate="no" href="{{ $detailUrl }}">{{ $idol['name'] }}</a></h2>
            <span translate="no">@if (!empty($idol['group_idx']))<a href="{{ route('idol.show', ['idx' => $idol['group_idx']]) }}">{{ $idol['group'] }}</a>@else{{ $idol['group'] }}@endif</span>
        </div>
        <div class="idol-ranking-stats">
            <span class="idol-ranking-hearts" data-vote-total-display="{{ $idol['idx'] }}" aria-label="{{ __('ui.voter_hearts_aria', ['amount' => number_format($idol['hearts'])]) }}">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ number_format($idol['hearts']) }}
            </span>
            @if ($idol['posts'] > 0)
                <span class="idol-ranking-posts" aria-label="{{ __('ui.fan_posts_count_aria', ['amount' => number_format($idol['posts'])]) }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['posts']) }}
                </span>
            @endif
        </div>
        <button class="idol-ranking-vote" type="button" data-heart-vote-open data-vote-target-id="{{ $idol['idx'] }}" data-vote-name="{{ $idol['name'] }}" data-vote-meta="{{ $idol['group'] }}" data-vote-image="{{ $idol['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" data-vote-total="{{ $idol['hearts'] }}" data-vote-posts="{{ $idol['posts'] }}" data-vote-month="{{ $voteMonth ?? now()->format('Y-m') }}" aria-label="{{ __('ui.vote_idol_aria', ['name' => $idol['name']]) }}" aria-haspopup="dialog" aria-controls="heart-vote-modal">
            <svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('ui.vote') }}
        </button>
    </div>
</article>
