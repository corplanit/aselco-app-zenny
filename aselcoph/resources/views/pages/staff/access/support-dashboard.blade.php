<x-app-layout>
    <x-slot name="title">Support Dashboard</x-slot>
    <x-slot name="active">My workspace</x-slot>

    @php
        $tiles = [
            'open' => ['My open', 'bi-inbox'],
            'new_assignments' => ['New assignments', 'bi-person-plus'],
            'near_sla' => ['Near SLA', 'bi-hourglass'],
            'breached' => ['SLA breaches', 'bi-exclamation-octagon'],
            'awaiting' => ['Awaiting feedback', 'bi-chat-dots'],
            'escalated' => ['Escalated', 'bi-signpost-split'],
            'completed_today' => ['Completed today', 'bi-check-circle'],
        ];
    @endphp

    <div class="ul-stat-grid mb-6">
        @foreach($tiles as $key => $tile)
            <div class="ul-stat">
                <div class="ul-stat-label"><i class="bi {{ $tile[1] }}"></i> {{ $tile[0] }}</div>
                <div class="ul-stat-value">{{ $stats[$key] ?? 0 }}</div>
            </div>
        @endforeach
    </div>

    <x-unified.table title="Recent notifications" :items="$notifications" :colspan="3" empty-title="No notifications." empty-text="Assignment and SLA alerts will appear here.">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Notification</th>
            <th>When</th>
        </x-slot:head>
        @foreach($notifications as $note)
            @php $href = data_get($note->data, 'url', route('workspace.notifications')); @endphp
            <x-unified.row :href="$href">
                <td class="ul-row-num">{{ $loop->iteration }}</td>
                <x-unified.td-primary :href="$href" :text="$note->title">
                    <x-slot:meta>{{ $note->body ?? '' }}</x-slot:meta>
                </x-unified.td-primary>
                <td class="ul-date">{{ $note->created_at?->diffForHumans() }}</td>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
