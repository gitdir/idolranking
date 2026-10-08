@props(['post', 'isCurrent' => false])
@php
    $cardMediaType = $post['media_type'] ?? 'image';
    $cardImage = $cardMediaType === 'youtube' && !empty($post['youtube_video_id'])
        ? 'https://i.ytimg.com/vi/'.$post['youtube_video_id'].'/hqdefault.jpg'
        : \App\Support\ManagedImageStorage::publicUrl($post['image_url'] ?? null);
    $detailUrl = isset($post['fan_post_idx'])
        ? route('fan-post.show', ['idx' => $post['fan_post_idx']])
        : null;
@endphp

<article @class(['fan-post-card', 'fan-post-card--current' => $isCurrent])>
    @if ($detailUrl)
        <a class="fan-post-image-link" href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>
    @endif
    <img class="fan-post-image fan-post-image--{{ $post[6] }}" src="{{ $cardImage }}" width="320" height="320" alt="{{ $cardMediaType === 'youtube' ? 'YouTube video thumbnail' : (isset($post['image_url']) ? 'Fan post image' : 'Fan post illustration placeholder') }}" loading="lazy">
    @if($cardMediaType === 'youtube')<span class="fan-post-video-play" aria-hidden="true">▶</span>@endif
    @if ($detailUrl)
        </a>
    @endif
    @if ($detailUrl)<a class="post-statistics" href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>@else<div class="post-statistics">@endif
        <span class="post-stat post-stat--views" aria-label="{{ number_format($post[3]) }} views">
            <svg aria-hidden="true"><use href="#icon-eye"/></svg><span>{{ number_format($post[3]) }}</span>
        </span>
        <span class="post-stat post-stat--recommendations" aria-label="{{ number_format($post[4]) }} recommendations">
            <svg aria-hidden="true"><use href="#icon-recommend"/></svg><span>{{ number_format($post[4]) }}</span>
        </span>
        <span class="post-stat post-stat--comments" aria-label="{{ number_format($post[5]) }} comments">
            <svg aria-hidden="true"><use href="#icon-comment"/></svg><span>{{ number_format($post[5]) }}</span>
        </span>
    @if ($detailUrl)</a>@else</div>@endif
    <div class="post-body">
        <div class="post-author">
            <x-member-link :member-code="$post['member_code'] ?? null" :label="isset($post['member_code']) ? 'View '.$post[1].' Fan Posts' : null" class="post-author-avatar-link">
                <img src="{{ \App\Support\ManagedImageStorage::publicUrl($post['author_image_url'] ?? null) }}" width="24" height="24" alt="" loading="lazy">
            </x-member-link>
            <x-member-link :member-code="$post['member_code'] ?? null" class="post-author-name-link">
                <span>{{ $post[1] }}</span>
            </x-member-link>
        </div>
        <p class="post-preview">@if ($detailUrl)<a href="{{ $detailUrl }}" @if ($isCurrent) aria-current="page" @endif>{{ $post[2] }}</a>@else{{ $post[2] }}@endif</p>
    </div>
</article>
