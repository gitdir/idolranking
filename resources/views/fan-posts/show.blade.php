@extends('layouts.app')

@section('title', $post['title'].' | IdolRanking')

@section('content')
    <div class="fan-post-detail">
        <div class="fan-detail-columns">
            <div class="fan-detail-main">
                <article class="fan-detail-post">
                <div class="fan-detail-author">
                    <x-member-link :member-code="$post['author']['member_code'] ?? null" :label="isset($post['author']['member_code']) ? 'View '.$post['author']['nickname'].' Fan Posts' : null">
                        <img src="{{ \App\Support\ManagedImageStorage::publicUrl($post['author']['image_url'] ?? null) }}" width="42" height="42" alt="">
                    </x-member-link>
                    <div><x-member-link :member-code="$post['author']['member_code'] ?? null"><span>{{ $post['author']['nickname'] }}</span></x-member-link><small class="fan-detail-time">2 hours ago</small></div>
                </div>
                <h1>{{ $post['title'] }}</h1>
                @if (($post['media_type'] ?? (!empty($post['youtube_video_id']) ? 'youtube' : 'image')) === 'youtube')
                    <div class="fan-detail-video"><iframe src="https://www.youtube-nocookie.com/embed/{{ $post['youtube_video_id'] }}" title="{{ $post['title'] }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>
                @elseif (($post['media_type'] ?? 'image') === 'image')
                    <img class="fan-detail-image" src="{{ \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null) }}" width="800" height="800" alt="{{ $post['title'] }} illustration">
                @endif
                <div class="fan-detail-comment">{{ $post['body'] }}</div>
                <ul class="fan-detail-tags" aria-label="Tags">
                    @foreach ($post['tags'] as $tag)<li>#{{ $tag }}</li>@endforeach
                </ul>
                <div class="fan-detail-statistics">
                    <div class="fan-detail-statistics-group">
                        <span><svg aria-hidden="true"><use href="#icon-eye"/></svg>{{ number_format($post['views']) }} <span>Views</span></span>
                        <span><svg aria-hidden="true"><use href="#icon-recommend"/></svg><span data-recommendation-count>{{ number_format($post['recommendations']) }}</span> <span>Recommendations</span></span>
                        <span><svg aria-hidden="true"><use href="#icon-comment"/></svg><span data-fan-post-comment-count>{{ $comments->total() }}</span> <span>Comments</span></span>
                    </div>
                    <div class="fan-detail-actions">
                        <button class="fan-detail-recommend" type="button"
                            data-fan-post-recommend data-endpoint="{{ route('fan-post.recommendation', $post['idx']) }}"
                            data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                            data-csrf="{{ csrf_token() }}" aria-pressed="{{ $isRecommended ? 'true' : 'false' }}"
                            @disabled($isRecommended)>
                            <svg aria-hidden="true"><use href="#icon-recommend"/></svg>
                            <span>Recommend</span>
                        </button>
                        <button class="fan-detail-report" type="button" data-fan-post-report-open
                            data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                            @disabled($isOwnPost) @if ($isOwnPost) title="You cannot report your own Fan Post." @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V4m0 1h11l-2 4 2 4H6"/></svg>
                            <span>Report</span>
                        </button>
                    </div>
                </div>
                <p class="fan-detail-report-success" data-fan-post-report-success role="status" hidden></p>
                </article>
                <section id="comments" class="fan-detail-comments" aria-labelledby="comments-title">
                    <h2 id="comments-title">Comments <span data-fan-post-comment-count>{{ $comments->total() }}</span></h2>
                    @php($commentMember = request()->attributes->get('currentMember'))
                    <form class="fan-detail-compose" action="{{ route('fan-post.comments.store', $post['idx']) }}" method="post"
                        data-fan-post-comment-form data-signed-in="{{ $commentMember ? 'true' : 'false' }}">
                        @csrf
                        <input type="hidden" name="request_key" value="">
                        @if ($commentMember)
                            <img class="fan-detail-compose-avatar" src="{{ \App\Support\ManagedImageStorage::publicUrl($commentMember->profile_image_path) }}" width="38" height="38" alt="">
                        @else
                            <span class="fan-detail-compose-avatar" aria-hidden="true">G</span>
                        @endif
                        <label class="sr-only" for="comment-body">Write a comment</label>
                        <textarea id="comment-body" name="content" rows="1" maxlength="1000" placeholder="Write a kind comment..."></textarea>
                        <button type="submit">Comment</button>
                    </form>
                    <p class="fan-detail-comment-message" data-fan-post-comment-message role="status" hidden></p>
                    <ul class="fan-detail-comment-list" data-fan-post-comment-list>
                        @foreach ($comments as $comment)
                            <li data-comment-idx="{{ $comment['idx'] ?? '' }}">
                                <x-member-link :member-code="$comment['member_code'] ?? null" :label="isset($comment['member_code']) ? 'View '.$comment['nickname'].' Fan Posts' : null">
                                    <img src="{{ \App\Support\ManagedImageStorage::publicUrl($comment['image_url'] ?? null) }}" width="38" height="38" alt="" loading="lazy">
                                </x-member-link>
                                <div class="fan-detail-comment-content">
                                    <h3><x-member-link :member-code="$comment['member_code'] ?? null">{{ $comment['nickname'] }}</x-member-link> <small class="fan-detail-time">{{ $comment['time'] }}</small></h3>
                                    <p>{{ $comment['body'] }}</p>
                                </div>
                                @if ($comment['can_delete'] ?? false)
                                    <button class="fan-detail-comment-delete" type="button" data-comment-delete
                                        data-endpoint="{{ route('fan-post.comments.destroy', ['idx' => $post['idx'], 'commentIdx' => $comment['idx']]) }}"
                                        data-csrf="{{ csrf_token() }}">Delete</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <x-number-pagination :paginator="$comments" :window="0" label="Comment pages" />
                </section>
            </div>
            <aside class="fan-detail-sidebar">
                <x-fan-post-sidebar-list :posts="$morePosts" title="More Posts by This Author" heading-id="more-posts-title" />
                <x-fan-post-sidebar-list :posts="$trendingPosts" title="Trending Fan Posts" heading-id="trending-posts-title" :see-all-url="route('fan-posts.index')" />
            </aside>
        </div>
        <x-fan-post-list :posts="$posts" :current-fan-post-idx="$post['idx']" heading-id="detail-fan-posts-title" empty-message="No fan posts yet.">
            <x-slot:heading>
                <div class="member-post-list-heading public-fan-posts-heading">
                    <div><h2 id="detail-fan-posts-title">Fan Posts</h2></div>
                    <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('fan-post.show', ['idx' => $post['idx']]) }}">
                            <label class="sr-only" for="detail-fan-post-sort">Sort fan posts</label>
                            <select id="detail-fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($fanPostSort === 'latest')>Latest</option>
                                <option value="liked" @selected($fanPostSort === 'liked')>Most Liked</option>
                                <option value="commented" @selected($fanPostSort === 'commented')>Most Commented</option>
                            </select>
                            <noscript><button type="submit">Apply</button></noscript>
                        </form>
                        @if (request()->attributes->get('currentMember'))
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create', ['idol_idx' => $post['idol_idx']]) }}">+ Create Fan Post</a>
                        @else
                            <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">+ Create Fan Post</button>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>
    </div>
    <dialog id="fan-post-report-modal" class="fan-post-report-modal" aria-labelledby="fan-post-report-title">
        <button class="fan-post-report-close" type="button" data-fan-post-report-cancel aria-label="Close report dialog">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
        <h2 id="fan-post-report-title">Report Fan Post</h2>
        <form data-fan-post-report-form action="{{ route('fan-post.report', ['idx' => $post['idx']]) }}" method="post">
            @csrf
            <fieldset>
                <legend>Reason <span>*</span></legend>
                <div class="fan-post-report-reasons">
                    @foreach ([
                        'spam' => 'Spam',
                        'harassment_hate' => 'Harassment or Hate',
                        'inappropriate_content' => 'Inappropriate Content',
                        'copyright' => 'Copyright',
                        'misinformation' => 'Misinformation',
                        'other' => 'Other',
                    ] as $value => $label)
                        <label><input type="radio" name="reason" value="{{ $value }}" required><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <label class="fan-post-report-details" for="fan-post-report-details">Details</label>
            <textarea id="fan-post-report-details" name="details" rows="5" maxlength="2000" placeholder="Add any helpful context..."></textarea>
            <p class="fan-post-report-error" data-fan-post-report-error role="alert" hidden></p>
            <div class="fan-post-report-actions">
                <button type="button" data-fan-post-report-cancel>Cancel</button>
                <button type="submit">Submit Report</button>
            </div>
        </form>
    </dialog>
@endsection
