@props(['rank'])

<span class="idol-ranking-badge idol-ranking-badge--{{ $rank <= 3 ? $rank : 'default' }}" aria-label="Rank {{ $rank }}">{{ $rank }}</span>
