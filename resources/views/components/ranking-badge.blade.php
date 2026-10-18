@props(['rank'])

<span class="idol-ranking-badge idol-ranking-badge--{{ $rank <= 3 ? $rank : 'default' }}" aria-label="{{ __('ui.rank_label', ['rank' => $rank]) }}">{{ $rank }}</span>
