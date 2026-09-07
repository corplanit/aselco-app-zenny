<x-app-layout>
    <x-slot name="title">Roles</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Roles</x-slot>
    <x-slot name="buttons">
        @can('roles.create')
            <button class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#access-role-create" type="button">
                <i class="bi bi-plus-lg"></i>Add role
            </button>
        @endcan
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @include('pages.staff.access._nav')

    <x-unified.table title="Roles" :items="$roles" :colspan="6" empty-title="No roles yet." empty-text="Create a role to start assigning access.">
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Role</th>
            <th>Type</th>
            <th>Scope</th>
            <th>Users</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>
        @foreach($roles as $role)
            <tr class="ul-row">
                <td class="ul-row-num">{{ $loop->iteration }}</td>
                <td>
                    <div class="font-semibold">{{ $role->name }}</div>
                    <div class="ul-date-meta">{{ \App\Support\TicketUi::roleMeaning($role->code) ?: $role->code }}</div>
                </td>
                <td>
                    <x-unified.badge :tone="\App\Support\AccessUi::userTypeTone($role->user_type)">
                        {{ \App\Support\AccessUi::userTypeLabel($role->user_type) }}
                    </x-unified.badge>
                </td>
                <td>
                    <x-unified.badge :tone="\App\Support\AccessUi::scopeTone($role->scope)">
                        {{ \App\Support\AccessUi::scopeLabel($role->scope) }}
                    </x-unified.badge>
                </td>
                <td>{{ $role->users_count }}</td>
                <td class="text-end ul-col-actions">
                    @can('permissions.view')
                        <a href="{{ route('access.permissions.index', ['role_id' => $role->id]) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view" title="Permissions">
                            <i class="bi bi-key"></i>
                        </a>
                    @endcan
                </td>
            </tr>
        @endforeach
    </x-unified.table>

    @can('roles.create')
        <div id="access-role-create" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="access-role-create-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-person-badge" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="access-role-create-title">Add role</h6>
                        <p>Create a role code and choose who it applies to.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('access.roles.store') }}" class="ul-modal-body">
                    @csrf
                    <div class="ul-form-grid" style="padding: 0 0 1rem;">
                        <div class="ul-field">
                            <label class="ti-form-label">Role name</label>
                            <input name="name" class="ti-form-input" placeholder="Support Agent" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Code</label>
                            <input name="code" class="ti-form-input" placeholder="support_agent" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Type</label>
                            <select name="user_type" class="ti-form-select">
                                <option value="support">Support</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Scope</label>
                            <select name="scope" class="ti-form-select">
                                <option value="all">All</option>
                                <option value="department">Department</option>
                                <option value="assigned">Assigned</option>
                                <option value="own">Own</option>
                            </select>
                        </div>
                    </div>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>Cancel</button>
                        <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-plus-lg"></i>Create role
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
