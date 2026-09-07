@can('tickets.view')
<li class="slide__category"><span class="category-name">Complaints &amp; Tickets</span></li>
<li class="slide">
    <a href="{{ route('workspace.department') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-speedometer2" ></i>
        <span class="side-menu__label">Dashboard</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('tickets.queue') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-ticket-detailed" ></i>
        <span class="side-menu__label">Ticket Queue</span>
    </a>
</li>
<li class="slide">
    <a href="{{ route('workspace.tickets') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-person-lines-fill" ></i>
        <span class="side-menu__label">
            Assigned Ticket
            @include('components.menu._my-tickets-count')
        </span>
    </a>
</li>
@endcan
@can('tickets.create')
<li class="slide">
    <a href="{{ route('tickets.intake') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-headset" ></i>
        <span class="side-menu__label">CSR Intake</span>
    </a>
</li>
@endcan
@can('tickets.view')
<li class="slide">
    <a href="{{ route('tickets.escalations') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-exclamation-triangle" ></i>
        <span class="side-menu__label">
            Escalations
            @include('components.menu._escalations-count')
        </span>
    </a>
</li>
@endcan
@can('ai.ticket-analysis.view')
<li class="slide">
    <a href="{{ route('tickets.ai') }}" class="side-menu__item">
        <i class="w-6 h-4 side-menu__icon bi bi-robot" ></i>
        <span class="side-menu__label">AI Ticket Insights</span>
    </a>
</li>
@endcan
@include('components.menu.knowledge')
