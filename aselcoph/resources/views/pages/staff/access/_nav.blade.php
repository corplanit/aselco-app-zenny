@php
    $items = [
        ['route' => 'access.users.index', 'label' => 'Users', 'icon' => 'bi-people', 'can' => 'users.view'],
        ['route' => 'access.customers.index', 'label' => 'Customers', 'icon' => 'bi-person', 'can' => 'customers.view'],
        ['route' => 'access.support.index', 'label' => 'Support', 'icon' => 'bi-headset', 'can' => 'users.view'],
        ['route' => 'access.departments.index', 'label' => 'Departments', 'icon' => 'bi-diagram-3', 'can' => 'departments.view'],
        ['route' => 'access.roles.index', 'label' => 'Roles', 'icon' => 'bi-shield', 'can' => 'roles.view'],
        ['route' => 'access.permissions.index', 'label' => 'Permissions', 'icon' => 'bi-key', 'can' => 'permissions.view'],
        ['route' => 'access.sessions.index', 'label' => 'Sessions', 'icon' => 'bi-laptop', 'can' => 'sessions.view'],
        ['route' => 'access.activity.index', 'label' => 'Activity', 'icon' => 'bi-clock-history', 'can' => 'audit.view'],
        ['route' => 'access.availability.index', 'label' => 'Availability', 'icon' => 'bi-person-check', 'can' => 'users.view'],
        ['route' => 'access.assignments.index', 'label' => 'Assignments', 'icon' => 'bi-ticket-detailed', 'can' => 'tickets.view'],
        ['route' => 'access.reports.index', 'label' => 'Reports', 'icon' => 'bi-bar-chart', 'can' => 'reports.view'],
        ['route' => 'access.settings.index', 'label' => 'Settings', 'icon' => 'bi-gear', 'can' => 'settings.view'],
    ];
@endphp
<nav class="ul-subnav" aria-label="User Management">
    @foreach($items as $item)
        @can($item['can'])
            <a href="{{ route($item['route']) }}" class="ul-subnav-item {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])) ? 'is-active' : '' }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                {{ $item['label'] }}
            </a>
        @endcan
    @endforeach
</nav>
