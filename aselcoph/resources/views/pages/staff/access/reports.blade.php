<x-app-layout>
    <x-slot name="title">User Management Reports</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Reports</x-slot>

    @include('pages.staff.access._nav')

    @php
        $reportTypes = [
            'active_users' => 'Active users',
            'inactive_users' => 'Inactive users',
            'staff_by_department' => 'Staff by department',
            'tickets_per_account' => 'Tickets per account',
            'tickets_per_department' => 'Tickets per department',
            'login_activity' => 'Login activity',
            'assignment_methods' => 'Assignment methods',
        ];
        $columns = $rows !== [] ? array_keys($rows[0]) : [];
        $colspan = max(count($columns) + 1, 2);
    @endphp

    <x-unified.toolbar
        :action="route('access.reports.index')"
        :reset-url="route('access.reports.index', ['type' => $type])"
        :show-filter="false"
        :show-reset="false"
        search-placeholder="Filter loaded rows…"
        :local="true"
    >
        <x-slot:actions>
            <label class="ti-form-label mb-0 me-1" for="access-report-type">Type</label>
            <select
                id="access-report-type"
                class="ti-form-select"
                style="min-width: 15rem;"
                onchange="window.location.href = @js(route('access.reports.index')).replace(/\/?$/, '') + '?type=' + encodeURIComponent(this.value)"
            >
                @foreach($reportTypes as $key => $label)
                    <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @can('reports.export')
                <a class="ti-btn ti-btn-light ti-btn-sm" href="{{ route('access.reports.index', ['type' => $type, 'export' => 1]) }}">
                    <i class="bi bi-download me-1" aria-hidden="true"></i>
                    Export CSV
                </a>
            @endcan
        </x-slot:actions>
    </x-unified.toolbar>

    <x-unified.table
        :title="$reportTypes[$type] ?? 'Report'"
        :items="$rows"
        :colspan="$colspan"
        empty-title="No rows."
        empty-text="Choose another report type."
        :fit="true"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            @foreach($columns as $col)
                <th>{{ \App\Support\AccessUi::label($col) }}</th>
            @endforeach
        </x-slot:head>
        @foreach($rows as $row)
            <tr class="ul-row">
                <td class="ul-row-num">{{ $loop->iteration }}</td>
                @foreach($row as $key => $cell)
                    <td>
                        @if(is_array($cell))
                            {{ json_encode($cell) }}
                        @elseif(in_array($key, ['account_status', 'status'], true))
                            <x-unified.badge :tone="\App\Support\AccessUi::accountStatusTone((string) $cell)">
                                {{ \App\Support\AccessUi::accountStatusLabel((string) $cell) }}
                            </x-unified.badge>
                        @elseif($key === 'user_type')
                            <x-unified.badge :tone="\App\Support\AccessUi::userTypeTone((string) $cell)">
                                {{ \App\Support\AccessUi::userTypeLabel((string) $cell) }}
                            </x-unified.badge>
                        @elseif(str_contains((string) $key, 'department') && filled($cell) && ! is_numeric($cell))
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::departmentTone((string) $cell)"
                                :icon="\App\Support\TicketUi::departmentIcon((string) $cell)"
                            >{{ $cell }}</x-unified.badge>
                        @else
                            {{ $cell === null || $cell === '' ? '—' : $cell }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </x-unified.table>
</x-app-layout>
