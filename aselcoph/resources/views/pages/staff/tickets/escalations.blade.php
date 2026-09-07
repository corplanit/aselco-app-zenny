<x-app-layout>
    <x-slot name="title">Escalated tickets</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets/escalations", "text": "Escalations"}</x-slot>
    <x-slot name="active">Open escalations</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('workspace.department') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-ticket-detailed"></i>Ticket Queue
        </a>
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger mb-4">{{ session('error') }}</div> @endif

    <div class="ul-stat-grid mb-6">
        <div class="ul-stat">
            <div class="ul-stat-label"><i class="bi bi-signpost-split"></i> Open escalations</div>
            <div class="ul-stat-value">{{ number_format($stats['open']) }}</div>
        </div>
        <div class="ul-stat">
            <div class="ul-stat-label"><i class="bi bi-person-x"></i> Unassigned</div>
            <div class="ul-stat-value">{{ number_format($stats['unassigned']) }}</div>
        </div>
        <div class="ul-stat">
            <div class="ul-stat-label"><i class="bi bi-exclamation-octagon"></i> Overdue SLA</div>
            <div class="ul-stat-value">{{ number_format($stats['overdue']) }}</div>
        </div>
    </div>

    <x-unified.toolbar
        :action="route('tickets.escalations')"
        :reset-url="route('tickets.escalations')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search ticket no., customer, reason…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Escalated to</label>
                <select name="escalated_to" class="ti-form-select">
                    <option value="">All departments</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" @selected(($filters['escalated_to'] ?? '') === $dept)>{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
        <x-slot:actions>
            <x-unified.sort-select
                :options="['id' => 'Newest', 'escalated_at' => 'Escalated']"
                :value="$filters['sort'] ?? 'id'"
                :dir="$filters['dir'] ?? 'desc'"
            />
        </x-slot:actions>
    </x-unified.toolbar>

    <x-unified.table
        class="ul-esc-table"
        title="Open escalations"
        :paginator="$escalations"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('tickets.escalations')"
        empty-title="No open escalations."
        empty-text="Tickets sent to a second-tier department will appear here until they are resolved."
        :colspan="8"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Ticket</th>
            <th>Customer</th>
            <th>Route</th>
            <th>Reason</th>
            <x-unified.th label="Escalated" column="escalated_at" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th>Assigned</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach($escalations as $row)
            @php
                $ticket = $row->ticket;
                $href = $ticket ? route('tickets.show', $ticket->id) : null;
                $overdue = $ticket?->sla_due_at
                    && $ticket->sla_due_at->isPast()
                    && ! in_array($ticket->status, ['closed', 'resolved'], true);
                $reasonPlain = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $row->reason), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            @endphp
            <x-unified.row :href="$href">
                <x-unified.td-num :paginator="$escalations" :iteration="$loop->iteration" />
                <td>
                    @if($ticket)
                        <a href="{{ $href }}" class="ul-primary-link ul-no-row-click">{{ $ticket->ticket_no }}</a>
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <td>
                    <span class="ul-clip" title="{{ $ticket?->customer?->name }}">{{ $ticket?->customer?->name ?: '—' }}</span>
                </td>
                <td>
                    <div class="esc-route">
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($row->escalated_from)"
                            :icon="\App\Support\TicketUi::departmentIcon($row->escalated_from)"
                        >{{ $row->escalated_from ?: '—' }}</x-unified.badge>
                        <i class="bi bi-arrow-right esc-route-arrow" aria-hidden="true"></i>
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($row->escalated_to)"
                            :icon="\App\Support\TicketUi::departmentIcon($row->escalated_to)"
                        >{{ $row->escalated_to }}</x-unified.badge>
                    </div>
                </td>
                <td class="ul-esc-reason">
                    <span class="ul-clip" @if($reasonPlain !== '') title="{{ $reasonPlain }}" @endif>{{ $reasonPlain !== '' ? $reasonPlain : '—' }}</span>
                </td>
                <td class="ul-date">
                    {{ optional($row->escalated_at)->timezone('Asia/Manila')->format('M d, Y · h:i A') ?: '—' }}
                </td>
                <td>
                    @if($ticket)
                        <span class="esc-assigned">
                            <span class="ul-clip" title="{{ $ticket->assignee?->name }}">{{ $ticket->assignee?->name ?: 'Unassigned' }}</span>
                            @if($overdue)
                                <x-unified.badge tone="danger" icon="bi-exclamation-octagon">Overdue</x-unified.badge>
                            @endif
                        </span>
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <x-unified.actions :view-url="$href">
                    @if($canAssign && $ticket)
                        <button
                            type="button"
                            class="ti-btn ti-btn-sm ul-btn-view"
                            data-ul-modal="#esc-assign-modal"
                            data-esc-assign
                            data-action="{{ route('tickets.reassign', $ticket->id) }}"
                            data-dept="{{ $ticket->assigned_department ?: $row->escalated_to }}"
                            data-assigned="{{ $ticket->assigned_to }}"
                            title="Assign"
                            aria-label="Assign"
                        >
                            <i class="bi bi-person-check" aria-hidden="true"></i>
                        </button>
                    @endif
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>

    @if($canAssign)
        <div id="esc-assign-modal" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="esc-assign-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-person-check" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="esc-assign-title">Assign escalated ticket</h6>
                        <p>Send this ticket to the handling department and support account.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="" id="esc-assign-form" class="ul-modal-body">
                    @csrf
                    <label class="ti-form-label">Department</label>
                    <select name="assigned_department" class="ti-form-select mb-3" required>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}">{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Filter support by role</label>
                    <select name="assignee_role_filter" class="ti-form-select mb-3">
                        <option value="">All support roles</option>
                        @foreach($assigneeRoles as $role)
                            <option value="{{ $role }}">{{ \App\Support\TicketUi::roleOptionLabel($role) }}</option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Assign to</label>
                    <select name="assigned_to" class="ti-form-select mb-3">
                        <option value="">Auto (round-robin in department)</option>
                        @foreach($assignees as $person)
                            <option
                                value="{{ $person->id }}"
                                data-dept="{{ $person->department_code }}"
                                data-role="{{ $person->role }}"
                            >
                                {{ $person->name }}
                                · {{ $person->role ?: 'staff' }}
                                · {{ $person->department_code ?: 'No dept' }}
                            </option>
                        @endforeach
                    </select>
                    <label class="ti-form-label">Remarks</label>
                    <input type="text" name="remarks" class="ti-form-input mb-3" placeholder="Optional assignment note">
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-person-check"></i>Assign / reassign
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('esc-assign-form');
                if (!form) return;
                const deptSelect = form.querySelector('[name="assigned_department"]');
                const roleSelect = form.querySelector('[name="assignee_role_filter"]');
                const assigneeSelect = form.querySelector('[name="assigned_to"]');

                const applyFilter = () => {
                    const dept = deptSelect.value;
                    const role = roleSelect.value;
                    assigneeSelect.querySelectorAll('option[data-role]').forEach((opt) => {
                        const personDept = opt.dataset.dept || '';
                        const matchRole = !role || opt.dataset.role === role;
                        const matchDept = !dept || personDept === dept || personDept === '';
                        opt.hidden = !(matchRole && matchDept);
                    });
                    const selected = assigneeSelect.options[assigneeSelect.selectedIndex];
                    if (selected && selected.hidden) {
                        assigneeSelect.value = '';
                    }
                };

                deptSelect.addEventListener('change', applyFilter);
                roleSelect.addEventListener('change', applyFilter);

                document.querySelectorAll('[data-esc-assign]').forEach((button) => {
                    button.addEventListener('click', () => {
                        form.action = button.dataset.action || '';
                        deptSelect.value = button.dataset.dept || deptSelect.options[0]?.value || '';
                        assigneeSelect.value = button.dataset.assigned || '';
                        applyFilter();
                    });
                });

                applyFilter();
            });
        </script>
    @endif
</x-app-layout>
