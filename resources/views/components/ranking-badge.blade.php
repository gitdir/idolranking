@props(['rank'])

@if ($rank <= 3)
    <img class="idol-ranking-badge idol-ranking-badge--{{ $rank }}" src="{{ asset('images/rank'.$rank.'-glow.png') }}" width="36" height="46" alt="{{ __('ui.rank_label', ['rank' => $rank]) }}">
@else
    <span class="idol-ranking-badge idol-ranking-badge--default" aria-label="{{ __('ui.rank_label', ['rank' => $rank]) }}">{{ $rank }}</span>
@endif
