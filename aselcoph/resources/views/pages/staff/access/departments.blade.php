<x-app-layout>
    <x-slot name="title">Departments</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Departments</x-slot>
    <x-slot name="buttons">
        @can('departments.create')
            <button class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#access-department-create" type="button">
                <i class="bi bi-plus-lg"></i>Add department
            </button>
        @endcan
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @include('pages.staff.access._nav')

    <x-unified.table title="Departments" :paginator="$departments" :has-filters="$activeFilterCount > 0" :reset-url="route('access.departments.index')" :colspan="8">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Department</th>
            <th>Head</th>
            <th>Staff</th>
            <th>Open tickets</th>
            <th>SLA</th>
            <th>Status</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>
        @foreach($departments as $dept)
            @php $href = route('access.departments.show', $dept); @endphp
            <x-unified.row :href="$href">
                <x-unified.td-num :paginator="$departments" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$dept->code">
                    <x-slot:meta>{{ \App\Support\TicketUi::departmentMeaning($dept->code) ?: $dept->name }}</x-slot:meta>
                </x-unified.td-primary>
                <td>{{ $dept->head?->name ?: '—' }}</td>
                <td>{{ $dept->members_count }}</td>
                <td>{{ $ticketStats[$dept->code]->open_count ?? 0 }}</td>
                <td>
                    @if(($ticketStats[$dept->code]->sla_count ?? 0) > 0)
                        <x-unified.badge tone="danger" icon="bi-exclamation-octagon">{{ $ticketStats[$dept->code]->sla_count }}</x-unified.badge>
                    @else
                        0
                    @endif
                </td>
                <td>
                    <x-unified.badge :tone="$dept->status === 'active' ? 'lime' : 'slate'" :icon="$dept->status === 'active' ? 'bi-check-circle' : 'bi-pause-circle'">
                        {{ ucfirst($dept->status) }}
                    </x-unified.badge>
                </td>
                <x-unified.actions :view-url="$href">
                    <x-slot:menu>
                        <a href="{{ $href }}" class="ul-menu-item" role="menuitem"><i class="bi bi-eye"></i> View</a>
                    </x-slot:menu>
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>

    @can('departments.create')
        <div id="access-department-create" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="access-department-create-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-sky"><i class="bi bi-diagram-3" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="access-department-create-title">Add department</h6>
                        <p>Create a department code and optional contact head.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('access.departments.store') }}" class="ul-modal-body">
                    @csrf
                    <div class="ul-form-grid" style="padding: 0 0 1rem;">
                        <div class="ul-field">
                            <label class="ti-form-label">Code</label>
                            <input name="code" class="ti-form-input" placeholder="COMD" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Name</label>
                            <input name="name" class="ti-form-input" placeholder="Commercial Operations" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Contact</label>
                            <input name="contact" class="ti-form-input" placeholder="Optional">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Head</label>
                            <select name="head_user_id" class="ti-form-select">
                                <option value="">Unassigned</option>
                                @foreach($heads as $head)
                                    <option value="{{ $head->id }}">{{ $head->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>Cancel</button>
                        <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-plus-lg"></i>Create department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
