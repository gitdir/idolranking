@props(['title', 'type' => 'boy'])
@php
    $banners = [
        'boy' => ['file' => 'boy.png', 'width' => 827, 'height' => 140],
        'boy-group' => ['file' => 'boygroup.png', 'width' => 2172, 'height' => 359],
        'girl' => ['file' => 'girl.png', 'width' => 2172, 'height' => 359],
        'girl-group' => ['file' => 'girlgroup.png', 'width' => 2508, 'height' => 627],
    ];
    $banner = $banners[$type];
@endphp

<section class="page-hero-banner ranking-banner ranking-banner--{{ $type }}" aria-label="{{ $title }}">
    <h1 class="sr-only">{{ $title }}</h1>
    <img class="ranking-banner-image" src="{{ asset('images/ranking-banners/'.$banner['file']) }}" width="{{ $banner['width'] }}" height="{{ $banner['height'] }}" alt="Celebrate your favorites. Every Heart counts.">
</section>
