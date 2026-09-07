@php
    $slaWatchCount = Auth::user()?->openSlaWatchCount() ?? 0;
@endphp
@if($slaWatchCount > 0)
    <span class="side-menu__badge badge !rounded-full bg-danger">{{ $slaWatchCount }}</span>
@endif
