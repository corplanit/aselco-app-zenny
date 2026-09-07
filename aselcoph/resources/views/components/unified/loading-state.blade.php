@props([
    'text' => 'Loading rows…',
])

<div class="ul-state" role="status" aria-live="polite">
    <div class="ul-state-title">{{ $text }}</div>
</div>
