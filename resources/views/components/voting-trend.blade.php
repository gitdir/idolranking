@props(['days'])

@php
    $hasVotes = count($days) > 0 && array_sum(array_column($days, 'votes')) > 0;
    if ($hasVotes) {
        $ceiling = max(50000, ceil(max(array_column($days, 'votes')) / 50000) * 50000);
        $points = [];
        foreach ($days as $index => $day) {
            $points[] = [
                'x' => 58 + $index * 498 / max(1, count($days) - 1),
                'y' => 202 - $day['votes'] / $ceiling * 164,
                'label' => (new DateTimeImmutable($day['date']))->format('F j, Y').': Daily Votes '.number_format($day['votes']),
            ];
        }
        $line = implode(' ', array_map(fn ($point) => $point['x'].','.$point['y'], $points));
    }
@endphp

@if (!$hasVotes)
    <p class="fan-post-empty" role="status">No voting activity has been recorded in the last 30 days.</p>
@else
<svg class="voting-trend-chart" viewBox="0 0 576 260" role="group" aria-label="Daily votes over the last 30 days. Each point includes its date and vote count.">
    <text x="58" y="18" class="trend-axis-label">Daily Votes</text>
    @for ($tick = 0; $tick <= 3; $tick++)
        @php($y = 202 - $tick * 164 / 3)
        <line class="trend-grid-line" x1="58" x2="556" y1="{{ $y }}" y2="{{ $y }}"/>
        <text class="trend-axis-label" x="50" y="{{ $y + 4 }}" text-anchor="end">{{ number_format($ceiling * $tick / 3) }}</text>
    @endfor
    <polyline class="trend-line" points="{{ $line }}"/>
    @foreach ($points as $point)
        <circle class="trend-point" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" tabindex="0" role="img" aria-label="{{ $point['label'] }}"><title>{{ $point['label'] }}</title></circle>
    @endforeach
    @foreach ([0, 7, 14, 21, 29] as $index)
        <text class="trend-axis-label" x="{{ $points[$index]['x'] }}" y="225" text-anchor="{{ $index === 0 ? 'start' : ($index === 29 ? 'end' : 'middle') }}">{{ (new DateTimeImmutable($days[$index]['date']))->format('M j') }}</text>
    @endforeach
    <text class="trend-axis-label" x="307" y="250" text-anchor="middle">Date</text>
</svg>
@endif
