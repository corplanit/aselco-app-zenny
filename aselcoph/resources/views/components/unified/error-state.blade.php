@props([
    'message' => 'Unable to load records.',
    'retryUrl' => null,
])

@php
    $retryUrl = $retryUrl ?? url()->full();
@endphp

<div class="ul-state" role="alert">
    <div class="ul-state-title">{{ $message }}</div>
    <a href="{{ $retryUrl }}" class="ti-btn ti-btn-primary ti-btn-sm">Retry</a>
</div>
