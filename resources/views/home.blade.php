@extends('layouts.app')

@section('content')
    @php
        $panels = [
            ['key' => 'boy', 'title' => 'Boy Ranking', 'theme' => 'blue'],
            ['key' => 'girl', 'title' => 'Girl Ranking', 'theme' => 'pink'],
            ['key' => 'boy-group', 'title' => 'Boy Group Ranking', 'theme' => 'purple'],
            ['key' => 'girl-group', 'title' => 'Girl Group Ranking', 'theme' => 'pink'],
        ];
        foreach ($panels as $panelIndex => $panel) {
            $visible = $publicRankings[$panel['key']] ?? [];
            $panels[$panelIndex]['members'] = array_map(fn (array $idol) => [
                $idol['name'], $idol['hearts'], $idol['posts'], 'idx' => $idol['idx'],
                'group' => $idol['group'], 'image_url' => $idol['image_url'] ?? null,
            ], array_slice($visible, 0, 5));
            $panels[$panelIndex]['images'] = $rankingImages[$panel['key']] ?? [];
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
