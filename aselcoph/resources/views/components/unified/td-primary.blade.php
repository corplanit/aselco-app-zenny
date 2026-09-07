@props([
    'href',
    'text' => null,
])

<td {{ $attributes->class(['ul-td-primary']) }}>
    <a href="{{ $href }}" class="ul-primary-link ul-no-row-click">{{ $text ?? $slot }}</a>
    @isset($meta)
        <div class="ul-meta">{{ $meta }}</div>
    @endisset
</td>
