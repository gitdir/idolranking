@props(['member'])

<div {{ $attributes->class(['member-post-stats']) }} aria-label="Member Fan Post statistics">
    <div class="member-post-stat">
        <svg aria-hidden="true"><use href="#icon-recommend"/></svg>
        <strong>{{ $member['like_ranking'] }}</strong>
        <span>Like Ranking</span>
    </div>
    <div class="member-post-stat">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6z"/><path d="M15 3v5h4M9 12h7M9 16h7"/></svg>
        <strong>{{ number_format($member['fan_post_count']) }}</strong>
        <span>Fan Posts</span>
    </div>
    <div class="member-post-stat">
        <svg aria-hidden="true"><use href="#icon-comment"/></svg>
        <strong>{{ number_format($member['comment_count']) }}</strong>
        <span>Comments</span>
    </div>
</div>
