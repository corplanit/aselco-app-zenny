<x-app-layout>
    <x-slot name="title">Staff Availability</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Availability</x-slot>
    @include('pages.staff.access._nav')

    <x-unified.table title="Staff availability" :paginator="$staff" :colspan="6">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Staff</th>
            <th>Department</th>
            <th>Status</th>
            <th>Shift</th>
            <th class="text-end ul-col-actions">Update</th>
        </x-slot:head>
        @foreach($staff as $person)
            @php
                $status = $person->staffAvailability?->status ?: $person->availability_status ?: 'offline';
                $href = route('access.support.show', $person);
            @endphp
            <tr>
                <x-unified.td-num :paginator="$staff" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$person->name">
                    <x-slot:meta>{{ $person->email }}</x-slot:meta>
                </x-unified.td-primary>
                <td>
                    @php $deptCode = $person->accessDepartment?->code ?: $person->department_code; @endphp
                    @if($deptCode)
                        <x-unified.badge
                            :tone="\App\Support\TicketUi::departmentTone($deptCode)"
                            :icon="\App\Support\TicketUi::departmentIcon($deptCode)"
                        >{{ $deptCode }}</x-unified.badge>
                        <div class="ul-date-meta">{{ \App\Support\TicketUi::departmentMeaning($deptCode) ?: ($person->accessDepartment?->name ?: '') }}</div>
                    @else
                        <span class="ul-empty">—</span>
                    @endif
                </td>
                <td>
                    <x-unified.badge
                        :tone="\App\Support\AccessUi::availabilityTone($status)"
                        :icon="\App\Support\AccessUi::availabilityIcon($status)"
                    >{{ \App\Support\AccessUi::availabilityLabel($status) }}</x-unified.badge>
                </td>
                <td>{{ $person->staffAvailability?->shift ?: '—' }}</td>
                <td class="ul-no-row-click text-end ul-col-actions">
                    <form method="POST" action="{{ route('access.availability.update', $person) }}" class="flex justify-end gap-2">
                        @csrf
                        <select name="status" class="ti-form-select ul-select">
                            @foreach(['available','busy','away','offline','on_leave'] as $option)
                                <option value="{{ $option }}" @selected($status === $option)>{{ \App\Support\AccessUi::availabilityLabel($option) }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="shift" value="{{ $person->staffAvailability?->shift ?: 'day' }}">
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view"><i class="bi bi-check2"></i>Save</button>
                    </form>
                </td>
            </tr>
        @endforeach
    </x-unified.table>
</x-app-layout>
