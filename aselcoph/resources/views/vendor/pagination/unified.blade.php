@if ($paginator->hasPages())
    <nav class="ul-pages" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="ul-page is-disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="ul-page" rel="prev" aria-label="{{ __('pagination.previous') }}">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="ul-page is-disabled" aria-disabled="true">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="ul-page is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="ul-page" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="ul-page" rel="next" aria-label="{{ __('pagination.next') }}">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        @else
            <span class="ul-page is-disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </span>
        @endif
    </nav>
@endif
