@extends('layouts.app')

@section('content')
    @php
        // Ranking panel presentation remains unchanged; public ranking data replaces these fallback rows.
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
        foreach ($panels as $panelIndex => $panel) {
            $visible = $publicRankings[$panel['key']] ?? [];
            $panels[$panelIndex]['members'] = array_map(fn(array $idol) => [
                $idol['name'], $idol['hearts'], $idol['posts'], 'idx'=>$idol['idx'], 'group'=>$idol['group'], 'image'=>$idol['image'], 'image_url'=>$idol['image_url'] ?? null,
            ], array_slice($visible, 0, 5));
            $panels[$panelIndex]['images'] = array_pad(array_slice(array_column($visible, 'image'), 0, 3), 3, $panel['images'][0]);
        }
    @endphp

    <h1 class="sr-only">Idol rankings and latest fan posts</h1>
    <section class="rankings" aria-label="Idol rankings">
        @foreach ($panels as $panel)
            <x-ranking-panel :panel="$panel" />
        @endforeach
    </section>

    <x-fan-post-list :posts="$posts" heading-id="home-fan-posts-title" empty-message="No fan posts yet.">
        <x-slot:heading>
            <div class="member-post-list-heading public-fan-posts-heading">
                <div><h2 id="home-fan-posts-title">Fan Posts</h2></div>
                <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                    <form method="get" action="{{ route('home') }}">
                        <label class="sr-only" for="home-fan-post-sort">Sort fan posts</label>
                        <select id="home-fan-post-sort" name="sort" onchange="this.form.submit()">
                            <option value="latest" @selected($fanPostSort === 'latest')>Latest</option>
                            <option value="liked" @selected($fanPostSort === 'liked')>Most Liked</option>
                            <option value="commented" @selected($fanPostSort === 'commented')>Most Commented</option>
                        </select>
                        <noscript><button type="submit">Apply</button></noscript>
                    </form>
                    @if (request()->attributes->get('currentMember'))
                        <a class="idol-detail-create-post" href="{{ route('fan-post.create') }}">+ Create Fan Post</a>
                    @else
                        <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">+ Create Fan Post</button>
                    @endif
                </div>
            </div>
        </x-slot:heading>
    </x-fan-post-list>
@endsection
