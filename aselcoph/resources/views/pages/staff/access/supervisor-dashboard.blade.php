<x-app-layout>
    <x-slot name="title">Supervisor Dashboard</x-slot>
    <x-slot name="active">Department</x-slot>

    <div class="ul-stat-grid mb-6">
        @foreach($stats as $key => $value)
            <div class="ul-stat">
                <div class="ul-stat-label">{{ \App\Support\AccessUi::label($key) }}</div>
                <div class="ul-stat-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <x-unified.table title="Staff workload" :items="$staff" :colspan="3" empty-title="No staff in this department." empty-text="Assign support accounts to see workload.">
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Staff</th>
                <th>Open tickets</th>
            </x-slot:head>
            @foreach($staff as $person)
                @php $href = route('access.support.show', $person); @endphp
                <x-unified.row :href="$href">
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <x-unified.td-primary :href="$href" :text="$person->name" />
                    <td>
                        @if($person->open_tickets > 0)
                            <x-unified.badge tone="amber">{{ $person->open_tickets }} open</x-unified.badge>
                        @else
                            <x-unified.badge tone="lime">0 open</x-unified.badge>
                        @endif
                    </td>
                </x-unified.row>
            @endforeach
        </x-unified.table>

        <x-unified.table title="Assignment methods" :items="$methods" :colspan="3" empty-title="No assignment history yet." empty-text="Endorse and reassignment methods will appear here.">
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Method</th>
                <th>Total</th>
            </x-slot:head>
            @foreach($methods as $method => $total)
                <tr>
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <td>
                        <x-unified.badge tone="slate">{{ \App\Support\AccessUi::label($method) }}</x-unified.badge>
                    </td>
                    <td>{{ $total }}</td>
                </tr>
            @endforeach
        </x-unified.table>
    </div>
</x-app-layout>
