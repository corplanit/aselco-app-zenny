@php
    $myOpenTickets = Auth::user()?->openAssignedTicketCount() ?? 0;
@endphp
@if($myOpenTickets > 0)
    <span class="side-menu__badge badge !rounded-full bg-danger">{{ $myOpenTickets }}</span>
@endif
