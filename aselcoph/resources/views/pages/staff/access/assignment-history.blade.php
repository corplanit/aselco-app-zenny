<x-app-layout>
    <x-slot name="title">Assignment history</x-slot>
    <x-slot name="url_1">{"link": "{{ route('tickets.show', $ticket->id) }}", "text": "{{ $ticket->ticket_no }}"}</x-slot>
    <x-slot name="active">History</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('tickets.show', $ticket->id) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>{{ $ticket->ticket_no }}
        </a>
    </x-slot>

    <x-unified.table title="Assignment history" :items="$history" :colspan="7" empty-title="No assignment history." empty-text="Endorse, reassign, and escalate events will appear here.">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>When</th>
            <th>From</th>
            <th>To</th>
            <th>Method</th>
            <th>By</th>
            <th>Reason</th>
        </x-slot:head>
        @foreach($history as $row)
            <tr>
                <td class="ul-row-num">{{ $loop->iteration }}</td>
                <td class="ul-date">{{ $row->created_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') }}</td>
                <td>
                    <div class="font-semibold">{{ $row->previous_department ?: '—' }}</div>
                    <div class="ul-date-meta">{{ $row->previousAssignee?->name ?: '—' }}</div>
                </td>
                <td>
                    <div class="font-semibold">{{ $row->new_department ?: '—' }}</div>
                    <div class="ul-date-meta">{{ $row->newAssignee?->name ?: '—' }}</div>
                </td>
                <td>
                    <x-unified.badge tone="slate">{{ \App\Support\AccessUi::label($row->method) }}</x-unified.badge>
                </td>
                <td>{{ $row->assignedBy?->name ?: '—' }}</td>
                <td>{{ $row->reason ?: '—' }}</td>
            </tr>
        @endforeach
    </x-unified.table>
</x-app-layout>
