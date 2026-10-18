@extends('layouts.app')

@section('title', $post['title'].' | IdolRanking')

@section('content')
    <div class="fan-post-detail">
        <div class="fan-detail-columns">
            <div class="fan-detail-main">
                <article class="fan-detail-post">
                <div class="fan-detail-author">
                    <x-member-link :member-code="$post['author']['member_code'] ?? null" :label="isset($post['author']['member_code']) ? __('ui.member_view_posts_label', ['name' => $post['author']['nickname']]) : null">
                        <img src="{{ \App\Support\ManagedImageStorage::publicUrl($post['author']['image_url'] ?? null) }}" width="42" height="42" alt="">
                    </x-member-link>
                    <div><x-member-link :member-code="$post['author']['member_code'] ?? null" class="fan-detail-author-name"><x-country-flag :code="$post['author']['country_code'] ?? null" />{{ $post['author']['nickname'] }}</x-member-link>
                        @if (\App\Support\FanPostLanguages::isValid($post['author']['language_code'] ?? null))
                            @php
                                $authorLanguage = strtoupper($post['author']['language_code']);
                            @endphp
                            <span class="fan-post-author-badge" title="{{ $authorLanguage }}" aria-label="{{ $authorLanguage }}">{{ $authorLanguage }}</span>
                        @endif
                        <small class="fan-detail-time">{{ $post['time'] }}</small></div>
                </div>
                @if ($isOwnPost)
                    <div class="fan-detail-owner-actions">
                        <a class="fan-detail-report" href="{{ route('fan-post.edit', ['idx' => $post['idx']]) }}">{{ __('ui.edit') }}</a>
                        <form method="post" action="{{ route('fan-post.destroy', ['idx' => $post['idx']]) }}"
                            data-confirm="{{ __('ui.confirm_delete_fan_post') }}" onsubmit="return confirm(this.dataset.confirm)">
                            @csrf
                            @method('DELETE')
                            <button class="fan-detail-report fan-detail-owner-delete" type="submit">{{ __('ui.delete_fan_post') }}</button>
                        </form>
                    </div>
                @endif
                @php($showTranslationControl = \App\Support\FanPostLanguages::isValid($post['language_code'] ?? null)
                    && strtolower($post['language_code']) !== app()->getLocale())
                @if ($showTranslationControl)
                    <div class="fan-post-title-translation" data-fan-post-translation
                        data-endpoint="{{ route('fan-post.translation', ['idx' => $post['idx']]) }}"
                        data-target-language="{{ app()->getLocale() }}">
                        <h1><span id="fan-post-translation-title" data-fan-post-title>{{ $post['title'] }}</span><button type="button" data-translation-toggle aria-expanded="false"
                                aria-controls="fan-post-translation-title fan-post-translation-content">{{ __('ui.translate') }}</button></h1>
                        <p class="fan-post-title-translation-status" data-translation-status role="status" aria-live="polite" hidden></p>
                    </div>
                @else
                    <h1 id="fan-post-translation-title" data-fan-post-title>{{ $post['title'] }}</h1>
                @endif
                @if (($post['media_type'] ?? (!empty($post['youtube_video_id']) ? 'youtube' : 'image')) === 'youtube')
                    <div class="fan-detail-video"><iframe src="https://www.youtube-nocookie.com/embed/{{ $post['youtube_video_id'] }}" title="{{ $post['title'] }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>
                @elseif (($post['media_type'] ?? 'image') === 'image')
                    @if (filled($post['original_image_url'] ?? null))
                        <a class="fan-detail-image-link" href="{{ $post['original_image_url'] }}" target="_blank" rel="noopener noreferrer">
                            <img class="fan-detail-image" src="{{ $post['image_url'] }}" width="800" height="800" alt="{{ $post['title'] }} illustration">
                        </a>
                    @else
                        <img class="fan-detail-image" src="{{ \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null) }}" width="800" height="800" alt="{{ $post['title'] }} illustration">
                    @endif
                @endif
                <div id="fan-post-translation-content" class="fan-detail-comment" data-fan-post-content>{{ $post['body'] }}</div>
                <ul class="fan-detail-tags" aria-label="{{ __('ui.tags_label') }}">
                    @foreach ($post['tags'] as $tag)<li>#{{ $tag }}</li>@endforeach
                </ul>
                <div class="fan-detail-statistics">
                    <div class="fan-detail-statistics-group">
                        <span><svg aria-hidden="true"><use href="#icon-eye"/></svg>{{ number_format($post['views']) }} <span>{{ __('ui.views_label') }}</span></span>
                        <span><svg aria-hidden="true"><use href="#icon-recommend"/></svg><span><span data-monthly-recommendation-count>{{ number_format($post['monthly_recommendations']) }}</span> (<span data-recommendation-count>{{ number_format($post['recommendations']) }}</span>)</span> <span>{{ __('ui.recommendations_label') }}</span></span>
                        <span><svg aria-hidden="true"><use href="#icon-comment"/></svg><span data-fan-post-comment-count>{{ $comments->total() }}</span> <span>{{ __('ui.comments') }}</span></span>
                    </div>
                    <div class="fan-detail-actions">
                        <button class="fan-detail-recommend" type="button"
                            data-fan-post-recommend data-endpoint="{{ route('fan-post.recommendation', $post['idx']) }}"
                            data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                            data-request-key="{{ $recommendationRequestKey }}"
                            data-csrf="{{ csrf_token() }}" aria-pressed="{{ $isRecommended ? 'true' : 'false' }}"
                            @disabled($isRecommended)>
                            <svg aria-hidden="true"><use href="#icon-recommend"/></svg>
                            <span>{{ __('ui.recommend') }}</span>
                        </button>
                        <button class="fan-detail-report" type="button" data-fan-post-report-open
                            data-endpoint="{{ route('fan-post.report', ['idx' => $post['idx']]) }}" data-report-title="{{ __('ui.report_fan_post') }}"
                            data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                            @disabled($isOwnPost) @if ($isOwnPost) title="{{ __('ui.report_own_post_blocked') }}" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 21V4m0 1h11l-2 4 2 4H6"/></svg>
                            <span>{{ __('ui.report') }}</span>
                        </button>
                    </div>
                </div>
                <p class="fan-detail-report-success" data-fan-post-report-success role="status" hidden></p>
                </article>
                <section id="comments" class="fan-detail-comments" aria-labelledby="comments-title">
                    <h2 id="comments-title">{{ __('ui.comments') }} <span data-fan-post-comment-count>{{ $comments->total() }}</span></h2>
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
                        <div class="fan-detail-compose-fields">
                            <label class="sr-only" for="comment-body">{{ __('ui.write_comment') }}</label>
                            <textarea id="comment-body" name="content" rows="1" maxlength="1000" placeholder="{{ __('ui.comment_placeholder') }}"></textarea>
                            <div class="fan-detail-compose-actions">
                                <label class="sr-only" for="comment-language">{{ __('ui.comment_language') }}</label>
                                <select id="comment-language" class="fan-detail-compose-language" name="language_code"
                                    aria-label="{{ __('ui.comment_language') }}">
                                    @foreach (\App\Support\FanPostLanguages::all() as $code => $name)
                                        <option value="{{ $code }}" @selected(old('language_code', ($randomDummyMode ?? false) ? 'ko' : app()->getLocale()) === $code)>{{ $name }} ({{ strtoupper($code) }})</option>
                                    @endforeach
                                </select>
                                <button type="submit">{{ __('ui.post_comment') }}</button>
                            </div>
                        </div>
                    </form>
                    <p class="fan-detail-comment-message" data-fan-post-comment-message role="status" hidden></p>
                    <ul @class(['fan-detail-comment-list', 'fan-detail-comment-list--has-pagination' => $comments->hasPages()]) data-fan-post-comment-list>
                        @foreach ($comments as $comment)
                            <li data-comment-idx="{{ $comment['idx'] ?? '' }}">
                                <x-member-link :member-code="$comment['member_code'] ?? null" :label="isset($comment['member_code']) ? __('ui.member_view_posts_label', ['name' => $comment['nickname']]) : null">
                                    <img src="{{ \App\Support\ManagedImageStorage::publicUrl($comment['image_url'] ?? null) }}" width="38" height="38" alt="" loading="lazy">
                                </x-member-link>
                                <div class="fan-detail-comment-content">
                                    <h3><x-member-link :member-code="$comment['member_code'] ?? null" class="fan-detail-author-name"><x-country-flag :code="$comment['country_code'] ?? null" />{{ $comment['nickname'] }}</x-member-link> <small class="fan-detail-time">{{ $comment['time'] }}</small></h3>
                                    @if (\App\Support\FanPostLanguages::isValid($comment['language_code'] ?? null) && strtolower($comment['language_code']) !== app()->getLocale())
                                        <div class="fan-post-comment-translation" data-comment-translation
                                            data-endpoint="{{ route('fan-post.comments.translation', ['idx' => $post['idx'], 'commentIdx' => $comment['idx']]) }}"
                                            data-target-language="{{ app()->getLocale() }}">
                                            <p class="fan-post-comment-body"><span id="fan-post-comment-{{ $comment['idx'] }}-content">{{ $comment['body'] }}</span> <button type="button" data-comment-translation-toggle aria-expanded="false"
                                                aria-controls="fan-post-comment-{{ $comment['idx'] }}-content">{{ __('ui.translate') }}</button></p>
                                            <p data-comment-translation-status role="status" aria-live="polite" hidden></p>
                                        </div>
                                    @else
                                        <p>{{ $comment['body'] }}</p>
                                    @endif
                                    @if ($comment['rewarded_hearts'] !== null)
                                        <span class="fan-detail-comment-reward">+{{ number_format($comment['rewarded_hearts']) }} Heart</span>
                                    @endif
                                </div>
                                @if ($comment['can_delete'] ?? false)
                                    <button class="fan-detail-comment-delete" type="button" data-comment-delete
                                        data-endpoint="{{ route('fan-post.comments.destroy', ['idx' => $post['idx'], 'commentIdx' => $comment['idx']]) }}"
                                        data-csrf="{{ csrf_token() }}">{{ __('ui.delete_comment') }}</button>
                                @elseif ($comment['can_report'] ?? false)
                                    <button class="fan-detail-comment-report" type="button" data-comment-report-open
                                        data-endpoint="{{ $comment['report_url'] }}" data-report-title="{{ __('ui.report_comment') }}"
                                        data-signed-in="{{ request()->attributes->get('currentMember') ? 'true' : 'false' }}"
                                        aria-haspopup="dialog" aria-controls="fan-post-report-modal">{{ __('ui.report') }}</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <x-number-pagination :paginator="$comments" :window="0" :label="__('ui.comment_pages')" />
                </section>
            </div>
            <aside class="fan-detail-sidebar">
                <x-fan-post-sidebar-list :posts="$morePosts" :title="__('ui.more_posts_author')" heading-id="more-posts-title" />
                <x-fan-post-sidebar-list :posts="$trendingPosts" :title="__('ui.trending_fan_posts')" heading-id="trending-posts-title" :see-all-url="route('fan-posts.index')" />
            </aside>
        </div>
        <x-fan-post-list :posts="$posts" :current-fan-post-idx="$post['idx']" heading-id="detail-fan-posts-title" :empty-message="__('ui.no_fan_posts')">
            <x-slot:heading>
                <div class="member-post-list-heading public-fan-posts-heading">
                    <div><h2 id="detail-fan-posts-title">{{ __('ui.fan_post_detail_posts') }}</h2></div>
                    <div class="idol-detail-fan-post-actions member-fan-post-actions fan-post-toolbar">
                        <form method="get" action="{{ route('fan-post.show', ['idx' => $post['idx']]) }}">
                            <label class="sr-only" for="detail-fan-post-sort">{{ __('ui.sort_fan_posts') }}</label>
                            <select id="detail-fan-post-sort" name="sort" onchange="this.form.submit()">
                                <option value="latest" @selected($fanPostSort === 'latest')>{{ __('ui.latest') }}</option>
                                <option value="monthly_liked" @selected($fanPostSort === 'monthly_liked')>{{ __('ui.monthly_liked') }}</option>
                                <option value="liked" @selected($fanPostSort === 'liked')>{{ __('ui.most_liked') }}</option>
                                <option value="commented" @selected($fanPostSort === 'commented')>{{ __('ui.most_commented') }}</option>
                            </select>
                            <noscript><button type="submit">{{ __('ui.apply') }}</button></noscript>
                        </form>
                        @if (request()->attributes->get('currentMember'))
                            <a class="idol-detail-create-post" href="{{ route('fan-post.create', ['idol_idx' => $post['idol_idx']]) }}">{{ __('ui.more_idol_fan_posts') }}</a>
                        @else
                            <button class="idol-detail-create-post" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">{{ __('ui.more_idol_fan_posts') }}</button>
                        @endif
                    </div>
                </div>
            </x-slot:heading>
        </x-fan-post-list>
    </div>
    <dialog id="fan-post-report-modal" class="fan-post-report-modal" aria-labelledby="fan-post-report-title">
        <button class="fan-post-report-close" type="button" data-fan-post-report-cancel aria-label="{{ __('ui.report_dialog_close') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
        </button>
        <h2 id="fan-post-report-title">{{ __('ui.report_fan_post') }}</h2>
        <form data-fan-post-report-form action="{{ route('fan-post.report', ['idx' => $post['idx']]) }}" method="post">
            @csrf
            <fieldset>
                <legend>{{ __('ui.reason') }} <span>*</span></legend>
                <div class="fan-post-report-reasons">
                    @foreach ([
                        'spam' => __('ui.report_spam'),
                        'harassment_hate' => __('ui.report_harassment'),
                        'inappropriate_content' => __('ui.report_inappropriate'),
                        'copyright' => __('ui.report_copyright'),
                        'misinformation' => __('ui.report_misinformation'),
                        'other' => __('ui.report_other'),
                    ] as $value => $label)
                        <label><input type="radio" name="reason" value="{{ $value }}" required><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            <label class="fan-post-report-details" for="fan-post-report-details">{{ __('ui.details') }}</label>
            <textarea id="fan-post-report-details" name="details" rows="5" maxlength="2000" placeholder="{{ __('ui.report_details_placeholder') }}"></textarea>
            <p class="fan-post-report-error" data-fan-post-report-error role="alert" hidden></p>
            <div class="fan-post-report-actions">
                <button type="button" data-fan-post-report-cancel>{{ __('ui.cancel') }}</button>
                <button type="submit">{{ __('ui.submit_report') }}</button>
            </div>
        </form>
    </dialog>
@endsection
