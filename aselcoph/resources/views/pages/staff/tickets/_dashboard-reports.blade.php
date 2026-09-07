@php
    $rangeFrom = $filters['from'] ?? $analytics['from'];
    $rangeTo = $filters['to'] ?? $analytics['to'];
    $rangeLabel = \Illuminate\Support\Carbon::parse($analytics['from'])->timezone('Asia/Manila')->format('M d, Y')
        .' – '.\Illuminate\Support\Carbon::parse($analytics['to'])->timezone('Asia/Manila')->format('M d, Y');
    $reportTiles = [
        ['Tickets', $analytics['totals']['tickets'], 'bi-ticket-detailed', 'is-slate'],
        ['Open', $analytics['totals']['open'], 'bi-inbox', 'is-sky'],
        ['Closed', $analytics['totals']['closed'], 'bi-check-circle', 'is-green'],
        ['Escalation rate', $analytics['totals']['escalation_rate'].'%', 'bi-signpost-split', 'is-amber'],
    ];
@endphp

<section class="ul-report" id="ticket-reports" aria-labelledby="ticket-reports-title">
    <div class="col-span-12">
        <div class="box ul-card">
            <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-2">
                <div class="box-title ul-card-title mb-0" id="ticket-reports-title">
                    Reports
                    <span class="ul-card-count">{{ $rangeLabel }}</span>
                </div>
                <div class="ul-card-header-actions">
                    <a href="{{ route('tickets.reports', array_filter(['from' => $rangeFrom, 'to' => $rangeTo, 'export' => 'csv'])) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-primary">
                        <i class="bi bi-download"></i>Export CSV
                    </a>
                </div>
            </div>
            <div class="ul-card-footer ul-report-bar">
                <form method="GET" action="{{ route('workspace.department') }}" class="ul-report-filter">
                    <div class="ul-field">
                        <label class="ti-form-label" for="report-from">From</label>
                        <input type="date" id="report-from" name="from" class="ti-form-input" value="{{ $rangeFrom }}">
                    </div>
                    <div class="ul-field">
                        <label class="ti-form-label" for="report-to">To</label>
                        <input type="date" id="report-to" name="to" class="ti-form-input" value="{{ $rangeTo }}">
                    </div>
                    <button class="ti-btn ti-btn-sm ul-btn ul-btn-view" type="submit">
                        <i class="bi bi-funnel"></i>Apply
                    </button>
                    <a href="{{ route('workspace.department') }}#ticket-reports" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">Reset</a>
                </form>
                <p class="ul-report-scope">{{ $scope }}</p>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="ul-kpi-grid is-four">
            @foreach($reportTiles as $tile)
                <div class="ul-kpi {{ $tile[3] }}">
                    <span class="ul-kpi-icon"><i class="bi {{ $tile[2] }}"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ is_numeric($tile[1]) ? number_format($tile[1]) : $tile[1] }}</span>
                        <span class="ul-kpi-label">{{ $tile[0] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="xl:col-span-7 col-span-12 ul-chart-card-wrap">
        <div class="box ul-card ul-chart-card">
            <div class="box-header ul-card-header">
                <div class="box-title ul-card-title">Tickets by category</div>
            </div>
            <div class="box-body">
                <canvas id="catChart" height="140"></canvas>
            </div>
        </div>
    </div>

    <div class="xl:col-span-5 col-span-12 ul-chart-card-wrap">
        <div class="box ul-card ul-chart-card">
            <div class="box-header ul-card-header">
                <div class="box-title ul-card-title">Avg resolution vs SLA</div>
            </div>
            <div class="box-body">
                <canvas id="slaChart" height="140"></canvas>
            </div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="box ul-card">
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
    </div>

    <div class="col-span-12">
        <x-unified.table
            class="ul-report-table"
            title="Category detail"
            :items="$analytics['by_category']"
            :colspan="8"
            empty-title="No tickets in this range."
            empty-text="Try a wider date range."
        >
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Category</th>
                <th>Dept</th>
                <th class="text-end">Tickets</th>
                <th class="text-end">Closed</th>
                <th class="text-end">Avg min</th>
                <th class="text-end">SLA</th>
                <th class="text-end">Escalation</th>
            </x-slot:head>
            @foreach($analytics['by_category'] as $row)
                <tr>
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <td>{{ \App\Support\TicketUi::categoryLabels()[$row['department_code']] ?? $row['name'] }}</td>
                    <td>
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($row['department_code'])"
                            :icon="\App\Support\TicketUi::departmentIcon($row['department_code'])"
                        >{{ $row['department_code'] }}</x-unified.badge>
                    </td>
                    <td class="text-end">{{ number_format($row['ticket_count']) }}</td>
                    <td class="text-end">{{ number_format($row['closed_count']) }}</td>
                    <td class="text-end">{{ $row['avg_resolution_minutes'] ?? '—' }}</td>
                    <td class="text-end">{{ $row['sla_minutes'] }}</td>
                    <td class="text-end">
                        <x-unified.badge :tone="$row['escalation_rate'] > 0 ? 'amber' : 'slate'">{{ $row['escalation_rate'] }}%</x-unified.badge>
                    </td>
                </tr>
            @endforeach
        </x-unified.table>
    </div>

    <div class="xl:col-span-7 col-span-12">
        <x-unified.table
            class="ul-report-table"
            title="Department performance"
            :items="$analytics['by_department']"
            :colspan="6"
            empty-title="No department data in this range."
            empty-text="Tickets in the selected period will appear here."
        >
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Department</th>
                <th class="text-end">Tickets</th>
                <th class="text-end">Closed</th>
                <th class="text-end">Overdue</th>
                <th class="text-end">Action min</th>
            </x-slot:head>
            @foreach($analytics['by_department'] as $row)
                <tr>
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <td>
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($row['department'])"
                            :icon="\App\Support\TicketUi::departmentIcon($row['department'])"
                        >{{ $row['department'] }}</x-unified.badge>
                    </td>
                    <td class="text-end">{{ number_format($row['ticket_count']) }}</td>
                    <td class="text-end">{{ number_format($row['closed_count']) }}</td>
                    <td class="text-end">
                        @if($row['overdue_count'] > 0)
                            <x-unified.badge tone="danger" icon="bi-exclamation-octagon">{{ number_format($row['overdue_count']) }}</x-unified.badge>
                        @else
                            {{ number_format($row['overdue_count']) }}
                        @endif
                    </td>
                    <td class="text-end">{{ number_format($row['action_minutes']) }}</td>
                </tr>
            @endforeach
        </x-unified.table>
    </div>

    <div class="xl:col-span-5 col-span-12">
        <x-unified.table
            class="ul-report-table"
            title="CSR / intake"
            :items="$analytics['by_csr']"
            :colspan="4"
            empty-title="No staff-logged tickets in this range."
            empty-text="Intake tickets created by staff will appear here."
        >
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Staff</th>
                <th class="text-end">Intake</th>
                <th class="text-end">Verified</th>
            </x-slot:head>
            @foreach($analytics['by_csr'] as $row)
                <tr>
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-end">{{ number_format($row['intake_count']) }}</td>
                    <td class="text-end">{{ number_format($row['verified_count']) }}</td>
                </tr>
            @endforeach
        </x-unified.table>
    </div>
</section>
