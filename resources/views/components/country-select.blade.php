@props(['id', 'selected' => null, 'placeholder' => 'Select your country'])
@php
    $countries = \App\Support\Countries::records();
    $selectedCode = strtoupper((string) $selected);
    $selectedName = $countries[$selectedCode]['display'] ?? '';
    $panelId = $id.'-panel';
@endphp

<div class="country-picker" data-country-picker>
    <button id="{{ $id }}" class="country-picker-trigger" type="button" data-country-trigger
        aria-haspopup="listbox" aria-expanded="false" aria-controls="{{ $panelId }}">
        <span data-country-label @class(['country-picker-placeholder' => $selectedName === ''])>{{ $selectedName ?: $placeholder }}</span>
        <svg class="country-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <input class="country-picker-code" type="hidden" name="country_code" value="{{ $selectedCode }}" data-country-code>
    <div id="{{ $panelId }}" class="country-picker-panel" data-country-panel hidden>
        <label class="sr-only" for="{{ $id }}-search">Search country</label>
        <input id="{{ $id }}-search" class="country-picker-search" type="search" placeholder="Search country..." autocomplete="off" data-country-search>
        <div class="country-picker-list" role="listbox" aria-label="Countries" data-country-list>
            @foreach ($countries as $country)
                <button type="button" role="option" data-country-option data-code="{{ $country['code'] }}"
                    data-native="{{ $country['native'] }}" data-english="{{ $country['english'] }}" data-name="{{ $country['display'] }}"
                    aria-selected="{{ $selectedCode === $country['code'] ? 'true' : 'false' }}">{{ $country['display'] }}</button>
            @endforeach
        </div>
        <p class="country-picker-empty" data-country-empty hidden>No countries found.</p>
    </div>
</div>
