@extends('layouts.app')

@section('title', __('ui.attendance_title').' | IdolRanking')

@section('content')
<div class="attendance-page">
    <header class="attendance-hero">
        <span class="attendance-hero-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 9h18m-13 5 2 2 4-4"/></svg></span>
        <div>
            <h1>{{ __('ui.attendance_title') }}</h1>
            <p>{{ __('ui.attendance_description_prefix') }} <span class="attendance-hero-reward"><svg class="heart-icon--small" aria-hidden="true"><use href="#icon-heart"/></svg>+{{ number_format((int) ($attendanceReward?->min_heart_amount ?? $attendanceReward?->heart_amount ?? 0)) }}~{{ number_format((int) ($attendanceReward?->max_heart_amount ?? $attendanceReward?->heart_amount ?? 0)) }}</span> {{ __('ui.attendance_description_suffix') }}</p>
        </div>
    </header>

    <section class="attendance-composer" aria-labelledby="attendance-composer-title" data-attendance-check-in-composer>
        <div class="attendance-section-heading">
            <h2 id="attendance-composer-title">{{ __('ui.attendance_check_in') }}</h2>
            <span>{{ __('ui.attendance_once_utc_day') }}</span>
        </div>
        @if ($todayCheckIn)
            <div class="attendance-complete" data-attendance-completed role="status">
                <strong>{{ __('ui.attendance_complete_today') }}</strong>
                @if ($todayCheckIn->reward_awarded)
                    <span><svg class="heart-icon--small" aria-hidden="true"><use href="#icon-heart"/></svg>+{{ number_format((int) $todayCheckIn->awarded_hearts) }}</span>
                @endif
                <p class="attendance-complete-message">{{ $todayCheckIn->content }}</p>
            </div>
        @elseif (!$checkInEnabled)
            <p class="attendance-composer-message" role="status">{{ __('ui.attendance_reward_unavailable') }}</p>
        @elseif (!$member)
            <div class="attendance-guest-prompt">
                <p>{{ __('ui.attendance_sign_in_prompt') }}</p>
                <button type="button" data-sign-in-open aria-haspopup="dialog" aria-controls="sign-in-modal">{{ __('ui.sign_in') }}</button>
            </div>
        @else
            <form class="attendance-check-in-form" data-attendance-check-in-form
                data-endpoint="{{ route('get-hearts.daily-check-in') }}" data-csrf="{{ csrf_token() }}"
                data-completed-label="{{ __('ui.attendance_complete_today') }}">
                <label class="sr-only" for="attendance-message">{{ __('ui.check_in_message_label') }}</label>
                <textarea id="attendance-message" name="content" rows="3" maxlength="200" required
                    placeholder="{{ __('ui.check_in_message_placeholder') }}"></textarea>
                <div class="attendance-form-footer">
                    <small>{{ __('ui.attendance_text_only_hint') }}</small>
                    <div class="attendance-form-actions">
                        <label class="sr-only" for="attendance-language">{{ __('ui.check_in_language') }}</label>
                        <select id="attendance-language" class="fan-post-sort-select" name="language_code" required aria-label="{{ __('ui.check_in_language') }}">
                            @foreach (\App\Support\FanPostLanguages::all() as $code => $name)
                                <option value="{{ $code }}" @selected($code === app()->getLocale())>{{ $name }} ({{ strtoupper($code) }})</option>
                            @endforeach
                        </select>
                        <button type="submit" data-attendance-submit>{{ __('ui.check_in_submit') }}</button>
                    </div>
                </div>
                <p class="attendance-form-result" data-attendance-result role="status" aria-live="polite" hidden></p>
            </form>
        @endif
    </section>

    <section class="attendance-feed" aria-labelledby="attendance-feed-title" data-attendance-feed>
        <div class="attendance-section-heading">
            <h2 id="attendance-feed-title">{{ __('ui.attendance_community') }}</h2>
            <span>{{ __('ui.attendance_newest_first') }}</span>
        </div>
        @if ($checkIns->count())
            <div class="attendance-grid">
                @foreach ($checkIns as $checkIn)
                    <x-attendance-card :check-in="$checkIn" />
                @endforeach
            </div>
            <x-number-pagination :paginator="$checkIns" label="{{ __('ui.attendance_pages') }}" />
        @else
            <p class="attendance-empty">{{ __('ui.attendance_empty') }}</p>
        @endif
    </section>
</div>
@endsection
