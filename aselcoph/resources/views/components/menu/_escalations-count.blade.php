@php
    $openEscalations = Auth::user()?->openEscalationCount() ?? 0;
@endphp
@if($openEscalations > 0)
    <span class="side-menu__badge badge !rounded-full bg-danger">{{ $openEscalations }}</span>
@endif
