@props(['paginator'])

@if ($paginator->hasPages())
    @php
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $pages = collect([1, $lastPage])
            ->merge(range(max(1, $currentPage - 2), min($lastPage, $currentPage + 2)))
            ->unique()->sort()->values();
        $previousPage = null;
    @endphp
    <footer class="admin-table-footer admin-access-pagination">
        <nav aria-label="페이지 이동">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="이전 페이지">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="이전 페이지">&lsaquo;</a>
            @endif
            @foreach ($pages as $page)
                @if ($previousPage !== null && $page > $previousPage + 1)
                    <span class="admin-pagination-ellipsis" aria-hidden="true">&hellip;</span>
                @endif
                @if ($page === $currentPage)
                    <strong aria-current="page">{{ $page }}</strong>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="{{ $page }}페이지로 이동">{{ $page }}</a>
                @endif
                @php($previousPage = $page)
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="다음 페이지">&rsaquo;</a>
            @else
                <span aria-disabled="true" aria-label="다음 페이지">&rsaquo;</span>
            @endif
        </nav>
    </footer>
@endif
