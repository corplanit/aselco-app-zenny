@props([
    'paginator' => null,
    'iteration' => null,
    'index' => null,
])

@php
    if ($index !== null) {
        $num = $index;
    } elseif ($paginator !== null && $iteration !== null) {
        $num = (($paginator->currentPage() - 1) * $paginator->perPage()) + (int) $iteration;
    } else {
        $num = '—';
    }
@endphp

<td class="ul-row-num ul-no-row-click" {{ $attributes }}>
    @if($num === '—')
        <span class="ul-empty">—</span>
    @else
        <span class="ul-row-num-value">{{ $num }}.</span>
    @endif
</td>
