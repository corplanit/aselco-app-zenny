<div>
<div class="box ul-card">
    <div class="box-header ul-card-header">
        <div class="box-title ul-card-title">
            Browser sessions
            <span class="ul-card-count">{{ count($this->sessions) }} {{ count($this->sessions) === 1 ? 'device' : 'devices' }}</span>
        </div>
    </div>
    <div class="box-body">
        <p class="pf-note pf-note-lead">
            Sign out other browsers if you think this account was used on a device you do not recognize. Update your password as well.
        </p>

        @if (count($this->sessions) > 0)
            <div class="pf-sessions">
                @foreach ($this->sessions as $session)
                    <div class="pf-session">
                        <span class="pf-session-icon">
                            <i class="bi {{ $session->agent->isDesktop() ? 'bi-laptop' : 'bi-phone' }}"></i>
                        </span>
                        <span class="pf-session-copy">
                            <span class="pf-session-title">
                                {{ $session->agent->platform() ?: 'Unknown' }}
                                ·
                                {{ $session->agent->browser() ?: 'Unknown' }}
                            </span>
                            <span class="pf-session-meta">
                                {{ $session->ip_address }}
                                @if ($session->is_current_device)
                                    · This device
                                @else
                                    · Last active {{ $session->last_active }}
                                @endif
                            </span>
                        </span>
                        @if ($session->is_current_device)
                            <x-unified.badge tone="lime" icon="bi-check2">Current</x-unified.badge>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <x-unified.note tone="slate" icon="bi-laptop" title="No session details">
                Signed-in devices will appear here when session tracking is available.
            </x-unified.note>
        @endif
    </div>
    <div class="ul-card-footer pf-actions">
        <x-action-message class="pf-saved" on="loggedOut">Done.</x-action-message>
        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" wire:click="confirmLogout" wire:loading.attr="disabled">
            <i class="bi bi-box-arrow-right"></i>Log out other sessions
        </button>
    </div>
</div>

<x-dialog-modal wire:model.live="confirmingLogout">
    <x-slot name="title">Log out other browser sessions</x-slot>
    <x-slot name="content">
        Enter your password to confirm sign-out on other devices.
        <div class="mt-4 ul-field" x-data="{}" x-on:confirming-logout-other-browser-sessions.window="setTimeout(() => $refs.password.focus(), 250)">
            <input
                type="password"
                class="ti-form-input"
                autocomplete="current-password"
                placeholder="Password"
                x-ref="password"
                wire:model="password"
                wire:keydown.enter="logoutOtherBrowserSessions"
            >
            <x-input-error for="password" class="mt-2" />
        </div>
    </x-slot>
    <x-slot name="footer">
        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" wire:click="$toggle('confirmingLogout')" wire:loading.attr="disabled">
            Cancel
        </button>
        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" wire:click="logoutOtherBrowserSessions" wire:loading.attr="disabled">
            Log out other sessions
        </button>
    </x-slot>
</x-dialog-modal>
</div>
