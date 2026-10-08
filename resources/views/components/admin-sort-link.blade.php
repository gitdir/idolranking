@props(['column', 'label', 'sort', 'direction'])
@php($next = $sort === $column && $direction === 'asc' ? 'desc' : 'asc')
<a class="admin-sort-link" href="{{ request()->fullUrlWithQuery(['sort'=>$column,'direction'=>$next,'page'=>null]) }}">
    {{ $label }} @if($sort === $column)<span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>@endif
</a>
