@extends('layouts.app')
@section('title', __('ui.member_page_title', ['name' => $memberSummary['nickname']]).' | IdolRanking')
@section('content')
    <div class="member-fan-posts-page my-page-content">
        <x-member-fan-post-summary :member="$memberSummary" />

        <x-fan-post-list :posts="$fanPosts" heading-id="member-fan-posts-title" layout="member" :empty-message="__('ui.no_fan_posts')">
            <x-slot:heading>
                <div class="member-post-list-heading">
                    <div>
                        <h2 id="member-fan-posts-title">{{ __('ui.member_fan_posts') }}</h2>
                        <p translate="no">{{ __('ui.posts_written_by', ['name' => $memberSummary['nickname']]) }}</p>
                    </div>
                    <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('member.fan-posts', ['member_code' => $member_code]) }}">
                            <label class="sr-only" for="fan-post-sort">{{ __('ui.sort_fan_posts') }}</label>
                            <select id="fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($sort === 'latest')>{{ __('ui.latest') }}</option>
                                <option value="monthly_liked" @selected($sort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option>
                                <option value="liked" @selected($sort === 'liked')>{{ __('ui.most_liked') }}</option>
                                <option value="commented" @selected($sort === 'commented')>{{ __('ui.most_commented') }}</option>
                            </select>
                            <noscript><button type="submit">{{ __('ui.apply') }}</button></noscript>
                        </form>
                        @if ($canCreateFanPost ?? false)
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create') }}">{{ __('ui.more_idol_fan_posts') }}</a>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>

        <x-number-pagination :paginator="$fanPosts" :sample="$showSamplePagination ?? false" />
    </div>
@endsection
