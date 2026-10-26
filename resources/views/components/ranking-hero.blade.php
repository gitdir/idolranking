@props(['title', 'type' => 'boy', 'gender' => 'all'])

@if (in_array($type, ['boy', 'girl'], true))
    @php
        $rankingTitlePrefix = match ($gender) { 'boy' => 'Boy', 'girl' => 'Girl', default => 'Idol' };
    @endphp
    <section class="page-hero-banner ranking-banner ranking-banner--{{ $type }} ranking-banner--idol ranking-banner--idol-{{ $gender }}" aria-labelledby="ranking-hero-title">
        <div class="ranking-hero-copy">
            <h1 id="ranking-hero-title"><span class="ranking-hero-title-accent ranking-hero-title-accent--{{ $gender }}">{{ $rankingTitlePrefix }}</span> <span>Ranking</span></h1>
            <p>{{ __('ui.idol_ranking_hero_subtitle') }}</p>
        </div>
        <div class="ranking-hero-art" aria-hidden="true">
            <span class="ranking-hero-circle ranking-hero-circle--lavender"></span>
            <span class="ranking-hero-circle ranking-hero-circle--pink"></span>
            <span class="ranking-hero-circle ranking-hero-circle--blue"></span>
            <span class="ranking-hero-rings"></span>
            <span class="ranking-hero-dot ranking-hero-dot--one"></span>
            <span class="ranking-hero-dot ranking-hero-dot--two"></span>
            <span class="ranking-hero-sparkle ranking-hero-sparkle--one"></span>
            <span class="ranking-hero-sparkle ranking-hero-sparkle--two"></span>
            <span class="ranking-hero-sparkle ranking-hero-sparkle--three"></span>
            @if ($gender === 'all')
                <span class="ranking-hero-heart"><svg viewBox="0 0 48 48"><path d="M24 42 6.7 25.3C-4.8 13.7 11.2-1.4 24 11.5 36.8-1.4 52.8 13.7 41.3 25.3Z"/></svg></span>
            @elseif ($gender === 'boy')
                <span class="ranking-hero-heart ranking-hero-heart--star"><svg viewBox="0 0 100 100"><path d="m50 8 12.5 27.7 30.2 3.2-22.3 20.6 5.8 29.8L50 74.2 23.8 89.3l5.8-29.8L7.3 38.9l30.2-3.2L50 8Z"/></svg></span>
            @else
                <span class="ranking-hero-heart ranking-hero-heart--bow"><svg viewBox="0 0 120 90"><path d="M57 43C42 28 23 9 10 15 4 18 9 34 23 45 9 56 4 72 10 75c13 6 32-13 47-28m6-4c15-15 34-34 47-28 6 3 1 19-13 30 14 11 19 27 13 30-13 6-32-13-47-28m-6 0c7-9 13-9 20 0-7 9-13 9-20 0Z"/><path d="m30 49-8 34 25-24m43-10 8 34-25-24"/></svg></span>
            @endif
            <span class="ranking-hero-dot-grid"></span>
        </div>
    </section>
@else
    @php
        $groupTitleWords = match ($gender) {
            'boy' => ['Boy', 'Group'],
            'girl' => ['Girl', 'Group'],
            default => ['Group'],
        };
    @endphp
    <section class="page-hero-banner ranking-banner ranking-banner--{{ $type }} ranking-banner--idol ranking-banner--group ranking-banner--group-{{ $gender }}" aria-labelledby="ranking-hero-title">
        <div class="ranking-hero-copy">
            <h1 id="ranking-hero-title">
                @foreach ($groupTitleWords as $word)
                    <span class="ranking-hero-title-accent ranking-hero-title-accent--{{ $gender }}">{{ $word }}</span>
                @endforeach
                <span>Ranking</span>
            </h1>
            <p>{{ __('ui.idol_ranking_hero_subtitle') }}</p>
        </div>
        <div class="ranking-group-hero-art" aria-hidden="true">
            @if ($gender === 'boy')
                <svg class="ranking-group-symbol ranking-group-symbol--star" viewBox="0 0 100 100"><path d="m50 7 11.8 28.4 30.6 2.3-23.4 19.8 7.3 29.8L50 71.2 23.7 87.3 31 57.5 7.6 37.7l30.6-2.3L50 7Z"/></svg>
                <span class="ranking-group-sparkle ranking-group-sparkle--one"></span>
                <span class="ranking-group-sparkle ranking-group-sparkle--two"></span>
                <span class="ranking-group-sparkle ranking-group-sparkle--three"></span>
            @elseif ($gender === 'girl')
                <svg class="ranking-group-symbol ranking-group-symbol--bow" viewBox="0 0 160 100"><path d="M76 48C57 31 35 9 13 14 4 16 8 37 21 50 8 63 4 84 13 86 35 91 57 68 76 52m8-4c19-17 41-39 63-34 9 2 5 23-8 36 13 13 17 34 8 36-22 5-44-18-63-34m-8-2c8-9 16-9 24 0-8 10-16 10-24 0Z"/><path d="M24 50h112M80 51l-8 39m8-39 10 39"/></svg>
                <span class="ranking-group-sparkle ranking-group-sparkle--one"></span>
                <span class="ranking-group-sparkle ranking-group-sparkle--two"></span>
                <span class="ranking-group-sparkle ranking-group-sparkle--three"></span>
            @else
                <span class="ranking-group-orbit"></span>
                <svg class="ranking-group-symbol ranking-group-symbol--crown" viewBox="0 0 100 80"><path d="m13 22 22 17 15-28 15 28 22-17-9 43H22l-9-43Z"/><path d="M25 73h50"/><circle cx="13" cy="18" r="4"/><circle cx="50" cy="9" r="4"/><circle cx="87" cy="18" r="4"/></svg>
                <span class="ranking-group-sparkle ranking-group-sparkle--one"></span>
                <span class="ranking-group-sparkle ranking-group-sparkle--two"></span>
            @endif
        </div>
    </section>
@endif
