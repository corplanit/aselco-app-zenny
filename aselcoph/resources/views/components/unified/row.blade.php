@props([
    'href' => null,
])

<tr
    {{ $attributes->class(['ul-row' => filled($href)]) }}
    @if(filled($href))
        data-href="{{ $href }}"
        tabindex="0"
        role="link"
        aria-label="Open record"
    @endif
>
    {{ $slot }}
</tr>
