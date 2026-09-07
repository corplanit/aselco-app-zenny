@props([
    'icon' => null,
    'quiet' => false,
])

<span {{ $attributes->class(['ul-chip', 'ul-chip-quiet' => $quiet]) }}>
    @if($icon)
        <i class="bi {{ $icon }}" aria-hidden="true"></i>
    @endif
    <span>{{ $slot }}</span>
</span>
