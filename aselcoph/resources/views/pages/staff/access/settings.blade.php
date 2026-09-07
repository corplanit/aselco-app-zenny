<x-app-layout>
    <x-slot name="title">User Management Settings</x-slot>
    <x-slot name="url_1">{"link": "{{ route('access.users.index') }}", "text": "User Management"}</x-slot>
    <x-slot name="active">Settings</x-slot>
    @if(session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @include('pages.staff.access._nav')

    <form method="POST" action="{{ route('access.settings.update') }}">
        @csrf
        <div class="grid grid-cols-12 gap-6">
            <div class="xl:col-span-6 col-span-12">
                <div class="box ul-card">
                    <div class="box-header ul-card-header"><div class="box-title ul-card-title">Assignment</div></div>
                    <div class="ul-form-grid">
                        <div class="ul-field">
                            <label class="ti-form-label">Default role code</label>
                            <input name="default_role" class="ti-form-input" value="{{ $settings['default_role'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Assignment strategy</label>
                            <select name="assignment_strategy" class="ti-form-select">
                                @foreach(['manual','round_robin','least_busy','skill','ai_recommended'] as $strategy)
                                    <option value="{{ $strategy }}" @selected($settings['assignment_strategy'] === $strategy)>{{ \App\Support\AccessUi::label($strategy) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Max workload</label>
                            <input type="number" name="max_workload" class="ti-form-input" value="{{ $settings['max_workload'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Day shift start</label>
                            <input name="day_shift_start" class="ti-form-input" value="{{ $settings['day_shift_start'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Day shift end</label>
                            <input name="day_shift_end" class="ti-form-input" value="{{ $settings['day_shift_end'] }}">
                        </div>
                        <label class="ul-toggle">
                            <input type="hidden" name="ai_assisted_assignment" value="0">
                            <input type="checkbox" name="ai_assisted_assignment" value="1" @checked($settings['ai_assisted_assignment'])>
                            AI-assisted assignment
                        </label>
                    </div>
                </div>
            </div>
            <div class="xl:col-span-6 col-span-12">
                <div class="box ul-card">
                    <div class="box-header ul-card-header"><div class="box-title ul-card-title">Security</div></div>
                    <div class="ul-form-grid">
                        <div class="ul-field">
                            <label class="ti-form-label">Lockout attempts</label>
                            <input type="number" name="lockout_attempts" class="ti-form-input" value="{{ $settings['lockout_attempts'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Lockout minutes</label>
                            <input type="number" name="lockout_minutes" class="ti-form-input" value="{{ $settings['lockout_minutes'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Session timeout (minutes)</label>
                            <input type="number" name="session_timeout_minutes" class="ti-form-input" value="{{ $settings['session_timeout_minutes'] }}">
                        </div>
                        <div class="ul-field">
                            <label class="ti-form-label">Password min length</label>
                            <input type="number" name="password_min_length" class="ti-form-input" value="{{ $settings['password_min_length'] }}">
                        </div>
                        <label class="ul-toggle">
                            <input type="hidden" name="password_require_mixed" value="0">
                            <input type="checkbox" name="password_require_mixed" value="1" @checked($settings['password_require_mixed'])>
                            Require mixed password
                        </label>
                        <label class="ul-toggle">
                            <input type="hidden" name="mfa_required_for_staff" value="0">
                            <input type="checkbox" name="mfa_required_for_staff" value="1" @checked($settings['mfa_required_for_staff'])>
                            Require MFA for staff
                        </label>
                    </div>
                </div>
            </div>
        </div>
        @can('settings.manage')
            <div class="box ul-card mt-6">
                <div class="ul-form-actions">
                    <button class="ti-btn ti-btn-sm ul-btn ul-btn-view"><i class="bi bi-check2"></i>Save settings</button>
                </div>
            </div>
        @endcan
    </form>
</x-app-layout>
