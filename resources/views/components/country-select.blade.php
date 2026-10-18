@props([
    'id', 'selected' => null, 'placeholder' => null, 'name' => 'country_code', 'disabled' => false,
    'requiredSelection' => true,
    'searchLabel' => null, 'listLabel' => null, 'emptyLabel' => null, 'options' => null,
])
@php
    $countries = $options ?? \App\Support\Countries::records();
    $selectedCode = strtoupper((string) $selected);
    $selectedName = $countries[$selectedCode]['display'] ?? '';
    $panelId = $id.'-panel';
@endphp

<div class="country-picker" data-country-picker data-country-required="{{ $requiredSelection ? 'true' : 'false' }}">
    <button id="{{ $id }}" class="country-picker-trigger" type="button" data-country-trigger @disabled($disabled)
        aria-haspopup="listbox" aria-expanded="false" aria-controls="{{ $panelId }}">
        <span data-country-label @class(['country-picker-placeholder' => $selectedName === ''])>{{ $selectedName ?: ($placeholder ?: __('ui.select_country')) }}</span>
        <svg class="country-picker-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <input class="country-picker-code" type="hidden" name="{{ $name }}" value="{{ $selectedCode }}" data-country-code @disabled($disabled)>
    <div id="{{ $panelId }}" class="country-picker-panel" data-country-panel hidden>
        <label class="sr-only" for="{{ $id }}-search">{{ $searchLabel ?? __('ui.search_country') }}</label>
        <input id="{{ $id }}-search" class="country-picker-search" type="search" placeholder="{{ $searchLabel ?? __('ui.search_country') }}" autocomplete="off" data-country-search>
        <div class="country-picker-list" role="listbox" aria-label="{{ $listLabel ?? __('ui.countries') }}" data-country-list>
            @foreach ($countries as $country)
                <button type="button" role="option" data-country-option data-code="{{ $country['code'] }}"
                    data-native="{{ $country['native'] }}" data-english="{{ $country['english'] }}" data-name="{{ $country['display'] }}"
                    aria-selected="{{ $selectedCode === $country['code'] ? 'true' : 'false' }}">{{ $country['display'] }}</button>
            @endforeach
        </div>
        <p class="country-picker-empty" data-country-empty hidden>{{ $emptyLabel ?? __('ui.no_countries') }}</p>
    </div>
</div>
