<x-app-layout>
    <x-slot name="title">User Activity</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Activity</x-slot>
    @include('pages.staff.access._nav')

    <x-unified.toolbar :action="route('access.activity.index')" :reset-url="route('access.activity.index')" :search-value="$filters['search'] ?? ''" :active-filter-count="$activeFilterCount">
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Action</label>
                <input name="action" class="ti-form-input" value="{{ $filters['action'] ?? '' }}" placeholder="login, update…">
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <x-unified.table title="Audit trail" :paginator="$logs" :has-filters="$activeFilterCount > 0" :reset-url="route('access.activity.index')" :colspan="6">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>When</th>
            <th>User</th>
            <th>Action</th>
            <th>Target</th>
            <th>IP</th>
        </x-slot:head>
        @foreach($logs as $log)
            <tr>
                <x-unified.td-num :paginator="$logs" :iteration="$loop->iteration" />
                <td class="ul-date">{{ $log->created_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') }}</td>
                <td>{{ $log->user?->name ?: '—' }}</td>
                <td>
                    <x-unified.badge tone="slate">{{ \App\Support\AccessUi::label($log->action) }}</x-unified.badge>
                </td>
                <td>{{ $log->target_type ? class_basename($log->target_type).'#'.$log->target_id : '—' }}</td>
                <td>{{ $log->ip_address }}</td>
            </tr>
        @endforeach
    </x-unified.table>
</x-app-layout>
