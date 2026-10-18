@props(['idol', 'images', 'voteMonth' => null])
@php($detailUrl = route('idol.show', ['idx' => $idol['idx']]))

<section class="ranking-featured" aria-labelledby="ranking-featured-name">
    <h2 id="ranking-featured-name" class="sr-only"><a href="{{ $detailUrl }}">{{ $idol['name'] }}</a></h2>
    <a class="ranking-featured-images" href="{{ $detailUrl }}" aria-label="{{ __('ui.popular_rank_details', ['name' => $idol['name']]) }}">
        @foreach ($images as $image)
            <img src="{{ $image }}" width="320" height="320" alt="{{ __('ui.fan_post_image_for', ['name' => $idol['name']]) }}" loading="lazy">
        @endforeach
    </a>
    <dl class="ranking-featured-stats">
        <div>
            <dt>{{ __('ui.current_month_rank') }}</dt>
            <dd><svg class="ranking-featured-crown" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m2 6 5 4 5-7 5 7 5-4-3 13H5Zm3 15h14v2H5Z"/></svg>1</dd>
        </div>
        <div>
            <dt>{{ __('ui.monthly_votes') }}</dt>
            <dd><svg class="ranking-featured-heart" aria-hidden="true"><use href="#icon-heart"/></svg><span data-vote-total-display="{{ $idol['idx'] }}">{{ number_format($idol['hearts']) }}</span></dd>
        </div>
        <div>
            <dt>{{ __('ui.total_fan_posts') }}</dt>
            <dd><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($idol['posts']) }}</dd>
        </div>
    </dl>
    <button class="idol-ranking-vote ranking-featured-vote" type="button" data-heart-vote-open data-vote-target-id="{{ $idol['idx'] }}" data-vote-name="{{ $idol['name'] }}" data-vote-meta="{{ $idol['group'] }}" data-vote-image="{{ $idol['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" data-vote-total="{{ $idol['hearts'] }}" data-vote-posts="{{ $idol['posts'] }}" data-vote-month="{{ $voteMonth ?? now()->format('Y-m') }}" aria-label="{{ __('ui.vote_for_idol', ['name' => $idol['name']]) }}" aria-haspopup="dialog" aria-controls="heart-vote-modal">
        <svg aria-hidden="true"><use href="#icon-heart"/></svg>{{ __('ui.vote_for_idol', ['name' => $idol['name']]) }}
    </button>
</section>
