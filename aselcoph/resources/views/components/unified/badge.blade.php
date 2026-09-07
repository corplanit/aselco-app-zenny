@props([
    'tone' => 'neutral',
    'icon' => null,
])

@php
    $allowed = [
        'success', 'warning', 'danger', 'info', 'primary', 'neutral',
        'amber', 'cyan', 'orange', 'slate', 'rose', 'teal', 'violet',
        'indigo', 'sky', 'purple', 'lime',
    ];
    $tone = in_array($tone, $allowed, true) ? $tone : 'neutral';
@endphp

<span {{ $attributes->class(['ul-badge', 'is-'.$tone]) }}>
    @if($icon)
        <i class="bi {{ $icon }}" aria-hidden="true"></i>
    @endif
    <span>{{ $slot }}</span>
</span>
