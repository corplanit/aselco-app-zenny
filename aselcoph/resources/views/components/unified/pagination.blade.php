@props([
    'paginator',
    'perPageOptions' => [10, 25, 50, 100],
    'showPageSize' => true,
])

@php
    $from = $paginator->firstItem() ?? 0;
    $to = $paginator->lastItem() ?? 0;
    $total = $paginator->total();
@endphp

<div class="ul-pagination">
    <div class="ul-pagination-meta">
        @if($total > 0)
            Showing {{ number_format($from) }}–{{ number_format($to) }} of {{ number_format($total) }}
        @else
            Showing 0 records
        @endif
    </div>

    <div class="ul-pagination-controls">
        @if($showPageSize && method_exists($paginator, 'perPage'))
            <form method="GET" action="{{ url()->current() }}" class="ul-page-size">
                @foreach(request()->except(['per_page', 'page']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $v)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="ul-per-page">Rows</label>
                <select
                    id="ul-per-page"
                    name="per_page"
                    class="ti-form-select ul-select ul-choice-skip"
                    onchange="this.form.submit()"
                    aria-label="Rows per page"
                >
                    @foreach($perPageOptions as $size)
                        <option value="{{ $size }}" @selected((int) $paginator->perPage() === (int) $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        {{ $paginator->onEachSide(1)->withQueryString()->links('vendor.pagination.unified') }}
    </div>
</div>
