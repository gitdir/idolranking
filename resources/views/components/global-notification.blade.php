@php($initialNotification = session('global_notification'))
<div class="global-notification" data-global-notification role="status" aria-live="polite" aria-atomic="true" hidden
    @if(is_array($initialNotification))
        data-initial-title="{{ $initialNotification['title'] ?? '' }}"
        data-initial-message="{{ $initialNotification['message'] ?? '' }}"
        data-initial-type="{{ $initialNotification['type'] ?? 'success' }}"
    @endif>
    <div class="global-notification-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>
    </div>
    <div>
        <strong data-global-notification-title></strong>
        <p data-global-notification-message></p>
        <small data-global-notification-detail></small>
    </div>
</div>
