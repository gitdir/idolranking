@extends('layouts.app')

@section('title', 'Boy Ranking | IdolRanking')

@section('content')
    @php
        // Static UI fixtures, in display order. No database access or vote processing.
        $idols = [
            ['name' => 'Jungkook', 'group' => 'BTS', 'hearts' => 2847316, 'posts' => 128, 'image' => 'portrait-blue'],
            ['name' => 'V', 'group' => 'BTS', 'hearts' => 2341002, 'posts' => 96, 'image' => 'portrait-lilac'],
            ['name' => 'Jimin', 'group' => 'BTS', 'hearts' => 1987450, 'posts' => 84, 'image' => 'portrait-peach'],
            ['name' => 'Cha Eun-woo', 'group' => 'ASTRO', 'hearts' => 1654329, 'posts' => 73, 'image' => 'portrait-sage'],
            ['name' => 'Lee Know', 'group' => 'Stray Kids', 'hearts' => 1321887, 'posts' => 0, 'image' => 'portrait-blue'],
            ['name' => 'Hyunjin', 'group' => 'Stray Kids', 'hearts' => 1250420, 'posts' => 62, 'image' => 'portrait-rose'],
            ['name' => 'Felix', 'group' => 'Stray Kids', 'hearts' => 1180340, 'posts' => 55, 'image' => 'portrait-peach'],
            ['name' => 'Mingyu', 'group' => 'SEVENTEEN', 'hearts' => 1095820, 'posts' => 41, 'image' => 'portrait-lilac'],
            ['name' => 'Wonwoo', 'group' => 'SEVENTEEN', 'hearts' => 982640, 'posts' => 0, 'image' => 'portrait-sage'],
            ['name' => 'Yeonjun', 'group' => 'TOMORROW X TOGETHER', 'hearts' => 914250, 'posts' => 38, 'image' => 'portrait-blue'],
            ['name' => 'Soobin', 'group' => 'TOMORROW X TOGETHER', 'hearts' => 875120, 'posts' => 24, 'image' => 'portrait-rose'],
            ['name' => 'Sunghoon', 'group' => 'ENHYPEN', 'hearts' => 802460, 'posts' => 1, 'image' => 'portrait-peach'],
        ];
    @endphp

    <div class="ranking-page">
        {{-- This fixed-size slot can contain a single img.ranking-banner-image instead. --}}
        <section class="ranking-banner" aria-label="Boy Ranking">
            <div class="ranking-banner-copy">
                <h1>Boy Ranking</h1>
                <p>Celebrate your favorites. Every Heart counts.</p>
            </div>
            <img class="ranking-banner-visual" src="{{ asset('images/placeholders/portrait-blue.svg') }}" alt="" width="320" height="320">
        </section>

        <ol class="idol-ranking-grid" aria-label="Boy rankings">
            @foreach ($idols as $idol)
                <li>
                    <x-idol-ranking-card :idol="$idol" :rank="$loop->iteration" />
                </li>
            @endforeach
        </ol>
    </div>
@endsection
