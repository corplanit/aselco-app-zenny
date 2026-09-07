<x-app-layout>
    <x-slot name="title">{{ $department->code }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.departments.index') }}", "text": "Departments"}</x-slot>
    <x-slot name="active">{{ $department->name }}</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('access.departments.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Departments
        </a>
    </x-slot>
    @include('pages.staff.access._nav')

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">{{ $department->code }}</div>
                        <div class="td-hero-badges">
                            <x-unified.badge :tone="$department->status === 'active' ? 'lime' : 'slate'">
                                {{ ucfirst($department->status) }}
                            </x-unified.badge>
                        </div>
                    </div>
                </div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Name</th>
                                    <td>
                                        {{ $department->name }}
                                        @if(\App\Support\TicketUi::departmentMeaning($department->code))
                                            <div class="ul-date-meta">{{ \App\Support\TicketUi::departmentMeaning($department->code) }}</div>
                                        @endif
                                    </td>
                                    <th>Contact</th>
                                    <td>{{ $department->contact ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Head</th>
                                    <td>{{ $department->head?->name ?: '—' }}</td>
                                    <th>Members</th>
                                    <td>{{ $department->members->count() }}</td>
                                </tr>
                                <tr>
                                    <th>Description</th>
                                    <td colspan="3" class="td-kv-wide">{{ $department->description ?: '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <x-unified.table title="Members" :items="$department->members" :colspan="4" empty-title="No members assigned." empty-text="Assign staff to this department from User Management.">
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Staff</th>
                    <th>Role</th>
                    <th class="text-end ul-col-actions">Actions</th>
                </x-slot:head>
                @foreach($department->members as $member)
                    @php $href = route('access.support.show', $member); @endphp
                    <x-unified.row :href="$href">
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <x-unified.td-primary :href="$href" :text="$member->name">
                            <x-slot:meta>{{ $member->email }}</x-slot:meta>
                        </x-unified.td-primary>
                        <td>
                            @php $roleKey = $member->accessRole?->code ?: $member->role; @endphp
                            @if($roleKey)
                                <x-unified.badge
                                    :tone="\App\Support\TicketUi::roleTone($roleKey)"
                                    :icon="\App\Support\TicketUi::roleIcon($roleKey)"
                                >{{ $member->accessRole?->name ?: \App\Support\TicketUi::roleLabel($roleKey) }}</x-unified.badge>
                            @else
                                <span class="ul-empty">—</span>
                            @endif
                        </td>
                        <x-unified.actions :view-url="$href" />
                    </x-unified.row>
                @endforeach
            </x-unified.table>

            <x-unified.table title="Recent tickets" :items="$tickets" :colspan="4" empty-title="No tickets." empty-text="Tickets assigned to this department will appear here.">
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Ticket</th>
                    <th>Status</th>
                    <th class="text-end ul-col-actions">Actions</th>
                </x-slot:head>
                @foreach($tickets as $ticket)
                    @php $href = route('tickets.show', $ticket->id); @endphp
                    <x-unified.row :href="$href">
                        <td class="ul-row-num">{{ $loop->iteration }}</td>
                        <x-unified.td-primary :href="$href" :text="$ticket->ticket_no" />
                        <td>
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::statusTone($ticket->status)"
                                :icon="\App\Support\TicketUi::statusIcon($ticket->status)"
                            >{{ \App\Support\TicketUi::statusLabel($ticket->status) }}</x-unified.badge>
                        </td>
                        <x-unified.actions :view-url="$href" />
                    </x-unified.row>
                @endforeach
            </x-unified.table>
        </div>

        <div class="xl:col-span-4 col-span-12">
            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">Edit department</div></div>
                <form method="POST" action="{{ route('access.departments.update', $department) }}">
                    @csrf @method('PUT')
                    <div class="ul-form-grid">
                        <div class="ul-field">
                            <label class="ti-form-label">Code</label>
                            <input name="code" class="ti-form-input" value="{{ $department->code }}" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Name</label>
                            <input name="name" class="ti-form-input" value="{{ $department->name }}" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Contact</label>
                            <input name="contact" class="ti-form-input" value="{{ $department->contact }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Status</label>
                            <select name="status" class="ti-form-select">
                                <option value="active" @selected($department->status === 'active')>Active</option>
                                <option value="inactive" @selected($department->status === 'inactive')>Inactive</option>
                            </select>
                        </div>
                        <div class="ul-field" style="grid-column: 1 / -1;">
                            <label class="ti-form-label">Description</label>
                            <textarea name="description" class="ti-form-input" rows="3">{{ $department->description }}</textarea>
                        </div>
                    </div>
                    @can('departments.edit')
                        <div class="ul-form-actions">
                            <button class="ti-btn ti-btn-sm ul-btn ul-btn-view"><i class="bi bi-check2"></i>Save</button>
                        </div>
                    @endcan
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
