@props([
    'title' => 'No records found.',
    'text' => 'Try changing your search or filter criteria.',
    'resetUrl' => null,
    'showClear' => false,
])

<div class="ul-state">
    <div class="ul-state-icon" aria-hidden="true"><i class="bi bi-inbox"></i></div>
    <div class="ul-state-title">{{ $title }}</div>
    @if($text)
        <div class="ul-state-text">{{ $text }}</div>
    @endif
    @if($showClear && $resetUrl)
        <a href="{{ $resetUrl }}" class="ti-btn ti-btn-light ti-btn-sm">Clear Filters</a>
    @endif
</div>
