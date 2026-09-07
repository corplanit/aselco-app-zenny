@props([
    'tone' => 'amber',
    'icon' => 'bi-info-circle',
    'title' => null,
])

@php
    $allowed = ['amber', 'slate', 'lime', 'rose', 'sky', 'success'];
    $tone = in_array($tone, $allowed, true) ? $tone : 'amber';
@endphp

<div {{ $attributes->class(['ul-note', 'is-'.$tone]) }} role="note">
    <span class="ul-note-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <div class="ul-note-copy">
        @if($title)
            <div class="ul-note-title">{{ $title }}</div>
        @endif
        <div class="ul-note-body">{{ $slot }}</div>
    </div>
</div>
