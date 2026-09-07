<x-app-layout>
    <x-slot name="title">User Management</x-slot>
    <x-slot name="url_1">{"link": "/users", "text": "Manage"}</x-slot>
    <x-slot name="url_2">{"link": "/users", "text": "Users"}</x-slot>
    <x-slot name="active">Accounts</x-slot>
    <x-slot name="buttons">
        <button class="ti-btn ti-btn-primary ti-btn-sm" data-hs-overlay="#user-create" type="button">
            <i class="bi bi-plus me-1"></i>Register Users
        </button>
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif

    <x-unified.toolbar
        :action="url('/users')"
        :reset-url="url('/users')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search name, email, role, department…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Role</label>
                <select name="role" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Department</label>
                <select name="department_code" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($departments as $dept)
                        <option value="{{ $dept }}" @selected(($filters['department_code'] ?? '') === $dept)>{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Email verified</label>
                <select name="verified" class="ti-form-select">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['verified'] ?? '') === '1')>Verified</option>
                    <option value="0" @selected(($filters['verified'] ?? '') === '0')>Not verified</option>
                </select>
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <x-unified.table
        title="Users"
        :paginator="$users"
        :has-filters="$activeFilterCount > 0"
        :reset-url="url('/users')"
        empty-title="No users found."
        :colspan="9"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <x-unified.th label="Name" column="name" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
            <x-unified.th label="Email" column="email" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
            <th>Validated</th>
            <th>Role</th>
            <th>Department</th>
            <x-unified.th label="Created" column="created_at" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
            <th>Status</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach($users as $user)
            @php
                $userPayload = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'department_code' => $user->department_code,
                    'email_verified_at' => $user->email_verified_at,
                ];
            @endphp
            <x-unified.row>
                <x-unified.td-num :paginator="$users" :iteration="$loop->iteration" />
                <td class="font-semibold">{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @if($user->email_verified_at)
                        <x-unified.badge tone="info" icon="bi-check-circle">Verified</x-unified.badge>
                    @else
                        <x-unified.badge tone="danger" icon="bi-x-circle">Not verified</x-unified.badge>
                    @endif
                </td>
                <td>
                    @php
                        $roleUi = match ($user->role) {
                            'Administrator', 'administrator' => ['icon' => 'bi-shield-lock', 'tone' => 'indigo'],
                            'Customer Service' => ['icon' => 'bi-headset', 'tone' => 'sky'],
                            'support' => ['icon' => 'bi-headset', 'tone' => 'teal'],
                            'Supervisor', 'supervisor' => ['icon' => 'bi-person-badge', 'tone' => 'orange'],
                            'Content Manager' => ['icon' => 'bi-journal-richtext', 'tone' => 'violet'],
                            default => ['icon' => 'bi-person', 'tone' => 'slate'],
                        };
                    @endphp
                    <x-unified.badge :tone="$roleUi['tone']" :icon="$roleUi['icon']">{{ $user->role }}</x-unified.badge>
                </td>
                <td>{{ $user->department_code ?: '—' }}</td>
                <td class="ul-date">
                    {{ optional($user->created_at)->timezone('Asia/Manila')->format('M d, Y') }}
                </td>
                <td><x-unified.badge tone="success" icon="bi-check-circle-fill">Active</x-unified.badge></td>
                <td class="ul-no-row-click text-end">
                    <button
                        type="button"
                        class="ti-btn ti-btn-sm ul-btn-edit open-user-modal"
                        data-user='@json($userPayload)'
                        data-hs-overlay="#user-info"
                    >
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                </td>
            </x-unified.row>
        @endforeach
    </x-unified.table>

    <script>
        $(document).on('click', '.open-user-modal', function(e) {
            e.preventDefault();
            try {
                const user = $(this).data('user');
                $('#user-info input[name="name"]').val(user.name);
                $('#user-info input[name="email"]').val(user.email);
                const roleSelect = $('#user-info select[name="role"]');
                if (user.role && roleSelect.find('option[value="'+user.role+'"]').length === 0) {
                    roleSelect.append($('<option>', { value: user.role, text: user.role }));
                }
                roleSelect.val(user.role).trigger('change');
                $('#user-info select[name="department_code"]').val(user.department_code || '').trigger('change');
                $('#user-info select[name="email_validated"]').val(user.email_verified_at ? '1' : '0').trigger('change');
                const form = $('#user-info form');
                form.attr('action', `/users/${user.id}`);
                form.find('input[name="_method"]').remove();
                form.append(`<input type="hidden" name="_method" value="PUT">`);
                window.HSOverlay?.open(document.getElementById('user-info'));
            } catch (err) {
                console.error('Failed to open modal:', err);
            }
        });
        document.addEventListener('DOMContentLoaded', function() {
            window.HSOverlay?.autoInit();
        });
    </script>

    <div id="user-create" class="hs-overlay hs-overlay-open:mt-6 ti-modal hidden">
        <div class="hs-overlay ti-modal-box mt-0 lg:!max-w-4xl lg:w-full m-3 items-center justify-center">
            <div class="max-h-full w-full overflow-hidden ti-modal-content">
                <div class="ti-modal-header">
                    <h6 class="modal-title text-[1rem] font-semibold">Register User</h6>
                    <button type="button" class="hs-dropdown-toggle ti-modal-close-btn" data-hs-overlay="#user-create">
                        <span class="sr-only">Close</span>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" class="space-y-4 p-6 pt-0" action="{{ route('users.save') }}">
                    @csrf
                    <div>
                        <label class="ti-form-label">Name</label>
                        <input name="name" class="ti-form-input" required>
                    </div>
                    <div>
                        <label class="ti-form-label">Email</label>
                        <input name="email" type="email" class="ti-form-input" required>
                    </div>
                    <div>
                        <label class="ti-form-label">Role</label>
                        <select name="role" class="ti-form-select" required>
                            @foreach ($managedRoles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="ti-form-label">Department</label>
                        <select name="department_code" class="ti-form-select">
                            <option value="">None — not ticket support</option>
                            @foreach ($departments as $dept)
                                <option value="{{ $dept }}">{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="ti-btn ti-btn-primary" type="submit">Create</button>
                </form>
            </div>
        </div>
    </div>

    <div id="user-info" class="hs-overlay hs-overlay-open:mt-6 ti-modal hidden">
        <div class="hs-overlay ti-modal-box mt-0 lg:!max-w-4xl lg:w-full m-3 items-center justify-center">
            <div class="max-h-full w-full overflow-hidden ti-modal-content">
                <div class="ti-modal-header">
                    <h6 class="modal-title text-[1rem] font-semibold">Edit User</h6>
                    <button type="button" class="hs-dropdown-toggle ti-modal-close-btn" data-hs-overlay="#user-info">
                        <span class="sr-only">Close</span>
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="#" class="p-6 pt-0 space-y-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="ti-form-label">Name</label>
                            <input name="name" class="ti-form-input" required>
                        </div>
                        <div>
                            <label class="ti-form-label">Email</label>
                            <input type="email" name="email" class="ti-form-input" required>
                        </div>
                        <div>
                            <label class="ti-form-label">Role</label>
                            <select name="role" class="ti-form-select" required>
                                @foreach ($managedRoles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ti-form-label">Department</label>
                            <select name="department_code" class="ti-form-select">
                                <option value="">None — not ticket support</option>
                                @foreach ($departments as $dept)
                                    <option value="{{ $dept }}">{{ \App\Support\TicketUi::departmentOptionLabel($dept) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ti-form-label">Email Validated</label>
                            <select name="email_validated" class="ti-form-select">
                                <option value="0">No</option>
                                <option value="1">Yes</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="ti-btn ti-btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
