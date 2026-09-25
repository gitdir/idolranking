@extends('layouts.app')

@section('content')
    @php
        // Static display fixtures only; no database reads or ranking calculations.
        $panels = [
            ['key' => 'boy', 'title' => 'Boy Ranking', 'theme' => 'blue', 'images' => ['portrait-blue', 'portrait-lilac', 'portrait-peach'], 'members' => [
                ['Jungkook', 2847316, 128], ['V', 2341002, 96], ['Jimin', 1987450, 84], ['Cha Eun-woo', 1654329, 73], ['Lee Know', 1321887, 0],
            ]],
            ['key' => 'girl', 'title' => 'Girl Ranking', 'theme' => 'pink', 'images' => ['portrait-rose', 'portrait-sage', 'portrait-peach'], 'members' => [
                ['IU', 2615920, 112], ['Jennie', 2308441, 128], ['Jisoo', 1972650, 128], ['Rosé', 1725093, 128], ['Karina', 1413882, 0],
            ]],
            ['key' => 'boy-group', 'title' => 'Boy Group Ranking', 'theme' => 'purple', 'images' => ['group-blue', 'group-lilac', 'group-rose'], 'members' => [
                ['BTS', 3947125, 256], ['SEVENTEEN', 2615480, 128], ['Stray Kids', 2102893, 128], ['TXT', 1546309, 128], ['ENHYPEN', 1284903, 0],
            ]],
            ['key' => 'girl-group', 'title' => 'Girl Group Ranking', 'theme' => 'pink', 'images' => ['group-rose', 'group-lilac', 'group-blue'], 'members' => [
                ['BLACKPINK', 3842661, 224], ['TWICE', 2711522, 128], ['aespa', 2084392, 128], ['IVE', 1688420, 0], ['LE SSERAFIM', 1320115, 128],
            ]],
        ];
        $posts = [
            ['portrait-blue', 'golden_army', 'His smile always makes my day brighter ✨', 933115, 12841, 320, 'square'],
            ['portrait-peach', 'ocean_day', 'A little sunshine by the sea.', 5421, 923, 27, 'landscape'],
            ['portrait-sage', 'lily_iu', "Spring with IU 🌸 She's so lovely!", 9672, 1245, 86, 'square'],
            ['portrait-lilac', 'winter_bloom', 'The softest winter mood ❄️', 4882, 781, 36, 'portrait'],
            ['group-blue', 'bangtan4ever', '7 together, always and forever ✨', 11423, 2341, 102, 'square'],
            ['group-rose', 'stage_diary', 'Every stage is a new favorite.', 9334, 2115, 72, 'landscape'],
            ['portrait-rose', 'rubyjane', 'Jennie in pink hits different 🌷', 8311, 1932, 64, 'square'],
            ['portrait-blue', 'blue_hour', 'This moment deserves a little replay.', 6221, 1054, 41, 'portrait'],
            ['portrait-lilac', 'thv.world', 'A work of art ✨', 7905, 1122, 65, 'landscape'],
            ['portrait-sage', 'spring_note', 'A smile to brighten your day.', 7662, 1988, 59, 'square'],
            ['group-rose', 'blinkarea', 'BLACKPINK forever and always', 10201, 2884, 91, 'square'],
            ['group-lilac', 'together_7', 'Our favorite kind of group photo.', 8115, 2421, 67, 'landscape'],
            ['portrait-peach', 'roseverse', 'Rosé and her cat are the cutest 🐱', 6784, 1432, 38, 'square'],
            ['portrait-blue', 'midnight_fan', 'Keeping this one in my favorites.', 5934, 1331, 33, 'square'],
        ];
    @endphp

    <h1 class="sr-only">Idol rankings and latest fan posts</h1>
    <section class="rankings" aria-label="Idol rankings">
        @foreach ($panels as $panel)
            <x-ranking-panel :panel="$panel" />
        @endforeach
    </section>

    <section class="fan-posts" aria-labelledby="fan-posts-title">
        <h2 id="fan-posts-title">Latest Fan Posts</h2>
        <div class="fan-post-grid">
            @foreach ($posts as $post)
                <x-fan-post-card :post="$post" />
            @endforeach
        </div>
    </section>
@endsection
