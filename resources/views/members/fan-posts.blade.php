@extends('layouts.app')
@section('title', $memberSummary['nickname'].' Fan Posts | IdolRanking')
@section('content')
    <div class="member-fan-posts-page my-page-content">
        <x-member-fan-post-summary :member="$memberSummary" />

        <x-fan-post-list :posts="$fanPosts" heading-id="member-fan-posts-title" layout="member" empty-message="No fan posts yet.">
            <x-slot:heading>
                <div class="member-post-list-heading">
                    <div>
                        <h2 id="member-fan-posts-title">My Fan Posts</h2>
                        <p>Posts written by {{ $memberSummary['nickname'] }}</p>
                    </div>
                    <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('member.fan-posts', ['member_code' => $member_code]) }}">
                            <label class="sr-only" for="fan-post-sort">Sort fan posts</label>
                            <select id="fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($sort === 'latest')>Latest</option>
                                <option value="liked" @selected($sort === 'liked')>Most Liked</option>
                                <option value="commented" @selected($sort === 'commented')>Most Commented</option>
                            </select>
                            <noscript><button type="submit">Apply</button></noscript>
                        </form>
                        @if ($canCreateFanPost ?? false)
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create') }}">+ Create Fan Post</a>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>

        <x-number-pagination :paginator="$fanPosts" :sample="$showSamplePagination ?? false" />
    </div>
@endsection
