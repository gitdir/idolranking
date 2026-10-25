@extends('layouts.app')

@section('title', __('ui.get_hearts_title').' | IdolRanking')

@section('content')
<div class="get-hearts-page">
    <section class="get-hearts-hero" aria-labelledby="get-hearts-title">
        <div>
            <h1 id="get-hearts-title">{{ __('ui.get_hearts_title') }}</h1>
            <p>{{ __('ui.get_hearts_intro') }}</p>
        </div>
        <svg viewBox="0 0 420 190" aria-hidden="true">
            <defs><linearGradient id="heart-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#ff8fc2"/><stop offset="1" stop-color="#ff2488"/></linearGradient></defs>
            <path class="get-hearts-hero-heart" d="M210 155S115 101 115 48c0-49 63-60 95-17 32-43 95-32 95 17 0 53-95 107-95 107Z"/>
            <path class="get-hearts-hero-heart get-hearts-hero-heart--small" d="M63 105S20 80 20 56c0-22 29-27 43-8 15-19 43-14 43 8 0 24-43 49-43 49Z"/>
            <path class="get-hearts-hero-heart get-hearts-hero-heart--tiny" d="M350 75s-28-16-28-32c0-14 19-18 28-5 10-13 28-9 28 5 0 16-28 32-28 32Z"/>
        </svg>
    </section>

    @if($member)
        <section class="get-hearts-summary" aria-label="{{ __('ui.my_heart_summary') }}">
            <div class="get-hearts-summary-item">
                <svg aria-hidden="true"><use href="#icon-heart"/></svg>
                <span>{{ __('ui.my_hearts_page') }}<strong data-get-hearts-balance>{{ number_format((int) $member->heart_balance) }}</strong></span>
            </div>
            <div class="get-hearts-summary-item get-hearts-summary-item--today">
                <span class="get-hearts-calendar" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#icon-heart"/></svg></span>
                <span>{{ __('ui.today_earned_label') }}<strong data-get-hearts-today-earned>+{{ number_format($todayEarned) }}</strong></span>
            </div>
            <p>{{ __('ui.collect_hearts') }}</p>
        </section>
    @else
        <section class="get-hearts-guest-summary">
            <div><svg aria-hidden="true"><use href="#icon-heart"/></svg><p><strong>{{ __('ui.sign_in_to_hearts') }}</strong><span>{{ __('ui.track_rewards') }}</span></p></div>
            <button type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">{{ __('ui.sign_in') }}</button>
        </section>
    @endif

    <section class="get-hearts-activities" aria-labelledby="ways-to-get-hearts">
        <header><h2 id="ways-to-get-hearts">{{ __('ui.ways_to_earn') }}</h2><p>{{ __('ui.complete_activities') }}</p></header>
        <div class="get-hearts-list">
            @foreach($activities as $activity)
                @php($complete = $activity->daily_limit > 0 && $activity->progress !== null && $activity->progress >= $activity->daily_limit)
                <article class="get-hearts-activity{{ !$activity->is_enabled ? ' is-disabled' : '' }}{{ $activity->code === 'quiz_weekly_rank_rewards' ? ' get-hearts-activity--weekly-quiz-reward' : '' }}" @if($activity->code === 'daily_attendance') data-daily-check-in-card @endif>
                    <div class="get-hearts-activity-main">
                        <span class="get-hearts-activity-icon get-hearts-activity-icon--{{ $activity->icon }}" aria-hidden="true">
                            @switch($activity->icon)
                                @case('calendar') <svg viewBox="0 0 24 24"><use href="#icon-calendar-check"/></svg>@break
                                @case('play') <svg viewBox="0 0 24 24"><use href="#icon-play-circle"/></svg>@break
                                @case('post') <svg viewBox="0 0 24 24"><use href="#icon-image-plus"/></svg>@break
                                @case('comment') <svg viewBox="0 0 24 24"><use href="#icon-message-circle"/></svg>@break
                                @case('recommend') <svg viewBox="0 0 24 24"><use href="#icon-thumbs-up"/></svg>@break
                                @case('invite') <svg viewBox="0 0 24 24"><use href="#icon-user-plus"/></svg>@break
                                @case('crown') <svg viewBox="0 0 24 24"><use href="#icon-users"/></svg>@break
                                @default
                            @endswitch
                        </span>
                        <span><strong>{{ $activity->name }}</strong><small>{{ $activity->description }}</small></span>
                    </div>
                    <div class="get-hearts-reward">
                        <strong><svg class="heart-icon--large" aria-hidden="true"><use href="#icon-heart"/></svg>@if(in_array($activity->code, ['quiz_creator_reward', 'quiz_weekly_rank_rewards'], true)){{ $activity->reward_amount_label }}@elseif(in_array($activity->code, ['comment_create', 'daily_attendance', 'quiz_perfect'], true) && $activity->min_heart_amount !== $activity->max_heart_amount)+{{ number_format($activity->min_heart_amount) }}~{{ number_format($activity->max_heart_amount) }}@else+{{ number_format($activity->heart_amount) }}@endif</strong>
                        <small>@if(!$activity->is_enabled){{ __('ui.unavailable') }} @elseif($activity->code === 'quiz_weekly_rank_rewards'){{ __('quiz.weekly_creator_rewards_frequency') }} @elseif($activity->code === 'quiz_perfect'){{ __('ui.quiz_perfect_reward_scope') }} @elseif($activity->code === 'quiz_creator_reward'){{ __('ui.quiz_creator_reward_scope') }} @elseif($activity->daily_limit > 0){{ __('ui.per_day', ['limit' => number_format($activity->daily_limit)]) }} @else {{ __('ui.no_daily_limit') }} @endif</small>
                    </div>
                    @if($activity->daily_limit > 0)
                        <div class="get-hearts-progress">
                            @if(!$activity->is_enabled)
                                <span class="get-hearts-progress-unavailable">{{ __('ui.unavailable') }}</span>
                            @else
                                <span class="get-hearts-progress-track"><i style="width: {{ $member ? $activity->progress_percent : 0 }}%" @if($activity->code === 'daily_attendance') data-daily-check-in-progress-bar @endif></i></span>
                                @if($member)
                                    <strong @if($activity->code === 'daily_attendance') data-daily-check-in-progress @endif>{{ number_format($activity->progress) }} / {{ number_format($activity->daily_limit) }}</strong>
                                @else
                                    <span class="get-hearts-progress-guest">{{ __('ui.sign_in_track') }}</span>
                                @endif
                            @endif
                        </div>
                    @elseif($activity->code === 'quiz_perfect')
                        <p class="get-hearts-activity-note">{{ __('ui.quiz_perfect_daily_rule') }}</p>
                    @elseif($activity->code === 'quiz_creator_reward')
                        <p class="get-hearts-activity-note">{{ __('ui.quiz_creator_reward_rule') }}</p>
                    @elseif($activity->code === 'invite_friend')
                        <p class="get-hearts-activity-note">@if($activity->is_enabled){{ __('ui.invite_reward_description', ['amount' => number_format($activity->heart_amount)]) }}@else {{ __('ui.activity_unavailable') }}@endif</p>
                    @elseif($activity->code === 'idol_registration_approved')
                        <p class="get-hearts-activity-note">@if($activity->is_enabled){{ __('ui.idol_registration_reward_description', ['amount' => number_format($activity->heart_amount)]) }}@else {{ __('ui.activity_unavailable') }}@endif</p>
                    @elseif($activity->code !== 'quiz_weekly_rank_rewards')
                        <div class="get-hearts-progress">
                            <span class="get-hearts-unlimited">@if($activity->is_enabled)??<small>{{ __('ui.no_daily_limit') }}</small>@else<small>{{ __('ui.unavailable') }}</small>@endif</span>
                        </div>
                    @endif
                    @if($activity->code === 'invite_friend')
                        <button class="get-hearts-action" type="button" data-invite-friends-open
                            data-signed-in="{{ $member ? 'true' : 'false' }}"
                            @unless($member) data-sign-in-open aria-controls="sign-in-modal" @else aria-controls="invite-friends-dialog" @endunless
                            aria-haspopup="dialog">{{ $member ? __('ui.share_invitation') : __('ui.sign_in') }}</button>
                    @elseif($activity->code === 'quiz_perfect')
                        <a class="get-hearts-action" href="{{ route('quizzes.index') }}">{{ __('ui.solve_quizzes') }}</a>
                    @elseif($activity->code === 'quiz_creator_reward')
                        <a class="get-hearts-action" href="{{ route('quizzes.create') }}">{{ __('ui.create_quiz') }}</a>
                    @elseif($activity->code === 'quiz_weekly_rank_rewards')
                        <a class="get-hearts-action" href="{{ route('quizzes.index') }}">{{ __('quiz.weekly_creator_rewards_action') }}</a>
                    @elseif($activity->code === 'watch_ads')
                        @if(!$activity->is_enabled)
                            <span class="get-hearts-status get-hearts-status--muted">{{ __('ui.unavailable') }}</span>
                        @elseif(!$member)
                            <button class="get-hearts-action" type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">{{ __('ui.sign_in') }}</button>
                        @elseif($complete)
                            <span class="get-hearts-status get-hearts-status--muted">{{ __('ui.daily_limit_reached') }}</span>
                        @else
                            <button class="get-hearts-action" type="button" data-watch-ad-open aria-haspopup="dialog" aria-controls="watch-ad-dialog">{{ __('ui.watch_ad') }}</button>
                        @endif
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="get-hearts-sponsors" aria-labelledby="sponsor-banners-title">
        <header><h2 id="sponsor-banners-title">{{ __('ui.partner_banners') }}</h2><p>{{ __('ui.discover_partners') }}</p></header>
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
                <p class="get-hearts-sponsor-empty">{{ __('ui.no_partner_banners') }}</p>
            @endforelse
        </div>
        <p class="get-hearts-sponsor-note"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg> {{ __('ui.eligible_banner_rewards') }}</p>
    </section>

    <aside class="get-hearts-notes" aria-label="{{ __('ui.heart_notes') }}"><strong>&#9432; {{ __('ui.notes') }}</strong><ul><li>{{ __('ui.automatic_activity_rewards') }}</li><li>{{ __('ui.limits_vary') }}</li><li>{{ __('ui.fraud_cancellation') }}</li></ul></aside>

    @if($member)
        <dialog id="watch-ad-dialog" class="watch-ad-dialog" aria-labelledby="watch-ad-title">
            <button class="watch-ad-close" type="button" data-watch-ad-close aria-label="{{ __('ui.close') }}">&times;</button>
            <span class="watch-ad-icon" aria-hidden="true">&#9654;</span>
            <h2 id="watch-ad-title">{{ __('ui.watch_ads') }}</h2>
            <p>{{ __('ui.watch_ad_intro') }}</p>
            <div><span>{{ __('ui.reward') }}</span><strong>{{ __('ui.heart_reward_settings') }}</strong><span>{{ __('ui.status') }}</span><strong>{{ __('ui.integration_soon') }}</strong></div>
            <p class="watch-ad-notice">{{ __('ui.ad_preview_notice') }}</p>
            <button type="button" disabled>{{ __('ui.watch_ad') }}</button>
        </dialog>

        @if($partnerBanners->contains(fn ($banner) => $banner->claim_url !== null || $banner->completed_today))
        <dialog id="partner-banner-reward-dialog" class="partner-banner-reward-dialog" aria-labelledby="partner-banner-reward-title">
            <button class="watch-ad-close" type="button" data-partner-banner-close aria-label="{{ __('ui.close') }}">&times;</button>
            <h2 id="partner-banner-reward-title">{{ __('ui.partner_banner') }}</h2>
            <p><strong data-partner-banner-amount></strong> Hearts are available for this banner.</p>
            <p class="partner-banner-reward-message">{{ __('ui.banner_reward_confirm') }}</p>
            <button type="button" data-partner-banner-claim data-csrf="{{ csrf_token() }}">{{ __('ui.claim_reward') }}</button>
        </dialog>
        @endif
    @endif
</div>
@endsection
