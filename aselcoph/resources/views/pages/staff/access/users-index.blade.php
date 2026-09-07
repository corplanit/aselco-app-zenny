<x-app-layout>
    <x-slot name="title">{{ $title }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">{{ $title }}</x-slot>
    <x-slot name="buttons">
        @can('users.create')
            <a href="{{ route('access.users.import') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-upload"></i>Import
            </a>
            <a href="{{ route('access.users.export', request()->query()) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-download"></i>Export
            </a>
            <button class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-ul-modal="#access-user-create" type="button">
                <i class="bi bi-person-plus"></i>Register
            </button>
        @endcan
    </x-slot>

    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @include('pages.staff.access._nav')

    @php
        $isCustomerList = ($userType ?? null) === 'customer';
        $tableColspan = $isCustomerList ? 10 : 9;
    @endphp

    <x-unified.toolbar
        :action="route($routeName)"
        :reset-url="route($routeName)"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search name, email, phone, or account number…"
        :live="true"
        :debounce-ms="500"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Role</label>
                <select name="role_id" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($roles as $role)
                        <option
                            value="{{ $role->id }}"
                            @selected(($filters['role_id'] ?? '') == $role->id)
                            data-tone="{{ \App\Support\TicketUi::roleTone($role->code) }}"
                            data-icon="{{ \App\Support\TicketUi::roleIcon($role->code) }}"
                        >{{ \App\Support\AccessUi::roleOptionLabel($role->code, $role->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Department</label>
                <select name="department_id" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($departments as $dept)
                        <option
                            value="{{ $dept->id }}"
                            @selected(($filters['department_id'] ?? '') == $dept->id)
                            data-tone="{{ \App\Support\TicketUi::departmentTone($dept->code) }}"
                            data-icon="{{ \App\Support\TicketUi::departmentIcon($dept->code) }}"
                        >{{ \App\Support\AccessUi::departmentOptionLabel($dept->code, $dept->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="account_status" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['active','inactive','suspended','pending_activation','locked'] as $status)
                        <option value="{{ $status }}" @selected(($filters['account_status'] ?? '') === $status)>{{ \App\Support\AccessUi::accountStatusLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Availability</label>
                <select name="availability_status" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['available','busy','away','offline','on_leave'] as $status)
                        <option value="{{ $status }}" @selected(($filters['availability_status'] ?? '') === $status)>{{ \App\Support\AccessUi::availabilityLabel($status) }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <form method="POST" action="{{ route('access.users.bulk') }}" id="access-bulk-form">
        @csrf
        <x-unified.table title="{{ $title }}" :paginator="$users" :has-filters="$activeFilterCount > 0" :reset-url="route($routeName)" :colspan="$tableColspan">
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th class="ul-check-cell"><x-unified.check all="ids[]" /></th>
                <x-unified.th label="User" column="name" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
                <th>Role</th>
                <th>Department</th>
                <th>Status</th>
                @if($isCustomerList)
                    <th>Membership</th>
                @endif
                <th>Availability</th>
                <x-unified.th label="Last Login" column="last_login_at" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
                <th class="text-end ul-col-actions">Actions</th>
            </x-slot:head>
            @foreach($users as $person)
                @php
                    $href = ($person->user_type === 'support')
                        ? route('access.support.show', $person)
                        : route('access.customers.show', $person);
                    $status = $person->account_status ?: 'active';
                    $availability = $person->availability_status ?: 'offline';
                @endphp
                <x-unified.row :href="$href">
                    <x-unified.td-num :paginator="$users" :iteration="$loop->iteration" />
                    <td class="ul-no-row-click ul-check-cell"><x-unified.check name="ids[]" :value="$person->id" /></td>
                    <x-unified.td-primary :href="$href" :text="$person->name">
                        <x-slot:meta>{{ $person->email }}</x-slot:meta>
                    </x-unified.td-primary>
                    <td>
                        @php $roleKey = $person->accessRole?->code ?: $person->role; @endphp
                        @if($roleKey)
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::roleTone($roleKey)"
                                :icon="\App\Support\TicketUi::roleIcon($roleKey)"
                            >{{ $person->accessRole?->name ?: \App\Support\TicketUi::roleLabel($roleKey) }}</x-unified.badge>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
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
                            :tone="\App\Support\AccessUi::accountStatusTone($status)"
                            :icon="\App\Support\AccessUi::accountStatusIcon($status)"
                        >{{ \App\Support\AccessUi::accountStatusLabel($status) }}</x-unified.badge>
                    </td>
                    @if($isCustomerList)
                        <td>
                            @if($person->memberProfile)
                                <x-unified.badge tone="lime" icon="bi-file-earmark-check">Application on file</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-hourglass">Pending details</x-unified.badge>
                            @endif
                        </td>
                    @endif
                    <td>
                        <x-unified.badge
                            :tone="\App\Support\AccessUi::availabilityTone($availability)"
                            :icon="\App\Support\AccessUi::availabilityIcon($availability)"
                        >{{ \App\Support\AccessUi::availabilityLabel($availability) }}</x-unified.badge>
                    </td>
                    <td class="ul-date">{{ optional($person->last_login_at)->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: '—' }}</td>
                    <x-unified.actions :view-url="$href">
                        <x-slot:menu>
                            <a href="{{ $href }}" class="ul-menu-item" role="menuitem"><i class="bi bi-eye"></i> View</a>
                            @if($isCustomerList)
                                <a href="{{ route('access.customers.membership-application', $person) }}" target="_blank" class="ul-menu-item" role="menuitem"><i class="bi bi-printer"></i> Print application</a>
                            @endif
                        </x-slot:menu>
                    </x-unified.actions>
                </x-unified.row>
            @endforeach
            <x-slot:footer>
                @can('users.edit')
                    <select name="account_status" class="ti-form-select ul-select">
                        <option value="" data-tone="slate" data-icon="bi-sliders">Bulk status…</option>
                        <option value="active" data-tone="lime" data-icon="bi-check-circle">Activate</option>
                        <option value="inactive" data-tone="slate" data-icon="bi-pause-circle">Deactivate</option>
                        <option value="suspended" data-tone="orange" data-icon="bi-slash-circle">Suspend</option>
                    </select>
                    <select name="department_id" class="ti-form-select ul-select">
                        <option value="" data-tone="slate" data-icon="bi-diagram-3">Assign department…</option>
                        @foreach($departments as $dept)
                            <option
                                value="{{ $dept->id }}"
                                data-tone="{{ \App\Support\TicketUi::departmentTone($dept->code) }}"
                                data-icon="{{ \App\Support\TicketUi::departmentIcon($dept->code) }}"
                            >{{ \App\Support\AccessUi::departmentOptionLabel($dept->code, $dept->name) }}</option>
                        @endforeach
                    </select>
                    <select name="role_id" class="ti-form-select ul-select">
                        <option value="" data-tone="slate" data-icon="bi-person-badge">Assign role…</option>
                        @foreach($roles as $role)
                            <option
                                value="{{ $role->id }}"
                                data-tone="{{ \App\Support\TicketUi::roleTone($role->code) }}"
                                data-icon="{{ \App\Support\TicketUi::roleIcon($role->code) }}"
                            >{{ \App\Support\AccessUi::roleOptionLabel($role->code, $role->name) }}</option>
                        @endforeach
                    </select>
                    <button
                        class="ti-btn ti-btn-sm ul-btn ul-btn-view"
                        data-ul-confirm="Apply these bulk changes to the selected accounts."
                        data-ul-confirm-verb="Apply"
                        data-ul-confirm-icon="bi-check2"
                    >
                        <i class="bi bi-check2"></i>Apply
                    </button>
                @endcan
            </x-slot:footer>
        </x-unified.table>
    </form>

    @can('users.create')
        <div id="access-user-create" class="ul-modal" hidden>
            <div class="ul-modal-backdrop" data-ul-modal-close></div>
            <div class="ul-modal-dialog is-wide" role="dialog" aria-modal="true" aria-labelledby="access-user-create-title">
                <div class="ul-modal-header">
                    <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                    <div class="ul-modal-copy">
                        <h6 id="access-user-create-title">Register account</h6>
                        <p>Create a support or customer account and assign an initial role.</p>
                    </div>
                    <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form method="POST" action="{{ route('access.users.store') }}" class="ul-modal-body">
                    @csrf
                    <div class="ul-form-grid" style="padding: 0 0 1rem;">
                        <div class="ul-field">
                            <label class="ti-form-label">Full name</label>
                            <input name="name" class="ti-form-input" placeholder="Full name" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Email</label>
                            <input name="email" type="email" class="ti-form-input" placeholder="name@aselco.ph" required>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Username</label>
                            <input name="username" class="ti-form-input" placeholder="Optional">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Account type</label>
                            <select name="user_type" class="ti-form-select">
                                <option value="support">Support</option>
                                <option value="customer">Customer</option>
                            </select>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Role</label>
                            <select name="role_id" class="ti-form-select">
                                <option value="">Select role</option>
                                @foreach($roles as $role)
                                    <option
                                        value="{{ $role->id }}"
                                        data-tone="{{ \App\Support\TicketUi::roleTone($role->code) }}"
                                        data-icon="{{ \App\Support\TicketUi::roleIcon($role->code) }}"
                                    >{{ \App\Support\AccessUi::roleOptionLabel($role->code, $role->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Department</label>
                            <select name="department_id" class="ti-form-select">
                                <option value="">No department</option>
                                @foreach($departments as $dept)
                                    <option
                                        value="{{ $dept->id }}"
                                        data-tone="{{ \App\Support\TicketUi::departmentTone($dept->code) }}"
                                        data-icon="{{ \App\Support\TicketUi::departmentIcon($dept->code) }}"
                                    >{{ \App\Support\AccessUi::departmentOptionLabel($dept->code, $dept->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="ul-modal-footer">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                            <i class="bi bi-x-lg"></i>Cancel
                        </button>
                        <button class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi bi-person-plus"></i>Create account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
