@props([
    'action' => null,
    'method' => 'GET',
    'resetUrl' => null,
    'activeFilterCount' => 0,
    'showFilter' => true,
    'showReset' => true,
    'searchName' => 'search',
    'searchValue' => '',
    'searchPlaceholder' => 'Search…',
    'local' => false,
    'live' => false,
    'debounceMs' => 350,
])

@php
    $action = $action ?? url()->current();
    $resetUrl = $resetUrl ?? $action;
@endphp

<div {{ $attributes->merge(['class' => 'box mb-4']) }}>
    <div class="box-body !py-3">
        <div class="ul-toolbar">
            <form
                method="{{ $method }}"
                action="{{ $action }}"
                class="ul-toolbar-form"
                role="search"
                @if($local) data-ul-local @endif
            >
                @if($local)
                    <x-unified.search
                        :name="$searchName"
                        value=""
                        :placeholder="$searchPlaceholder"
                        :live="false"
                        data-ul-filter-search
                    />
                @else
                    <x-unified.search
                        :name="$searchName"
                        :value="$searchValue"
                        :placeholder="$searchPlaceholder"
                        :live="$live"
                        :debounce-ms="$debounceMs"
                    />
                @endif

                <div class="ul-toolbar-actions">
                    @if($showFilter)
                        <div class="ul-filter-wrap" x-data="{ open: false }" @keydown.escape.window="open = false">
                            <button
                                type="button"
                                class="ti-btn ti-btn-light ti-btn-sm"
                                @click="open = !open"
                                :aria-expanded="open.toString()"
                                aria-haspopup="dialog"
                            >
                                <i class="bi bi-funnel me-1" aria-hidden="true"></i>
                                Filter
                                <span class="ul-badge-count" data-ul-filter-count @if((int) $activeFilterCount === 0) hidden @endif>{{ (int) $activeFilterCount }}</span>
                            </button>

                            <div
                                class="ul-filter-panel"
                                x-show="open"
                                x-cloak
                                x-transition
                                @click.outside="open = false"
                                role="dialog"
                                aria-label="Filters"
                            >
                                <div class="ul-filter-grid">
                                    {{ $filters ?? '' }}
                                </div>
                                <div class="ul-filter-actions">
                                    @if($local)
                                        <button type="button" class="ti-btn ti-btn-light ti-btn-sm" data-ul-filter-reset>Clear</button>
                                        <button type="button" class="ti-btn ti-btn-primary ti-btn-sm" @click="open = false" data-ul-filter-apply>Done</button>
                                    @else
                                        <a href="{{ $resetUrl }}" class="ti-btn ti-btn-light ti-btn-sm">Clear</a>
                                        <button type="submit" class="ti-btn ti-btn-primary ti-btn-sm">Apply</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{ $actions ?? '' }}

                    @unless($local)
                        <button type="submit" class="ti-btn ti-btn-primary ti-btn-sm" data-ul-search-apply>
                            <i class="bi bi-search me-1" aria-hidden="true"></i>
                            Search
                        </button>
                    @endunless

                    @if($showReset)
                        @if($local)
                            <button type="button" class="ti-btn ti-btn-light ti-btn-sm" data-ul-filter-reset title="Reset search and filters">
                                Reset
                            </button>
                        @else
                            <a href="{{ $resetUrl }}" class="ti-btn ti-btn-light ti-btn-sm" title="Reset search and filters">
                                Reset
                            </a>
                        @endif
                    @endif
                </div>

                {{ $hidden ?? '' }}
            </form>
        </div>
        {{ $footer ?? '' }}
    </div>
</div>
