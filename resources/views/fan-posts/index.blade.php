@extends('layouts.app')

@section('title', 'Fan Posts | IdolRanking')

@section('content')
<div class="public-fan-posts-page">
    <x-fan-post-list :posts="$fanPosts" heading-id="public-fan-posts-title" empty-message="No fan posts yet.">
        <x-slot:heading>
            <div class="member-post-list-heading public-fan-posts-heading">
                <div><h2 id="public-fan-posts-title">Fan Posts</h2></div>
                <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                    <form method="get" action="{{ route('fan-posts.index') }}">
                        <label class="sr-only" for="public-fan-post-sort">Sort fan posts</label>
                        <select id="public-fan-post-sort" name="sort" onchange="this.form.submit()">
                            <option value="latest" @selected($sort === 'latest')>Latest</option>
                            <option value="liked" @selected($sort === 'liked')>Most Liked</option>
                            <option value="commented" @selected($sort === 'commented')>Most Commented</option>
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

    <x-number-pagination :paginator="$fanPosts" />
</div>
@endsection
