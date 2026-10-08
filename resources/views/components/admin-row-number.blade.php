@props(['paginator', 'index'])

{{ number_format($paginator->total() - (($paginator->currentPage() - 1) * $paginator->perPage()) - $index) }}
