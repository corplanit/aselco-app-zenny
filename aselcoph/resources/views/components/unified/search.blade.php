@props([
    'name' => 'search',
    'value' => '',
    'placeholder' => 'Search…',
    'live' => false,
    'debounceMs' => 350,
])

<div
    class="ul-search"
    x-data="ulSearch({
        q: @js((string) $value),
        live: @js((bool) $live),
        debounceMs: @js((int) $debounceMs),
        name: @js($name)
    })"
    x-init="if (isLocalForm() || $el.closest('[data-ul-table-filter]')) { filterLocal(); }"
>
    <i class="bi bi-search ul-search-icon" aria-hidden="true"></i>
    <input
        type="search"
        name="{{ $name }}"
        x-ref="input"
        x-model="q"
        @input="onType()"
        @keydown.enter.prevent="
            const form = $el.closest('form');
            if (form && !form.hasAttribute('data-ul-local')) {
                form.requestSubmit();
                return;
            }
            clearTimeout(timer);
            filterLocal();
        "
        placeholder="{{ $placeholder }}"
        class="ti-form-input ul-search-input"
        autocomplete="off"
        aria-label="{{ $placeholder }}"
        {{ $attributes }}
    >
    <button
        type="button"
        class="ul-search-clear"
        x-show="q.length > 0"
        x-cloak
        @click="clear()"
        title="Clear search"
        aria-label="Clear search"
    >
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
</div>
