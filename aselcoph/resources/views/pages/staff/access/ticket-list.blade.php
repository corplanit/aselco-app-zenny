<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="active">{{ $title }}</x-slot>

    <x-unified.toolbar :action="url()->current()" :reset-url="url()->current()" :search-value="$filters['search'] ?? ''" :active-filter-count="$activeFilterCount">
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="status" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['assigned','in_progress','awaiting_feedback','escalated','resolved','closed'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\TicketUi::statusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <x-unified.table :title="$title" :paginator="$tickets" :has-filters="$activeFilterCount > 0" :reset-url="url()->current()" :colspan="7">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Ticket</th>
            <th>Customer</th>
            <th>Status</th>
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
                    <div class="text-sm">{{ $ticket->customer?->name ?? '—' }}</div>
                    <div class="ul-date-meta">{{ $ticket->customer?->email }}</div>
                </td>
                <td>
                    <x-unified.badge
                        :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                        :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                    >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                </td>
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
