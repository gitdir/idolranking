@props(['code'])
@php
    $countryCode = strtoupper((string) $code);
    $flagPath = preg_match('/^[A-Z]{2}$/', $countryCode)
        ? public_path('images/flags/'.strtolower($countryCode).'.svg')
        : null;
@endphp

@if ($flagPath && is_file($flagPath))
    <img class="country-flag" src="{{ asset('images/flags/'.strtolower($countryCode).'.svg') }}" width="18" height="13" alt="" aria-hidden="true" loading="lazy" decoding="async">
@endif
