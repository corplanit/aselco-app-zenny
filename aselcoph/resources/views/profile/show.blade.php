<x-app-layout>
    @php
        $user = auth()->user();
        $roleKey = $user->accessRole?->code ?: $user->role;
        $deptCode = $user->accessDepartment?->code ?: $user->department_code;
        $roleLabel = $user->accessRole?->name ?: \App\Support\TicketUi::roleLabel($roleKey) ?: 'Staff';
        $hasPhoto = filled($user->profile_photo_path);
        $photoUrl = $hasPhoto
            ? asset('storage/'.ltrim((string) $user->profile_photo_path, '/'))
            : asset('/user.png');
        $hasTwoFactor = filled($user->two_factor_secret);
    @endphp

    <x-slot name="title">Profile Settings</x-slot>
    <x-slot name="url_1">{"link": "{{ route('profile.show') }}", "text": "Profile"}</x-slot>
    <x-slot name="active">Settings</x-slot>
    <x-slot name="headerAvatar">
        <span class="page-header-card__avatar-face">
            <img
                src="{{ $photoUrl }}"
                alt="{{ $user->name }}"
                class="page-header-card__avatar-img"
                onerror="this.onerror=null; this.src='{{ asset('/user.png') }}';"
            >
        </span>
    </x-slot>

    <div class="pf-dash" id="profile-settings">
        <section class="box ul-card">
            <div class="box-body pf-identity">
                <div class="td-hero">
                    <div class="td-avatar">
                        <span class="td-avatar-face">
                            <img
                                class="td-avatar-img"
                                src="{{ $photoUrl }}"
                                alt="{{ $user->name }}"
                                onerror="this.onerror=null; this.src='{{ asset('/user.png') }}';"
                            >
                        </span>
                    </div>
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">{{ $user->name }}</div>
                        <div class="pf-identity-meta">{{ $user->email }}</div>
                        <div class="td-hero-badges">
                            <x-unified.badge
                                :tone="\App\Support\TicketUi::roleTone($roleKey)"
                                :icon="\App\Support\TicketUi::roleIcon($roleKey)"
                            >{{ $roleLabel }}</x-unified.badge>
                            @if($deptCode)
                                <x-unified.badge
                                    :tone="\App\Support\TicketUi::departmentTone($deptCode)"
                                    :icon="\App\Support\TicketUi::departmentIcon($deptCode)"
                                >{{ $user->accessDepartment?->name ?: $deptCode }}</x-unified.badge>
                            @endif
                            @if($user->email_verified_at)
                                <x-unified.badge tone="lime" icon="bi-envelope-check">Verified</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-envelope">Unverified</x-unified.badge>
                            @endif
                            @if($hasTwoFactor)
                                <x-unified.badge tone="lime" icon="bi-shield-check">2FA on</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-shield">2FA off</x-unified.badge>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <nav class="ul-subnav" aria-label="Profile sections">
            <button type="button" class="ul-subnav-item is-active" data-pf-tab="profile" aria-selected="true">
                <i class="bi bi-person"></i>Profile
            </button>
            <button type="button" class="ul-subnav-item" data-pf-tab="password" aria-selected="false">
                <i class="bi bi-key"></i>Password
            </button>
            <button type="button" class="ul-subnav-item" data-pf-tab="two-factor" aria-selected="false">
                <i class="bi bi-shield-lock"></i>Two-factor
            </button>
            <button type="button" class="ul-subnav-item" data-pf-tab="sessions" aria-selected="false">
                <i class="bi bi-laptop"></i>Sessions
            </button>
        </nav>

        <div class="pf-panel" data-pf-panel="profile">
            @if (Laravel\Fortify\Features::canUpdateProfileInformation())
                @livewire('profile.update-profile-information-form')
            @endif
        </div>

        <div class="pf-panel" data-pf-panel="password" hidden>
            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
                @livewire('profile.update-password-form')
            @endif
        </div>

        <div class="pf-panel" data-pf-panel="two-factor" hidden>
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                @livewire('profile.two-factor-authentication-form')
            @endif
        </div>

        <div class="pf-panel" data-pf-panel="sessions" hidden>
            @livewire('profile.logout-other-browser-sessions-form')
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('profile-settings');
            if (!root) return;
            const tabs = root.querySelectorAll('[data-pf-tab]');
            const panels = root.querySelectorAll('[data-pf-panel]');
            const keys = Array.from(tabs).map((tab) => tab.dataset.pfTab);
            const activate = (key) => {
                if (!keys.includes(key)) key = 'profile';
                tabs.forEach((item) => {
                    const on = item.dataset.pfTab === key;
                    item.classList.toggle('is-active', on);
                    item.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                panels.forEach((panel) => {
                    panel.hidden = panel.dataset.pfPanel !== key;
                });
                if (history.replaceState) {
                    history.replaceState(null, '', '#' + key);
                }
            };
            tabs.forEach((tab) => {
                tab.addEventListener('click', () => activate(tab.dataset.pfTab));
            });
            activate((location.hash || '#profile').slice(1));
        })();
    </script>
</x-app-layout>
