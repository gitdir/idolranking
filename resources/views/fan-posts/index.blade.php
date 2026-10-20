@extends('layouts.app')

@section('title', __('ui.fan_posts').' | IdolRanking')

@section('content')
<div class="public-fan-posts-page">
    <x-fan-post-list :posts="$fanPosts" heading-id="public-fan-posts-title" :empty-message="__('ui.no_fan_posts')">
        <x-slot:heading>
            <div class="member-post-list-heading public-fan-posts-heading">
                <div><h2 id="public-fan-posts-title" translate="no">Fan Posts</h2></div>
                <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                    <form method="get" action="{{ route('fan-posts.index') }}">
                        <label class="sr-only" for="public-fan-post-sort">{{ __('ui.sort_fan_posts') }}</label>
                        <select id="public-fan-post-sort" name="sort" onchange="this.form.submit()">
                            <option value="latest" @selected($sort === 'latest')>{{ __('ui.latest') }}</option>
                            <option value="monthly_liked" @selected($sort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option>
                            <option value="liked" @selected($sort === 'liked')>{{ __('ui.most_liked') }}</option>
                            <option value="commented" @selected($sort === 'commented')>{{ __('ui.most_commented') }}</option>
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

    <x-number-pagination :paginator="$fanPosts" />
</div>
@endsection
