@props(['days'])

@php
    $hasDays = count($days) > 0;
    if ($hasDays) {
        $maximum = max(array_column($days, 'votes'));
        $ceiling = max(3, (int) (ceil($maximum / 3) * 3));
        $points = [];
        foreach ($days as $index => $day) {
            $points[] = [
                'x' => 38 + $index * 518 / max(1, count($days) - 1),
                'y' => 202 - $day['votes'] / $ceiling * 164,
                'date' => (new DateTimeImmutable($day['date']))->format('Y-m-d'),
                'votes' => (int) $day['votes'],
            ];
        }
        $line = implode(' ', array_map(fn ($point) => $point['x'].','.$point['y'], $points));
    }
@endphp

@if (!$hasDays)
    <p class="fan-post-empty" role="status">{{ __('ui.no_vote_activity_30_days') }}</p>
@else
<div class="voting-trend-chart-wrap" data-voting-trend data-heart-label="{{ __('ui.hearts') }}">
<svg class="voting-trend-chart" viewBox="0 0 576 260" role="group" aria-label="{{ __('ui.daily_votes_chart_aria') }}">
    <text x="0" y="18" class="trend-axis-label">{{ __('ui.daily_votes') }}</text>
    @for ($tick = 0; $tick <= 3; $tick++)
        @php($y = 202 - $tick * 164 / 3)
        <line class="trend-grid-line" x1="38" x2="556" y1="{{ $y }}" y2="{{ $y }}"/>
        <text class="trend-axis-label" x="30" y="{{ $y + 4 }}" text-anchor="end">{{ number_format($ceiling * $tick / 3) }}</text>
    @endfor
    <polyline class="trend-line" points="{{ $line }}"/>
    @foreach ($points as $point)
        <circle class="trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" tabindex="0" role="img" data-date="{{ $point['date'] }}" data-votes="{{ $point['votes'] }}" aria-label="{{ $point['date'] }}: {{ number_format($point['votes']) }} {{ __('ui.hearts') }}"/>
    @endforeach
    @foreach ([0, 7, 14, 21, 29] as $index)
        <text class="trend-axis-label" x="{{ $points[$index]['x'] }}" y="225" text-anchor="{{ $index === 0 ? 'start' : ($index === 29 ? 'end' : 'middle') }}">{{ (new DateTimeImmutable($days[$index]['date']))->format('m-d') }}</text>
    @endforeach
    <text class="trend-axis-label" x="307" y="250" text-anchor="middle">{{ __('ui.date') }}</text>
</svg>
<div class="voting-trend-tooltip" data-voting-trend-tooltip role="status" aria-live="polite" hidden></div>
</div>
@endif
