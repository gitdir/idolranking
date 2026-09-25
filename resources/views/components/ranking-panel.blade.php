@props(['panel'])

<section id="ranking-{{ $panel['key'] }}" class="ranking-panel ranking-panel--{{ $panel['theme'] }}" aria-labelledby="title-{{ $panel['key'] }}">
    <header class="ranking-heading">
        <h2 id="title-{{ $panel['key'] }}">{{ $panel['title'] }}</h2>
        @if ($panel['key'] === 'boy')
            <a class="view-all" href="{{ route('ranking.boy') }}">View All <span aria-hidden="true">&#8250;</span></a>
        @else
            <button class="view-all" type="button" disabled title="Full ranking is not available in this preview">View All <span aria-hidden="true">›</span></button>
        @endif
    </header>
    <ol class="ranking-list">
        @foreach ($panel['members'] as $member)
            <li class="ranking-entry {{ $loop->first ? 'ranking-entry--first' : '' }}">
                @if ($loop->first)
                    {{-- Exactly three independent popular fan-post images, ordered by recommendations. --}}
                    <div class="top-post-images">
                        @foreach ($panel['images'] as $image)
                            <img src="{{ asset('images/placeholders/'.$image.'.svg') }}" width="320" height="320" alt="{{ $member[0] }} fan post placeholder {{ $loop->iteration }}">
                        @endforeach
                        <x-ranking-badge :rank="$loop->iteration" />
                    </div>
                @else
                    <div class="ranking-thumbnail-wrap">
                        <img class="ranking-thumbnail" src="{{ asset('images/placeholders/'.$panel['images'][($loop->index - 1) % 3].'.svg') }}" width="72" height="72" alt="{{ $member[0] }} placeholder">
                        <x-ranking-badge :rank="$loop->iteration" />
                    </div>
                @endif
                <div class="ranking-member">
                    <h3>{{ $member[0] }}</h3>
                        <div class="ranking-row-stats">
                            <p class="ranking-stat heart-total"><svg aria-hidden="true"><use href="#icon-heart"/></svg><span class="sr-only">Hearts: </span>{{ number_format($member[1]) }}</p>
                            @if (($member[2] ?? 0) > 0)
                                <span class="ranking-stat ranking-row-posts" aria-label="{{ number_format($member[2]) }} fan posts">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8" cy="8" r="1.5"/><path d="m4 17 5-5 4 4 3-3 5 5"/></svg>{{ number_format($member[2]) }}
                                </span>
                            @endif
                        </div>
                </div>
                <button type="button" class="heart-vote" disabled aria-label="Vote Hearts for {{ $member[0] }} (unavailable in preview)" title="Heart voting is not available in this preview">
                    <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                </button>
            </li>
        @endforeach
    </ol>
</section>
