@props([
    'label',
    'column',
    'currentSort' => null,
    'currentDir' => 'desc',
    'baseUrl' => null,
    'query' => [],
    'defaultSort' => 'id',
])

@php
    $baseUrl = $baseUrl ?? url()->current();
    $isActive = $currentSort === $column;
    $href = \App\Support\ListQuery::sortUrl($baseUrl, $query, $column, $currentSort, $currentDir, $defaultSort);
    $icon = 'bi-arrow-down-up';
    if ($isActive) {
        $icon = $currentDir === 'asc' ? 'bi-sort-up' : 'bi-sort-down';
    }
@endphp

<th {{ $attributes }}>
    <a
        href="{{ $href }}"
        class="ul-th-sort {{ $isActive ? 'is-active' : '' }}"
        title="Sort by {{ $label }}"
    >
        <span>{{ $label }}</span>
        <i class="bi {{ $icon }} ul-th-sort-icon" aria-hidden="true"></i>
    </a>
</th>
