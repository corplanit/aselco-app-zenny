<x-app-layout>
    <x-slot name="title">Ticket reports</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets/reports", "text": "Reports"}</x-slot>
    <x-slot name="active">Analytics</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('tickets.reports', array_merge($filters, ['export' => 'csv'])) }}" class="ti-btn ti-btn-primary ti-btn-sm">
            Export CSV
        </a>
    </x-slot>

    <div class="box mb-4">
        <div class="box-body">
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div>
                    <label class="ti-form-label">From</label>
                    <input type="date" name="from" class="ti-form-input" value="{{ $filters['from'] ?? $analytics['from'] }}">
                </div>
                <div>
                    <label class="ti-form-label">To</label>
                    <input type="date" name="to" class="ti-form-input" value="{{ $filters['to'] ?? $analytics['to'] }}">
                </div>
                <button class="ti-btn ti-btn-light">Apply</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6 mb-6">
        @foreach([
            ['Tickets', $analytics['totals']['tickets'], 'primary'],
            ['Open', $analytics['totals']['open'], 'warning'],
            ['Closed', $analytics['totals']['closed'], 'success'],
            ['Escalation rate', $analytics['totals']['escalation_rate'].'%', 'danger'],
        ] as $card)
            <div class="xl:col-span-3 col-span-12">
                <div class="box">
                    <div class="box-body">
                        <div class="text-sm text-textmuted">{{ $card[0] }}</div>
                        <div class="text-2xl font-semibold">{{ $card[1] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="box ul-card mb-6">
        <div class="box-header ul-card-header">
            <div class="box-title ul-card-title">Department legend</div>
        </div>
        <div class="box-body">
            <div class="td-dept-meanings td-dept-meanings-grid">
                @foreach($departments as $dept)
                    <div class="ul-choice-row">
                        <span class="ul-choice-mark ul-badge is-{{ \App\Support\TicketUi::departmentTone($dept) }}">
                            <i class="bi {{ \App\Support\TicketUi::departmentIcon($dept) }}" aria-hidden="true"></i>
                        </span>
                        <span class="ul-choice-copy">
                            <span class="ul-choice-title">{{ $dept }}</span>
                            <span class="ul-choice-hint">{{ \App\Support\TicketUi::departmentMeaning($dept) ?: 'Ticket routing department' }}</span>
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6 mb-6">
        <div class="xl:col-span-7 col-span-12">
            <div class="box">
                <div class="box-header"><div class="box-title">Tickets by category</div></div>
                <div class="box-body"><canvas id="catChart" height="120"></canvas></div>
            </div>
        </div>
        <div class="xl:col-span-5 col-span-12">
            <div class="box">
                <div class="box-header"><div class="box-title">Avg resolution vs SLA (minutes)</div></div>
                <div class="box-body"><canvas id="slaChart" height="160"></canvas></div>
            </div>
        </div>
    </div>

    <div class="box ul-card mb-6">
        <div class="box-header ul-card-header"><div class="box-title ul-card-title">Category detail</div></div>
        <div class="box-body p-0">
            <div class="ul-table-wrap table-responsive">
                <table class="table ul-table mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Dept</th>
                            <th class="text-end">Tickets</th>
                            <th class="text-end">Closed</th>
                            <th class="text-end">Avg min</th>
                            <th class="text-end">SLA target</th>
                            <th class="text-end">Escalation %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($analytics['by_category'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['name'] }}</td>
                                <td><span class="ul-chip ul-chip-quiet">{{ $row['department_code'] }}</span></td>
                                <td class="text-end">{{ $row['ticket_count'] }}</td>
                                <td class="text-end">{{ $row['closed_count'] }}</td>
                                <td class="text-end">{{ $row['avg_resolution_minutes'] ?? '—' }}</td>
                                <td class="text-end">{{ $row['sla_minutes'] }}</td>
                                <td class="text-end">{{ $row['escalation_rate'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-7 col-span-12">
            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">Department performance</div></div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                    <table class="table ul-table mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-end">Tickets</th>
                                <th class="text-end">Closed</th>
                                <th class="text-end">Overdue</th>
                                <th class="text-end">Action minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($analytics['by_department'] as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['department'] }}</td>
                                    <td class="text-end">{{ $row['ticket_count'] }}</td>
                                    <td class="text-end">{{ $row['closed_count'] }}</td>
                                    <td class="text-end {{ $row['overdue_count'] > 0 ? 'text-danger font-semibold' : '' }}">{{ $row['overdue_count'] }}</td>
                                    <td class="text-end">{{ $row['action_minutes'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-unified.empty-state title="No data in this range." text="" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="xl:col-span-5 col-span-12">
            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">CSR / intake performance</div></div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                    <table class="table ul-table mb-0">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th class="text-end">Intake</th>
                                <th class="text-end">Verifications</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($analytics['by_csr'] as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['name'] }}</td>
                                    <td class="text-end">{{ $row['intake_count'] }}</td>
                                    <td class="text-end">{{ $row['verified_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3"><x-unified.empty-state title="No staff-logged tickets in range." text="" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
    const cats = @json($analytics['by_category']);
    new Chart(document.getElementById('catChart'), {
        type: 'bar',
        data: {
            labels: cats.map(c => c.department_code),
            datasets: [{ label: 'Tickets', data: cats.map(c => c.ticket_count), backgroundColor: 'rgba(93,102,247,0.45)' }]
        },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    new Chart(document.getElementById('slaChart'), {
        type: 'bar',
        data: {
            labels: cats.map(c => c.department_code),
            datasets: [
                { label: 'Avg resolution', data: cats.map(c => c.avg_resolution_minutes ?? 0), backgroundColor: 'rgba(16,185,129,0.45)' },
                { label: 'SLA target', data: cats.map(c => c.sla_minutes), backgroundColor: 'rgba(239,68,68,0.35)' }
            ]
        },
        options: { scales: { y: { beginAtZero: true } } }
    });
    </script>
    @endpush
</x-app-layout>
