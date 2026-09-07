@props([
    'name' => null,
    'value' => '1',
    'checked' => false,
    'all' => null,
])

<label {{ $attributes->class(['ul-check'])->only('class') }}>
    <input
        type="checkbox"
        @if($name) name="{{ $name }}" @endif
        value="{{ $value }}"
        @checked($checked)
        @if($all) data-ul-check-all="{{ $all }}" @endif
        {{ $attributes->except('class') }}
    >
    @if(trim((string) $slot) !== '')
        <span class="ul-check-text">{{ $slot }}</span>
    @endif
</label>
