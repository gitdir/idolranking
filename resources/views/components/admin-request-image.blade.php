@props([
    'path' => null,
    'type' => 'individual',
    'alt' => '',
])

@php
    $imageUrl = \App\Support\ManagedImageStorage::publicUrl($path);
@endphp

<img src="{{ $imageUrl }}" alt="{{ $alt }}" {{ $attributes }}>
