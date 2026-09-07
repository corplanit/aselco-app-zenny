<x-app-layout>
    <x-slot name="title">Permissions</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Permission matrix</x-slot>
    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @include('pages.staff.access._nav')

    @if($target)
        <div class="box ul-card">
            <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                <div class="box-title ul-card-title mb-0">
                    Permission matrix
                    <span class="ul-card-count">{{ $target->name }}</span>
                </div>
                <form method="GET" class="ul-card-header-actions">
                    <select name="role_id" class="ti-form-select ul-select" onchange="this.form.submit()">
                        @foreach($roles as $role)
                            <option
                                value="{{ $role->id }}"
                                @selected($target?->id === $role->id)
                                data-tone="{{ \App\Support\TicketUi::roleTone($role->code) }}"
                                data-icon="{{ \App\Support\TicketUi::roleIcon($role->code) }}"
                            >{{ \App\Support\AccessUi::roleOptionLabel($role->code, $role->name) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <form method="POST" action="{{ route('access.permissions.roles.update', $target) }}">
                @csrf @method('PUT')
                <div class="box-body p-0">
                    <div class="ul-table-wrap table-responsive">
                        <table class="table ul-table ul-matrix mb-0">
                            <thead>
                                <tr>
                                    <th>Module</th>
                                    @foreach(['view','create','edit','delete','assign','approve'] as $action)
                                        <th>{{ ucfirst($action) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modules as $module => $actions)
                                    <tr>
                                        <td class="font-semibold">{{ ucfirst(str_replace('_',' ', $module)) }}</td>
                                        @foreach(['view','create','edit','delete','assign','approve'] as $action)
                                            <td>
                                                @if(in_array($action, $actions, true))
                                                    <x-unified.check name="cells[{{ $module }}][{{ $action }}]" value="1" :checked="!empty($matrix[$module][$action])" />
                                                @else
                                                    <span class="ul-empty">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('users.permissions.manage')
                        <div class="ul-card-footer">
                            <button class="ti-btn ti-btn-sm ul-btn ul-btn-view"><i class="bi bi-check2"></i>Save matrix</button>
                        </div>
                    @endcan
                </div>
            </form>
        </div>
    @else
        <x-unified.table title="Permission matrix" :items="[]" :colspan="1" empty-title="No roles found." empty-text="Create a role first, then assign module permissions." />
    @endif
</x-app-layout>
