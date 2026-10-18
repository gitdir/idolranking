@props(['posts', 'headingId' => 'fan-posts-title', 'currentFanPostIdx' => null, 'layout' => null, 'emptyMessage' => null])

<section @class(['fan-posts', 'fan-posts--'.$layout => $layout]) aria-labelledby="{{ $headingId }}">
    @isset($heading)
        {{ $heading }}
    @else
        <h2 id="{{ $headingId }}">{{ __('ui.latest_fan_posts') }}</h2>
    @endisset
    @if (count($posts))
        <div class="fan-post-grid">
        @foreach ($posts as $post)
            <x-fan-post-card :post="$post" :is-current="$currentFanPostIdx !== null && ($post['fan_post_idx'] ?? null) === $currentFanPostIdx" />
        @endforeach
        </div>
    @elseif ($emptyMessage)
        <p class="fan-post-empty">{{ $emptyMessage }}</p>
    @endif
</section>
