<x-app-layout>
    <x-slot name="title">User Sessions</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Sessions</x-slot>
    @include('pages.staff.access._nav')

    <x-unified.table title="Active sessions" :paginator="$sessions" :colspan="6" empty-title="No active sessions." empty-text="Signed-in accounts will appear here.">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>User</th>
            <th>IP</th>
            <th>Browser</th>
            <th>Last activity</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>
        @foreach($sessions as $session)
            <tr>
                <x-unified.td-num :paginator="$sessions" :iteration="$loop->iteration" />
                <td>
                    <div class="font-semibold">{{ $session->name }}</div>
                    <div class="ul-date-meta">{{ $session->email }}</div>
                </td>
                <td>{{ $session->ip_address }}</td>
                <td>
                    <div class="text-sm">{{ \Illuminate\Support\Str::limit($session->user_agent, 48) }}</div>
                </td>
                <td class="ul-date">{{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                <td class="text-end ul-col-actions">
                    @can('sessions.revoke')
                        <form
                            method="POST"
                            action="{{ route('access.sessions.revoke', $session->id) }}"
                            data-ul-confirm="Revoke this signed-in session."
                            data-ul-confirm-verb="Revoke"
                            data-ul-confirm-tone="danger"
                            data-ul-confirm-icon="bi-x-lg"
                        >
                            @csrf
                            <button class="ti-btn ti-btn-sm ul-btn ul-btn-danger" title="Revoke">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-unified.table>
</x-app-layout>
