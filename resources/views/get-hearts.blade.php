@extends('layouts.app')

@section('title', 'Get Hearts | IdolRanking')

@section('content')
<div class="get-hearts-page">
    <section class="get-hearts-hero" aria-labelledby="get-hearts-title">
        <div>
            <h1 id="get-hearts-title">Get <span>Hearts</span></h1>
            <strong>Hearts are earned, not bought.</strong>
            <p>Join various fan activities and collect Hearts<br>to support your favorite idols!</p>
        </div>
        <svg viewBox="0 0 420 190" aria-hidden="true">
            <defs><linearGradient id="heart-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#ff8fc2"/><stop offset="1" stop-color="#ff2488"/></linearGradient></defs>
            <path class="get-hearts-hero-heart" d="M210 155S115 101 115 48c0-49 63-60 95-17 32-43 95-32 95 17 0 53-95 107-95 107Z"/>
            <path class="get-hearts-hero-heart get-hearts-hero-heart--small" d="M63 105S20 80 20 56c0-22 29-27 43-8 15-19 43-14 43 8 0 24-43 49-43 49Z"/>
            <path class="get-hearts-hero-heart get-hearts-hero-heart--tiny" d="M350 75s-28-16-28-32c0-14 19-18 28-5 10-13 28-9 28 5 0 16-28 32-28 32Z"/>
        </svg>
    </section>

    @if($member)
        <section class="get-hearts-summary" aria-label="My Heart summary">
            <div class="get-hearts-summary-item">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                <span>My Hearts<strong data-get-hearts-balance>{{ number_format((int) $member->heart_balance) }}</strong></span>
            </div>
            <div class="get-hearts-summary-item get-hearts-summary-item--today">
                <span class="get-hearts-calendar" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#icon-heart"/></svg></span>
                <span>Today Earned<strong data-get-hearts-today-earned>+ {{ number_format($todayEarned) }}</strong><small>{{ now()->format('Y. m. d (D)') }}</small></span>
            </div>
            <p>Complete various fan activities and collect Hearts!</p>
        </section>
    @else
        <section class="get-hearts-guest-summary">
            <div><svg aria-hidden="true"><use href="#icon-heart"/></svg><p><strong>Sign in to see your Hearts</strong><span>Track today’s rewards and activity progress.</span></p></div>
            <button type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">Sign In</button>
        </section>
    @endif

    <section class="get-hearts-activities" aria-labelledby="ways-to-get-hearts">
        <header><h2 id="ways-to-get-hearts">Ways to Get Hearts</h2><p>Complete these activities and earn Hearts. Daily limits may vary by activity.</p></header>
        <div class="get-hearts-list">
            @foreach($activities as $activity)
                @php($complete = $activity->daily_limit > 0 && $activity->progress !== null && $activity->progress >= $activity->daily_limit)
                <article class="get-hearts-activity{{ !$activity->is_enabled ? ' is-disabled' : '' }}" @if($activity->code === 'daily_attendance') data-daily-check-in-card @endif>
                    <div class="get-hearts-activity-main">
                        <span class="get-hearts-activity-icon get-hearts-activity-icon--{{ $activity->icon }}" aria-hidden="true">
                            @switch($activity->icon)
                                @case('calendar') 📅 @break
                                @case('play') ▶ @break
                                @case('post') 📝 @break
                                @case('comment') 💬 @break
                                @case('recommend') 👍 @break
                                @case('invite') 👥 @break
                                @default ♛
                            @endswitch
                        </span>
                        <span><strong>{{ $activity->name }}</strong><small>{{ $activity->description }}</small></span>
                    </div>
                    <div class="get-hearts-reward">
                        <strong><svg aria-hidden="true"><use href="#icon-heart"/></svg>+ {{ number_format($activity->heart_amount) }}</strong>
                        <small>@if(!$activity->is_enabled)Unavailable @elseif($activity->daily_limit > 0)Up to {{ number_format($activity->daily_limit) }} {{ Str::plural('time', $activity->daily_limit) }} per day @else No daily limit @endif</small>
                    </div>
                    @if($activity->daily_limit > 0)
                        <div class="get-hearts-progress">
                        @if($member === null)
                            <span class="get-hearts-progress-guest">Sign in to track progress</span>
                        @else
                            <span class="get-hearts-progress-track"><i style="width: {{ $activity->progress_percent }}%" @if($activity->code === 'daily_attendance') data-daily-check-in-progress-bar @endif></i></span>
                            <strong @if($activity->code === 'daily_attendance') data-daily-check-in-progress @endif>{{ number_format($activity->progress) }} / {{ number_format($activity->daily_limit) }}</strong>
                        @endif
                        </div>
                    @elseif($activity->code === 'invite_friend')
                        <p class="get-hearts-activity-note">@if($activity->is_enabled)When your friend completes their registration, you will receive {{ number_format($activity->heart_amount) }} Hearts.@else This activity is currently unavailable.@endif</p>
                    @elseif($activity->code === 'idol_registration_approved')
                        <p class="get-hearts-activity-note">@if($activity->is_enabled)When your idol or group registration is approved, you will receive {{ number_format($activity->heart_amount) }} Hearts.@else This activity is currently unavailable.@endif</p>
                    @else
                        <div class="get-hearts-progress">
                            <span class="get-hearts-unlimited">@if($activity->is_enabled)∞ <small>No daily limit</small>@else<small>Unavailable</small>@endif</span>
                        </div>
                    @endif
                    @if($activity->code === 'daily_attendance')
                        @if(!$member)
                            <button class="get-hearts-action" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">Sign In</button>
                        @elseif($activity->is_enabled && !$complete)
                            <button class="get-hearts-action" type="button" data-daily-check-in data-endpoint="{{ route('get-hearts.daily-check-in') }}" data-csrf="{{ csrf_token() }}">Check In</button>
                        @endif
                    @elseif($activity->code === 'watch_ads')
                        @if(!$activity->is_enabled)
                            <span class="get-hearts-status get-hearts-status--muted">Unavailable</span>
                        @elseif(!$member)
                            <button class="get-hearts-action" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">Sign In</button>
                        @elseif($complete)
                            <span class="get-hearts-status get-hearts-status--muted">Daily limit reached</span>
                        @else
                            <button class="get-hearts-action" type="button" data-watch-ad-open aria-haspopup="dialog" aria-controls="watch-ad-dialog">Watch Ad</button>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="get-hearts-sponsors" aria-labelledby="sponsor-banners-title">
        <header><h2 id="sponsor-banners-title">Partner Banners</h2><p>Discover our partner pages.</p></header>
        <div class="get-hearts-sponsor-grid{{ $partnerBanners->isNotEmpty() ? ' has-banners' : '' }}">
            @forelse($partnerBanners as $banner)
                <a class="get-hearts-sponsor" href="{{ $banner->click_url }}" target="_blank" rel="noopener noreferrer"
                   @if($banner->claim_url) data-partner-banner-reward data-claim-url="{{ $banner->claim_url }}" data-banner-title="{{ $banner->title }}" data-heart-amount="{{ (int) $banner->heart_reward }}" @endif>
                    @if($banner->show_reward_badge)
                        <span class="get-hearts-sponsor-badge{{ $banner->completed_today ? ' is-complete' : '' }}" data-partner-banner-badge>
                            @if($banner->completed_today)
                                ✓ 완료
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>
                                <span>+{{ number_format((int) $banner->heart_reward) }}</span>
                            @endif
                        </span>
                    @endif
                    <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" loading="lazy">
                </a>
            @empty
                <p class="get-hearts-sponsor-empty">No partner banners are available yet.</p>
            @endforelse
        </div>
        <p class="get-hearts-sponsor-note"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg> Heart rewards are available to eligible signed-in members only. Each banner can earn Hearts once per day.</p>
    </section>

    <aside class="get-hearts-notes" aria-label="Heart reward notes"><strong>ⓘ Notes</strong><ul><li>Hearts are given automatically when you complete each activity.</li><li>Daily limits may vary by activity.</li><li>Abuse or fraudulent activities may result in reward cancellation.</li></ul></aside>

    @if($member)
        <dialog id="watch-ad-dialog" class="watch-ad-dialog" aria-labelledby="watch-ad-title">
            <button class="watch-ad-close" type="button" data-watch-ad-close aria-label="Close">×</button>
            <span class="watch-ad-icon" aria-hidden="true">▶</span>
            <h2 id="watch-ad-title">Watch Ads</h2>
            <p>Watch a short ad and get Hearts!</p>
            <div><span>Reward</span><strong>Configured in Heart Reward Settings</strong><span>Status</span><strong>Integration coming soon</strong></div>
            <p class="watch-ad-notice">No Hearts are granted from this preview. Rewarded-ad integration will be added separately.</p>
            <button type="button" disabled>Watch Ad</button>
        </dialog>

        @if($partnerBanners->contains(fn ($banner) => $banner->claim_url !== null || $banner->completed_today))
        <dialog id="partner-banner-reward-dialog" class="partner-banner-reward-dialog" aria-labelledby="partner-banner-reward-title">
            <button class="watch-ad-close" type="button" data-partner-banner-close aria-label="Close">×</button>
            <h2 id="partner-banner-reward-title">Partner Banner</h2>
            <p><strong data-partner-banner-amount></strong> Hearts are available for this banner.</p>
            <p class="partner-banner-reward-message">The partner page opened in a new tab. Confirm below to request the reward.</p>
            <button type="button" data-partner-banner-claim data-csrf="{{ csrf_token() }}">하트 받기</button>
        </dialog>
        @endif
    @endif
</div>
@endsection
