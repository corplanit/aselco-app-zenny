@props([
    'options' => [],
    'value' => null,
    'name' => 'sort',
    'dirName' => 'dir',
    'dir' => 'desc',
])

<div class="ul-filter-wrap" x-data="{ open: false }" @keydown.escape.window="open = false">
    <button type="button" class="ti-btn ti-btn-light ti-btn-sm" @click="open = !open" :aria-expanded="open.toString()">
        <i class="bi bi-arrow-down-up me-1" aria-hidden="true"></i>
        Sort
    </button>
    <div class="ul-filter-panel" x-show="open" x-cloak x-transition @click.outside="open = false" style="min-width: 14rem;">
        <label class="ti-form-label">Sort by</label>
        <select name="{{ $name }}" class="ti-form-select mb-2">
            @foreach($options as $key => $label)
                <option value="{{ $key }}" @selected($value === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="ti-form-label">Direction</label>
        <select name="{{ $dirName }}" class="ti-form-select mb-2">
            <option value="asc" @selected($dir === 'asc')>Ascending</option>
            <option value="desc" @selected($dir === 'desc')>Descending</option>
        </select>
        <div class="ul-filter-actions">
            <button type="submit" class="ti-btn ti-btn-primary ti-btn-sm">Apply</button>
        </div>
    </div>
</div>
