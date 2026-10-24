@if ($locale === 'en')
    <div class="idol-request-date-control" data-iso-date-control>
        <input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ $value }}" maxlength="10" pattern="[0-9]{4}-[0-9]{2}-[0-9]{2}" inputmode="numeric" autocomplete="off" placeholder="YYYY-MM-DD" data-iso-date-text data-max-date="{{ $max }}" aria-label="{{ $ariaLabel }}" @if ($required ?? false) required @endif>
        <button type="button" class="idol-request-date-picker-button" data-open-iso-date-picker aria-label="{{ $ariaLabel }}">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>
        </button>
        <input class="visually-hidden" type="date" value="{{ $value }}" max="{{ $max }}" lang="en" tabindex="-1" aria-hidden="true" data-iso-date-picker>
    </div>
@else
    <input @if ($id) id="{{ $id }}" @endif type="date" name="{{ $name }}" value="{{ $value }}" max="{{ $max }}" @if ($locale) lang="{{ $locale }}" @endif aria-label="{{ $ariaLabel }}" @if ($required ?? false) required @endif>
@endif
