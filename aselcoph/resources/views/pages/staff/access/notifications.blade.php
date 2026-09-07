<x-app-layout>
    <x-slot name="title">Notifications</x-slot>
    <x-slot name="active">Inbox</x-slot>

    <nav class="ul-subnav" aria-label="Notification filters">
        @foreach(['all' => 'All', 'unread' => 'Unread', 'read' => 'Read', 'assignment' => 'Assignment', 'escalation' => 'Escalation', 'system' => 'System'] as $key => $label)
            <a href="{{ route('workspace.notifications', ['filter' => $key]) }}" class="ul-subnav-item {{ $filter === $key ? 'is-active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    <x-unified.table title="Notifications" :paginator="$notifications" :colspan="4" empty-title="No notifications." empty-text="New assignment and system alerts will appear here.">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Notification</th>
            <th>Category</th>
            <th>When</th>
        </x-slot:head>
        @foreach($notifications as $note)
            @php
                $url = data_get($note->data, 'ticket_id') ? route('tickets.show', data_get($note->data, 'ticket_id')) : route('workspace.notifications');
            @endphp
            <x-unified.row :href="$url">
                <x-unified.td-num :paginator="$notifications" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$url" :text="$note->title">
                    <x-slot:meta>{{ $note->body }}</x-slot:meta>
                </x-unified.td-primary>
                <td>
                    <x-unified.badge tone="slate">{{ \App\Support\AccessUi::label($note->category) }}</x-unified.badge>
                </td>
                <td class="ul-date">{{ $note->created_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') }}</td>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
