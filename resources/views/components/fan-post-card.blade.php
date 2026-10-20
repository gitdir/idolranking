@props(['post', 'isCurrent' => false])
@php
    $cardMediaType = $post['media_type'] ?? 'image';
    $defaultCardImage = \App\Support\ManagedImageStorage::defaultUrl();
    $cardImage = $cardMediaType === 'youtube' && !empty($post['youtube_video_id'])
        ? 'https://i.ytimg.com/vi/'.$post['youtube_video_id'].'/hqdefault.jpg'
        : \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null);
    $hasVideoThumbnail = $cardMediaType === 'youtube' && !empty($post['youtube_video_id']);
    $hasImageThumbnail = $cardMediaType === 'image'
        && !empty($post['image_url'])
        && $post['image_url'] !== $defaultCardImage;
    $isTextOnly = ! $hasVideoThumbnail && ! $hasImageThumbnail;
    $detailUrl = isset($post['fan_post_idx'])
        ? route('fan-post.show', ['idx' => $post['fan_post_idx']])
        : null;
    $compactCount = static function (int $count): string {
        $count = max(0, $count);
        if ($count < 1000) {
            return number_format($count);
        }

        foreach ([1000000000000 => 'T', 1000000000 => 'B', 1000000 => 'M', 1000 => 'K'] as $divisor => $suffix) {
            if ($count >= $divisor) {
                $whole = intdiv($count, $divisor);
                $tenths = intdiv(($count % $divisor) * 10, $divisor);

                return $whole.($tenths > 0 ? '.'.$tenths : '').$suffix;
            }
        }

        return number_format($count);
    };
    $views = (int) ($post[3] ?? 0);
    $monthlyLikes = (int) ($post['monthly_like_count'] ?? 0);
    $lifetimeLikes = (int) ($post[4] ?? 0);
    $comments = (int) ($post[5] ?? 0);
    $likeCountsLabel = __('ui.fan_post_like_counts_aria', [
        'monthly' => number_format($monthlyLikes),
        'lifetime' => number_format($lifetimeLikes),
    ]);
    $badgeLanguage = strtoupper((string) ($post['language_code'] ?? ''));
@endphp

<article @class(['fan-post-card', 'fan-post-card--current' => $isCurrent])>
    @if ($detailUrl)
        <a @class(['fan-post-image-link', 'fan-post-image-link--text' => $isTextOnly]) href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>
    @else
        <div @class(['fan-post-image-link', 'fan-post-image-link--text' => $isTextOnly])>
    @endif
    @if ($isTextOnly)
        <span class="fan-post-text-placeholder" aria-hidden="true">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#d5d1de" stroke="none" d="M5 4.75h14a2.25 2.25 0 0 1 2.25 2.25v8A2.25 2.25 0 0 1 19 17.25h-7.2l-4.9 3.5v-3.5H5A2.25 2.25 0 0 1 2.75 15V7A2.25 2.25 0 0 1 5 4.75Z"/></svg>
        </span>
    @else
        <img class="fan-post-image fan-post-image--{{ $post[6] }}" src="{{ $cardImage }}" width="320" height="320" alt="{{ $cardMediaType === 'youtube' ? __('ui.youtube_thumbnail') : (isset($post['image_url']) ? __('ui.fan_post_thumbnail') : __('ui.fan_post_placeholder_image')) }}" loading="lazy">
    @endif
    @if (\App\Support\FanPostLanguages::isValid($post['language_code'] ?? null))
        <span class="fan-post-card-language-badge" title="{{ $badgeLanguage }}" aria-label="{{ $badgeLanguage }}">{{ $badgeLanguage }}</span>
    @endif
    @if($cardMediaType === 'youtube')<span class="fan-post-video-play" aria-hidden="true">▶</span>@endif
    @if ($detailUrl)
        </a>
    @else
        </div>
    @endif
    @if ($detailUrl)<a class="post-statistics" href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>@else<div class="post-statistics">@endif
        <span class="post-stat post-stat--views" aria-label="{{ __('ui.views_aria', ['amount' => number_format($views)]) }}" title="{{ __('ui.views_aria', ['amount' => number_format($views)]) }}">
            <svg aria-hidden="true"><use href="#icon-eye"/></svg><span>{{ $compactCount($views) }}</span>
        </span>
        <span class="post-statistics-right">
            <span class="post-stat post-stat--recommendations" aria-label="{{ $likeCountsLabel }}" title="{{ $likeCountsLabel }}">
                <svg aria-hidden="true"><use href="#icon-recommend"/></svg><span>{{ $compactCount($monthlyLikes) }} ({{ $compactCount($lifetimeLikes) }})</span>
            </span>
            <span class="post-stat post-stat--comments" aria-label="{{ __('ui.comments_aria', ['amount' => number_format($comments)]) }}" title="{{ __('ui.comments_aria', ['amount' => number_format($comments)]) }}">
                <svg aria-hidden="true"><use href="#icon-comment"/></svg><span>{{ $compactCount($comments) }}</span>
            </span>
        </span>
    @if ($detailUrl)</a>@else</div>@endif
    <div class="post-body">
        <div class="post-author">
            <x-member-link :member-code="$post['member_code'] ?? null" :label="isset($post['member_code']) ? __('ui.member_fan_posts_label', ['name' => $post[1]]) : null" class="post-author-avatar-link">
                <img src="{{ \App\Support\ManagedImageStorage::publicUrl($post['author_image_url'] ?? null) }}" width="24" height="24" alt="" loading="lazy">
            </x-member-link>
            <x-member-link :member-code="$post['member_code'] ?? null" class="post-author-name-link">
                <x-country-flag :code="$post['country_code'] ?? null" />
                <span translate="no">{{ $post[1] }}</span>
            </x-member-link>
        </div>
        <p class="post-preview">@if ($detailUrl)<a href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>{{ $post[2] }}</a>@else{{ $post[2] }}@endif</p>
    </div>
</article>
