@props(['panel'])

<section id="ranking-{{ $panel['key'] }}" class="ranking-panel ranking-panel--{{ $panel['theme'] }}" aria-labelledby="title-{{ $panel['key'] }}">
    <header class="ranking-heading">
        <h2 id="title-{{ $panel['key'] }}">{{ $panel['title'] }}</h2>
<a class="view-all" href="{{ route(str_contains($panel['key'], 'group') ? 'ranking.group' : 'ranking.idol', ['gender' => str_starts_with($panel['key'], 'boy') ? 'boy' : 'girl']) }}">View All <span aria-hidden="true">&#8250;</span></a>
    </header>
    <ol class="ranking-list">
        @foreach ($panel['members'] as $member)
            @php($detailUrl = route('idol.show', ['idx' => $member['idx']]))
            @php($voteImage = $member['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl())
            <li class="ranking-entry {{ $loop->first ? 'ranking-entry--first' : '' }}">
                @if ($loop->first)
                    {{-- Exactly three independent popular fan-post images, ordered by recommendations. --}}
                    <a class="top-post-images" href="{{ $detailUrl }}" aria-label="View {{ $member[0] }} details">
                        @foreach ($panel['images'] as $image)
                            <img src="{{ $member['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" width="320" height="320" alt="{{ $member[0] }} fan post placeholder {{ $loop->iteration }}">
                        @endforeach
                        <x-ranking-badge :rank="$loop->iteration" />
                    </a>
                @else
                    <a class="ranking-thumbnail-wrap" href="{{ $detailUrl }}" aria-label="View {{ $member[0] }} details">
                        <img class="ranking-thumbnail" src="{{ $member['image_url'] ?? \App\Support\ManagedImageStorage::defaultUrl() }}" width="72" height="72" alt="{{ $member[0] }} placeholder">
                        <x-ranking-badge :rank="$loop->iteration" />
                    </a>
                @endif
                <div class="ranking-member">
                    <h3><a href="{{ $detailUrl }}">{{ $member[0] }}</a></h3>
                        <div class="ranking-row-stats">
                            <p class="ranking-stat heart-total"><svg aria-hidden="true"><use href="#icon-heart"/></svg><span class="sr-only">Hearts: </span><span data-vote-total-display="{{ $member['idx'] }}">{{ number_format($member[1]) }}</span></p>
                            @if (($member[2] ?? 0) > 0)
                                <span class="ranking-stat ranking-row-posts" aria-label="{{ number_format($member[2]) }} fan posts">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($member[2]) }}
                                </span>
                            @endif
                        </div>
                </div>
                <button type="button" class="heart-vote" data-heart-vote-open data-vote-target-id="{{ $member['idx'] }}" data-vote-name="{{ $member[0] }}" data-vote-meta="{{ $member['group'] }}" data-vote-image="{{ $voteImage }}" data-vote-total="{{ $member[1] }}" data-vote-posts="{{ $member[2] ?? 0 }}" data-vote-month="{{ now()->format('Y-m') }}" aria-label="Vote Hearts for {{ $member[0] }}" aria-haspopup="dialog" aria-controls="heart-vote-modal">
                    <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                </button>
            </li>
        @endforeach
    </ol>
</section>
