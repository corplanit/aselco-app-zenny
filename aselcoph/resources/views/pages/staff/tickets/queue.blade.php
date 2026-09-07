<x-app-layout>
    <x-slot name="title">Ticket Queue</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets", "text": "Queue"}</x-slot>
    <x-slot name="active">Queue</x-slot>
    <x-slot name="buttons">
        @if(Auth::user()->canIntakeTickets())
            <a href="{{ route('tickets.intake') }}" class="ti-btn ti-btn-primary ti-btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Log Intake
            </a>
        @endif
        <a href="{{ route('tickets.escalations') }}" class="ti-btn ti-btn-outline-danger ti-btn-sm ms-1">Escalations</a>
        <a href="{{ route('workspace.department') }}#ticket-reports" class="ti-btn ti-btn-light ti-btn-sm ms-1">Reports</a>
    </x-slot>

    <x-unified.toolbar
        :action="route('tickets.queue')"
        :reset-url="route('tickets.queue')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search ticket no., customer, description…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="status" class="ti-form-select">
                    <option value="">Open (default)</option>
                    @foreach(\App\Support\TicketUi::statusLabels() as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Category</label>
                <select name="category_id" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int)($filters['category_id'] ?? 0) === $category->id)>
                            {{ $category->department_code }} ({{ \App\Support\TicketUi::categoryLabel($category) }})
                        </option>
                    @endforeach
                </select>
            </div>
            @if($wideView)
                <div>
                    <label class="ti-form-label">Department</label>
                    <select name="assigned_department" class="ti-form-select">
                        <option value="">All departments</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" @selected(($filters['assigned_department'] ?? '') === $dept)>{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="assigned_department" value="{{ Auth::user()->department_code }}">
                <div>
                    <label class="ti-form-label">Department</label>
                    <input type="text" class="ti-form-input" value="{{ Auth::user()->department_code }}" disabled>
                </div>
            @endif
            <div>
                <label class="ti-form-label">Priority</label>
                <select name="priority" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['low','normal','high','urgent'] as $priority)
                        <option value="{{ $priority }}" @selected(($filters['priority'] ?? '') === $priority)>{{ ucfirst($priority) }}</option>
                    @endforeach
                </select>
            </div>
            <x-unified.check name="overdue" value="1" :checked="!empty($filters['overdue'])">Overdue only</x-unified.check>
        </x-slot:filters>
        <x-slot:actions>
            <x-unified.sort-select
                :options="$sortable"
                :value="$filters['sort'] ?? 'id'"
                :dir="$filters['dir'] ?? 'desc'"
            />
        </x-slot:actions>
        <x-slot:footer>
            @unless($wideView)
                <p class="text-xs text-textmuted mt-3 mb-0">
                    You only see tickets assigned to <strong>{{ Auth::user()->department_code ?: 'you' }}</strong>.
                </p>
            @endunless
        </x-slot:footer>
    </x-unified.toolbar>

    <x-unified.table
        title="Ticket queue"
        :paginator="$tickets"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('tickets.queue')"
        empty-title="No tickets in this queue."
        :colspan="8"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <x-unified.th label="Ticket" column="ticket_no" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th>Customer</th>
            <th>Category</th>
            <x-unified.th label="Status" column="status" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <x-unified.th label="SLA" column="sla_due_at" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th>Assigned to</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach($tickets as $ticket)
            @php
                $overdue = $ticket->sla_due_at && $ticket->sla_due_at->isPast()
                    && !in_array($ticket->status, ['closed', 'resolved']);
                $href = route('tickets.show', $ticket->id);
            @endphp
            <x-unified.row :href="$href" @class(['bg-red-50' => $overdue])>
                <x-unified.td-num :paginator="$tickets" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$ticket->ticket_no">
                    <x-slot:meta>{{ ($ticket->assigned_department ?: 'Unassigned') }} · {{ $ticket->channel }}</x-slot:meta>
                </x-unified.td-primary>
                <td>
                    <div class="text-sm">{{ $ticket->customer?->name ?? '—' }}</div>
                    <div class="text-xs text-textmuted">{{ $ticket->customer?->email }}</div>
                </td>
                <td>
                    <x-unified.badge
                        :tone="\App\Support\TicketUi::categoryTone($ticket->category)"
                        :icon="\App\Support\TicketUi::categoryIcon($ticket->category)"
                    >{{ \App\Support\TicketUi::categoryLabel($ticket->category) }}</x-unified.badge>
                </td>
                <td>
                    <x-unified.badge
                        :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                        :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                    >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
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
                <td>
                    {{ $ticket->assignee?->name ?? 'Unassigned' }}
                </td>
                <x-unified.actions :view-url="$href">
                    <x-slot:menu>
                        <a href="{{ $href }}" role="menuitem"><i class="bi bi-eye"></i> View</a>
                        @if(Auth::user()->canSeeAllTicketDepartments() || Auth::user()->isTicketCsr())
                            <a href="{{ route('tickets.escalations') }}" role="menuitem"><i class="bi bi-exclamation-triangle"></i> Escalations</a>
                        @endif
                    </x-slot:menu>
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
