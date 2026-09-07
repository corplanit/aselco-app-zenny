<x-app-layout>
    <x-slot name="title">Dashboard Ticket</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/workspace/department-queue", "text": "Dashboard"}</x-slot>
    <x-slot name="active">Dashboard Ticket</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-ticket-detailed"></i>Ticket Queue
        </a>
        <a href="{{ route('workspace.tickets') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-person-lines-fill"></i>Assigned Ticket
        </a>
        <a href="#ticket-reports" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
            <i class="bi bi-bar-chart"></i>Reports
        </a>
    </x-slot>

    @php
        $tiles = [
            'unassigned' => ['Unassigned', 'bi-person-x', 'is-slate'],
            'assigned' => ['Assigned', 'bi-person-check', 'is-green'],
            'in_progress' => ['In progress', 'bi-play-circle', 'is-sky'],
            'awaiting' => ['Awaiting feedback', 'bi-chat-dots', 'is-cyan'],
            'escalated' => ['Escalated', 'bi-signpost-split', 'is-amber'],
            'overdue' => ['Overdue SLA', 'bi-exclamation-octagon', 'is-amber'],
        ];
    @endphp

    <div class="grid grid-cols-12 items-stretch ul-ast-dash mb-6">
        <section class="xl:col-span-8 col-span-12 ul-ast-card ul-ast-card-with-logo is-ticket" aria-label="Open tickets">
            <div class="ul-ast-card-main">
                <div class="ul-ast-card-top">
                    <span class="ul-ast-card-badge">
                        <i class="bi bi-ticket-detailed" aria-hidden="true"></i>
                        Tickets
                    </span>
                </div>
                <p class="ul-ast-card-label">Open tickets</p>
                <p class="ul-ast-card-amount">{{ number_format($stats['open'] ?? 0) }}</p>
                <p class="ul-ast-card-meta">{{ $scope }} · {{ number_format($stats['unassigned'] ?? 0) }} unassigned · {{ number_format($stats['overdue'] ?? 0) }} overdue SLA</p>
                <div class="ul-ast-card-actions">
                    <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('tickets.queue') }}">
                        <i class="bi bi-ticket-detailed"></i>Ticket Queue
                    </a>
                    <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('workspace.tickets') }}">
                        <i class="bi bi-person-lines-fill"></i>Assigned Ticket
                    </a>
                    <a class="ti-btn ti-btn-sm ul-btn" href="{{ route('tickets.escalations') }}">
                        <i class="bi bi-signpost-split"></i>Escalations
                    </a>
                </div>
            </div>
            <img
                class="ul-ast-card-logo"
                src="{{ asset('assets/logo_tag.png') }}"
                alt=""
                aria-hidden="true"
            >
        </section>

        <div class="xl:col-span-4 col-span-12 ul-qa-card-wrap">
            <div class="box ul-card ul-qa-card" id="ticketDashActions">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">Quick actions</div></div>
                <div class="ul-qa-grid">
                    <a class="ul-qa" href="{{ route('tickets.queue') }}">
                        <span class="ul-qa-icon"><i class="bi bi-ticket-detailed"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Ticket Queue</span>
                            <span class="ul-qa-hint">{{ number_format($stats['open'] ?? 0) }} open in the queue</span>
                        </span>
                    </a>
                    <a class="ul-qa is-slate" href="{{ route('workspace.tickets') }}">
                        <span class="ul-qa-icon"><i class="bi bi-person-lines-fill"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Assigned Ticket</span>
                            <span class="ul-qa-hint">Tickets assigned to you</span>
                        </span>
                    </a>
                    <a class="ul-qa is-orange" href="{{ route('tickets.escalations') }}">
                        <span class="ul-qa-icon"><i class="bi bi-signpost-split"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Escalations</span>
                            <span class="ul-qa-hint">{{ number_format($stats['escalated'] ?? 0) }} escalated now</span>
                        </span>
                    </a>
                    <a class="ul-qa is-amber" href="{{ route('workspace.sla') }}">
                        <span class="ul-qa-icon"><i class="bi bi-hourglass-split"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">SLA Monitoring</span>
                            <span class="ul-qa-hint">{{ number_format($stats['overdue'] ?? 0) }} overdue</span>
                        </span>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-span-12 ul-ast-dash-kpis">
            <div class="ul-kpi-grid is-wide">
                @foreach($tiles as $key => $tile)
                    <div class="ul-kpi {{ $tile[2] }}">
                        <span class="ul-kpi-icon"><i class="bi {{ $tile[1] }}"></i></span>
                        <span class="ul-kpi-copy">
                            <span class="ul-kpi-value">{{ number_format($stats[$key] ?? 0) }}</span>
                            <span class="ul-kpi-label">{{ $tile[0] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid {{ $staff->isNotEmpty() ? 'md:grid-cols-2' : '' }} gap-6">
        <x-unified.table
            title="Recent open tickets"
            :items="$recent"
            :colspan="5"
            empty-title="No open tickets."
            empty-text="New department tickets will appear here."
        >
            <x-slot:headerActions>
                <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">View queue</a>
            </x-slot:headerActions>
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Ticket</th>
                <th>Status</th>
                <th>Assignee</th>
                <th class="text-end ul-col-actions">Actions</th>
            </x-slot:head>
            @foreach($recent as $ticket)
                @php $href = route('tickets.show', $ticket->id); @endphp
                <x-unified.row :href="$href">
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <x-unified.td-primary :href="$href" :text="$ticket->ticket_no">
                        <x-slot:meta>{{ $ticket->customer?->name ?? '—' }}</x-slot:meta>
                    </x-unified.td-primary>
                    <td>
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                            :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                        >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                    </td>
                    <td>{{ $ticket->assignee?->name ?? '—' }}</td>
                    <x-unified.actions :view-url="$href" />
                </x-unified.row>
            @endforeach
        </x-unified.table>

        @if($staff->isNotEmpty())
            <x-unified.table
                title="Staff workload"
                :items="$staff"
                :colspan="3"
                empty-title="No staff in this department."
                empty-text="Assign support accounts to see workload."
            >
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Staff</th>
                    <th>Open tickets</th>
                </x-slot:head>
                @foreach($staff as $person)
                    @php $href = Auth::user()->canAccess('users.view') ? route('access.support.show', $person) : null; @endphp
                    <x-unified.row :href="$href">
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        @if($href)
                            <x-unified.td-primary :href="$href" :text="$person->name" />
                        @else
                            <td>{{ $person->name }}</td>
                        @endif
                        <td>
                            @if($person->open_tickets > 0)
                                <x-unified.badge tone="amber">{{ $person->open_tickets }} open</x-unified.badge>
                            @else
                                <x-unified.badge tone="lime">0 open</x-unified.badge>
                            @endif
                        </td>
                    </x-unified.row>
                @endforeach
            </x-unified.table>
        @endif
    </div>

    @isset($analytics)
        @include('pages.staff.tickets._dashboard-reports')
    @endisset

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        const hero = document.querySelector('.ul-ast-card-with-logo');
        const actions = document.getElementById('ticketDashActions');
        function matchHeight() {
            if (!hero || !actions) {
                return;
            }
            hero.style.height = '';
            actions.style.height = '';
            if (window.innerWidth < 1280) {
                return;
            }
            const height = Math.max(hero.offsetHeight, actions.offsetHeight);
            hero.style.height = height + 'px';
            actions.style.height = height + 'px';
        }
        window.addEventListener('resize', matchHeight);
        window.addEventListener('load', matchHeight);
        requestAnimationFrame(matchHeight);

        const cats = @json($analytics['by_category'] ?? []);
        const catEl = document.getElementById('catChart');
        const slaEl = document.getElementById('slaChart');
        if (catEl && slaEl && cats.length) {
            const axis = {
                beginAtZero: true,
                grid: { color: 'rgba(148, 163, 184, 0.16)' },
                ticks: { color: '#64748b', font: { size: 11 } }
            };
            new Chart(catEl, {
                type: 'bar',
                data: {
                    labels: cats.map((c) => c.department_code),
                    datasets: [{ label: 'Tickets', data: cats.map((c) => c.ticket_count), backgroundColor: 'rgba(14, 165, 233, 0.45)', borderRadius: 6 }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: axis, y: axis }
                }
            });
            new Chart(slaEl, {
                type: 'bar',
                data: {
                    labels: cats.map((c) => c.department_code),
                    datasets: [
                        { label: 'Avg resolution', data: cats.map((c) => c.avg_resolution_minutes ?? 0), backgroundColor: 'rgba(99, 102, 241, 0.45)', borderRadius: 6 },
                        { label: 'SLA target', data: cats.map((c) => c.sla_minutes), backgroundColor: 'rgba(245, 158, 11, 0.4)', borderRadius: 6 }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } },
                    scales: { x: axis, y: axis }
                }
            });
        }
    })();
    </script>
    @endpush
</x-app-layout>
