@extends('layouts.app')

@section('content')
    @php
        $homeRankingTitle = static function (string $gender, bool $isGroup = false): string {
            if ($isGroup) {
                return __('ui.homepage_'.$gender.'_group_ranking');
            }

            $genderLabel = __('ui.'.$gender);
            $categoryLabel = __('ui.idol');

            if (app()->getLocale() === 'id') {
                return __('ui.ranking').' '.$categoryLabel.' '.$genderLabel;
            }

            return $genderLabel.' '.__('ui.ranking');
        };
        $panels = [
            ['key' => 'boy', 'title' => $homeRankingTitle('boy'), 'theme' => 'blue'],
            ['key' => 'girl', 'title' => $homeRankingTitle('girl'), 'theme' => 'pink'],
            ['key' => 'boy-group', 'title' => $homeRankingTitle('boy', true), 'theme' => 'purple'],
            ['key' => 'girl-group', 'title' => $homeRankingTitle('girl', true), 'theme' => 'pink'],
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

    <h1 class="sr-only">{{ __('ui.ranking') }} / {{ __('ui.fan_posts') }}</h1>
    <div class="home-actions">
        @if (request()->attributes->get('currentMember'))
            <a href="{{ route('idol-registration.create') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3M19 3v6m-3-3h6"/></svg>
                <span>{{ __('ui.register_idol') }}</span>
            </a>
        @else
            <button type="button" data-sign-in-open data-sign-in-return-url="{{ route('idol-registration.create') }}" aria-haspopup="dialog" aria-controls="sign-in-modal">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c.7-4.2 3.4-6.3 8-6.3s7.3 2.1 8 6.3M19 3v6m-3-3h6"/></svg>
                <span>{{ __('ui.register_idol') }}</span>
            </button>
        @endif
        <button type="button" data-invite-friends-open data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}" @unless(request()->attributes->get('currentMember')) data-sign-in-open aria-controls="sign-in-modal" @else aria-controls="invite-friends-dialog" @endunless aria-haspopup="dialog">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 12v9H4v-9m-2-5h20v5H2Zm10 0v14m0-14H7.5a2.5 2.5 0 1 1 0-5C11 3 12 7 12 7Zm0 0h4.5a2.5 2.5 0 1 0 0-5C13 3 12 7 12 7Z"/></svg>
            <span>{{ __('ui.invite_friends') }}</span>
        </button>
    </div>
    <section class="rankings" aria-label="{{ __('ui.ranking') }}">
        @foreach ($panels as $panel)
            <x-ranking-panel :panel="$panel" />
        @endforeach
    </section>

    <x-fan-post-list :posts="$posts" heading-id="home-fan-posts-title" :empty-message="__('ui.no_fan_posts')">
        <x-slot:heading>
            <div class="member-post-list-heading public-fan-posts-heading">
                <div><h2 id="home-fan-posts-title">{{ __('ui.fan_posts') }}</h2></div>
                <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                    <form method="get" action="{{ route('home') }}">
                        <label class="sr-only" for="home-fan-post-sort">{{ __('ui.sort_fan_posts') }}</label>
                        <select id="home-fan-post-sort" class="fan-post-sort-select" name="sort" onchange="this.form.submit()">
                            <option value="latest" @selected($fanPostSort === 'latest')>{{ __('ui.latest') }}</option>
                            <option value="monthly_liked" @selected($fanPostSort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option>
                            <option value="liked" @selected($fanPostSort === 'liked')>{{ __('ui.most_liked') }}</option>
                            <option value="commented" @selected($fanPostSort === 'commented')>{{ __('ui.most_commented') }}</option>
                        </select>
                        <noscript><button type="submit">{{ __('ui.apply') }}</button></noscript>
                    </form>
                    @if (request()->attributes->get('currentMember'))
                        <a class="idol-detail-create-post" href="{{ route('fan-post.create') }}">+ {{ __('ui.create_fan_post') }}</a>
                    @else
                        <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">+ {{ __('ui.create_fan_post') }}</button>
                    @endif
                </div>
            </div>
        </x-slot:heading>
    </x-fan-post-list>
@endsection
