@props([
    'title' => 'Records',
    'paginator' => null,
    'hasFilters' => false,
    'resetUrl' => null,
    'emptyTitle' => 'No records found.',
    'emptyText' => 'Try changing your search or filter criteria.',
    'filteredTitle' => 'No results match your current filters.',
    'items' => null,
    'colspan' => 8,
    'loading' => false,
    'error' => null,
    'showCount' => true,
    'fit' => false,
])

@php
    $resetUrl = $resetUrl ?? url()->current();
    if ($paginator !== null) {
        $isEmpty = $paginator->total() === 0;
        $total = $paginator->total();
    } elseif ($items !== null) {
        $total = is_countable($items) ? count($items) : 0;
        $isEmpty = $total === 0;
    } else {
        $total = null;
        $isEmpty = false;
    }
    $countLabel = null;
    if ($showCount && $total !== null) {
        $countLabel = number_format($total).' '
            .($total === 1 ? 'record' : 'records');
    }
    $hasFooter = isset($footer) && trim((string) $footer) !== '';
    $hasHeaderActions = isset($headerActions) && trim((string) $headerActions) !== '';
@endphp

<div
    {{ $attributes->merge(['class' => 'box ul-card'.($fit ? ' ul-fit-table' : '')]) }}
    @if($fit) data-ul-fit-table="1" @endif
>
    <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-2">
        <div class="box-title ul-card-title mb-0">
            {{ $title }}
            @if($countLabel)
                <span class="ul-card-count" @if($fit) data-ul-fit-count @endif>{{ $countLabel }}</span>
            @endif
        </div>
        @if($hasHeaderActions || $hasFooter)
            <div class="ul-card-header-actions flex flex-wrap items-center gap-2">
                @if($hasHeaderActions)
                    {{ $headerActions }}
                @endif
                @if($hasFooter)
                    {{ $footer }}
                @endif
            </div>
        @endif
    </div>

    <div class="box-body p-0">
        @if($loading)
            <x-unified.loading-state />
        @elseif($error)
            <x-unified.error-state :message="$error" />
        @else
            <div class="ul-table-wrap table-responsive" @if($fit) data-ul-fit-wrap @endif>
                <table class="table ul-table mb-0">
                    @if(isset($head))
                        <thead>
                            <tr>{{ $head }}</tr>
                        </thead>
                    @endif
                    <tbody>
                        @if($isEmpty)
                            <tr>
                                <td colspan="{{ $colspan }}">
                                    <x-unified.empty-state
                                        :title="$hasFilters ? $filteredTitle : $emptyTitle"
                                        :text="$emptyText"
                                        :reset-url="$hasFilters ? $resetUrl : null"
                                        :show-clear="$hasFilters"
                                    />
                                </td>
                            </tr>
                        @else
                            {{ $slot }}
                        @endif
                    </tbody>
                </table>
            </div>

            @if($paginator && ! $fit)
                <div class="ul-table-bar">
                    <x-unified.pagination :paginator="$paginator" />
                </div>
            @elseif($fit && ! $isEmpty)
                <div class="ul-table-bar" data-ul-fit-bar>
                    <div class="ul-pagination">
                        <div class="ul-pagination-meta" data-ul-fit-meta></div>
                        <div class="ul-pagination-controls">
                            <nav class="ul-pages" data-ul-fit-pages role="navigation" aria-label="Pagination" hidden></nav>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
