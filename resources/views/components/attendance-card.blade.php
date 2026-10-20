@props(['checkIn'])
@php
    $sourceLanguage = $checkIn['language_code'] ?? null;
    $canTranslate = $sourceLanguage && $sourceLanguage !== app()->getLocale();
    $badgeLanguage = strtoupper((string) $sourceLanguage);
    $createdAt = \Illuminate\Support\Carbon::parse($checkIn['created_at'], 'UTC')->timezone(config('app.timezone'));
@endphp
<article id="attendance-card-{{ $checkIn['idx'] }}" class="attendance-card" data-attendance-card
    data-check-in-id="{{ $checkIn['idx'] }}" @if($canTranslate)
    data-attendance-translation data-endpoint="{{ route('attendance.translation', ['idx' => $checkIn['idx']]) }}"
    data-target-language="{{ app()->getLocale() }}" @endif>
    <header class="attendance-card-author">
        <a class="attendance-card-avatar-link" href="{{ $checkIn['member_url'] }}" aria-label="{{ __('ui.member_view_posts_label', ['name' => $checkIn['nickname']]) }}">
            <img src="{{ $checkIn['profile_image_url'] }}" width="40" height="40" loading="lazy" alt="">
        </a>
        <div class="attendance-card-author-details">
            <a class="attendance-card-nickname" translate="no" href="{{ $checkIn['member_url'] }}"><x-country-flag :code="$checkIn['country_code'] ?? null" />{{ $checkIn['nickname'] }}</a>
        </div>
        @if ($sourceLanguage)
            <span class="attendance-card-language" title="{{ $badgeLanguage }}" aria-label="{{ $badgeLanguage }}">{{ $badgeLanguage }}</span>
        @endif
    </header>
    <div class="attendance-card-message-row">
        <p class="attendance-card-message"><span @if($canTranslate) data-attendance-content @endif>{{ $checkIn['content'] }}</span>@if ($canTranslate) <button class="attendance-translate-toggle" type="button" data-attendance-translate-toggle
                aria-expanded="false" aria-label="{{ __('ui.translate') }}">{{ __('ui.translate') }}</button>@endif</p>
    </div>
    <p class="attendance-card-translation-status" data-attendance-translation-status role="status" aria-live="polite" hidden></p>
    <footer class="attendance-card-footer">
        <time datetime="{{ $createdAt->toIso8601String() }}">{{ $createdAt->locale(app()->getLocale())->diffForHumans() }}</time>
        @if ($checkIn['reward_awarded'])
            <strong class="attendance-card-reward"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-heart"/></svg>+{{ number_format($checkIn['awarded_hearts']) }} Heart</strong>
        @endif
    </footer>
</article>
