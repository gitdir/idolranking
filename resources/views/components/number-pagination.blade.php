@props(['paginator', 'sample' => false, 'window' => 2, 'label' => 'Fan post pages'])

@if ($paginator->hasPages() || $sample)
    @if ($sample && !$paginator->hasPages())
        <nav class="number-pagination number-pagination--sample" aria-label="Sample Fan Post pagination">
            <span class="number-pagination-arrow" aria-disabled="true">&lsaquo;</span>
            <span class="number-pagination-current" aria-current="page">1</span>
            <span>2</span>
            <span>3</span>
            <span class="number-pagination-sample-extra">4</span>
            <span class="number-pagination-sample-extra">5</span>
            <span class="number-pagination-ellipsis" aria-hidden="true">&hellip;</span>
            <span>12</span>
            <span class="number-pagination-arrow">&rsaquo;</span>
        </nav>
    @else
    @php
        $windowStart = max(1, $paginator->currentPage() - $window);
        $windowEnd = min($paginator->lastPage(), $paginator->currentPage() + $window);
        $windowPages = $windowStart <= $windowEnd ? range($windowStart, $windowEnd) : [];
        $pages = collect([1, $paginator->lastPage()])
            ->merge($windowPages)
            ->unique()->sort()->values();
        $previousNumber = null;
    @endphp
    <nav class="number-pagination" aria-label="{{ $label }}">
        @if ($paginator->onFirstPage())
            <span class="number-pagination-arrow" aria-disabled="true">&lsaquo;</span>
        @else
            <a class="number-pagination-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&lsaquo;</a>
        @endif

        @foreach ($pages as $page)
            @if ($previousNumber !== null && $page > $previousNumber + 1)
                <span class="number-pagination-ellipsis" aria-hidden="true">&hellip;</span>
            @endif
            @if ($page === $paginator->currentPage())
                <span class="number-pagination-current" aria-current="page">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
            @endif
            @php($previousNumber = $page)
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="number-pagination-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&rsaquo;</a>
        @else
            <span class="number-pagination-arrow" aria-disabled="true">&rsaquo;</span>
        @endif
    </nav>
    @endif
@endif
