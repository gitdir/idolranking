@props(['posts', 'title', 'headingId', 'seeAllUrl' => null])

<section class="fan-detail-box" aria-labelledby="{{ $headingId }}">
    <div class="fan-detail-box-heading"><h2 id="{{ $headingId }}">{{ $title }}</h2>@if($seeAllUrl)<a href="{{ $seeAllUrl }}">See All <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m6 4 4 4-4 4"/></svg></a>@else<button type="button" disabled>See All <svg viewBox="0 0 16 16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m6 4 4 4-4 4"/></svg></button>@endif</div>
    <ul class="fan-detail-related">
        @foreach ($posts as $item)
            <li><a href="{{ route('fan-post.show', ['idx' => $item['idx']]) }}">
                <img src="{{ \App\Support\ManagedImageStorage::publicUrl($item['image_url'] ?? null) }}" width="64" height="64" alt="" loading="lazy">
                <span>{{ $item['title'] }}<small class="fan-detail-time">{{ $item['time'] ?? ($loop->iteration + 2).' days ago' }}</small></span>
            </a></li>
        @endforeach
    </ul>
</section>
