<x-app-layout>
    <x-slot name="title">Ticket Assignments</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Assignments</x-slot>
    @include('pages.staff.access._nav')

    <x-unified.toolbar :action="route('access.assignments.index')" :reset-url="route('access.assignments.index')" :search-value="$filters['search'] ?? ''" :active-filter-count="$activeFilterCount">
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Department</label>
                <select name="assigned_department" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($departments as $dept)
                        <option
                            value="{{ $dept->code }}"
                            @selected(($filters['assigned_department'] ?? '') === $dept->code)
                            data-tone="{{ \App\Support\TicketUi::departmentTone($dept->code) }}"
                            data-icon="{{ \App\Support\TicketUi::departmentIcon($dept->code) }}"
                        >{{ \App\Support\AccessUi::departmentOptionLabel($dept->code, $dept->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Assignee</label>
                <select name="assigned_to" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($assignees as $person)
                        <option value="{{ $person->id }}" @selected(($filters['assigned_to'] ?? '') == $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <x-unified.table title="Assignments" :paginator="$tickets" :has-filters="$activeFilterCount > 0" :reset-url="route('access.assignments.index')" :colspan="8">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Ticket</th>
            <th>Category</th>
            <th>Department</th>
            <th>Assigned to</th>
            <th>Priority</th>
            <th>SLA</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>
        @foreach($tickets as $ticket)
            @php
                $href = route('tickets.show', $ticket->id);
                $overdue = $ticket->sla_due_at && $ticket->sla_due_at->isPast()
                    && !in_array($ticket->status, ['closed', 'resolved']);
            @endphp
            <x-unified.row :href="$href">
                <x-unified.td-num :paginator="$tickets" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$ticket->ticket_no" />
                <td>
                    <x-unified.badge
                        :tone="\App\Support\TicketUi::categoryTone($ticket->category)"
                        :icon="\App\Support\TicketUi::categoryIcon($ticket->category)"
                    >{{ \App\Support\TicketUi::categoryLabel($ticket->category) }}</x-unified.badge>
                </td>
                <td>
                    @if($ticket->assigned_department)
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($ticket->assigned_department)"
                            :icon="\App\Support\TicketUi::departmentIcon($ticket->assigned_department)"
                        >{{ $ticket->assigned_department }}</x-unified.badge>
                        <div class="ul-date-meta">{{ \App\Support\TicketUi::departmentMeaning($ticket->assigned_department) }}</div>
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <td>{{ $ticket->assignee?->name ?: 'Unassigned' }}</td>
                <td>
                    <x-unified.badge :tone="\App\Support\TicketUi::priorityTone($ticket->priority)">
                        {{ ucfirst($ticket->priority ?: 'normal') }}
                    </x-unified.badge>
                </td>
                <td class="ul-date">
                    @if($ticket->sla_due_at)
                        @if($overdue)
                            <x-unified.badge tone="danger" icon="bi-exclamation-octagon">Overdue</x-unified.badge>
                        @else
                            {{ $ticket->sla_due_at->diffForHumans() }}
                        @endif
                        <div class="ul-date-meta">{{ $ticket->sla_due_at->timezone('Asia/Manila')->format('M d, h:i A') }}</div>
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <x-unified.actions :view-url="$href" />
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
