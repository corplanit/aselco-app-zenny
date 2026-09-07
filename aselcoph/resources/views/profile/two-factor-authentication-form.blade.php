<div class="box ul-card">
    <div class="box-header ul-card-header">
        <div class="box-title ul-card-title">
            Two-factor authentication
            <span class="ul-card-count">Authenticator app</span>
        </div>
    </div>
    <div class="box-body pf-form">
        @if ($this->enabled)
            @if ($showingConfirmation)
                <x-unified.note tone="amber" icon="bi-shield-lock" title="Finish enabling 2FA">
                    Scan the QR code or enter the setup key, then confirm with a code from your authenticator app.
                </x-unified.note>
            @else
                <x-unified.note tone="lime" icon="bi-shield-check" title="Two-factor is on">
                    You will be asked for a code from Google Authenticator when you sign in.
                </x-unified.note>
            @endif
        @else
            <x-unified.note tone="slate" icon="bi-shield" title="Two-factor is off">
                Add a second step at sign-in using a secure code from your phone’s authenticator app.
            </x-unified.note>
        @endif

        @if ($this->enabled)
            @if ($showingQrCode)
                <div class="pf-2fa-box">
                    <div class="pf-2fa-qr">{!! $this->user->twoFactorQrCodeSvg() !!}</div>
                    <p class="pf-note">
                        Scan with Google Authenticator or another TOTP app.
                    </p>
                    <p class="pf-note">
                        <strong>Setup key:</strong> {{ decrypt($this->user->two_factor_secret) }}
                    </p>
                </div>

                @if ($showingConfirmation)
                    <div class="ul-field">
                        <label class="ti-form-label" for="code">Authenticator code</label>
                        <input
                            id="code"
                            type="text"
                            name="code"
                            class="ti-form-input"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                            wire:model="code"
                            wire:keydown.enter="confirmTwoFactorAuthentication"
                        >
                        <x-input-error for="code" class="mt-2" />
                    </div>
                @endif
            @endif

            @if ($showingRecoveryCodes)
                <div class="pf-recovery">
                    <p class="pf-note">Store these recovery codes in a password manager. They can restore access if you lose your authenticator.</p>
                    <div class="pf-recovery-list">
                        @foreach (json_decode(decrypt($this->user->two_factor_recovery_codes), true) as $code)
                            <div>{{ $code }}</div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
    <div class="ul-card-footer pf-actions">
        @if (! $this->enabled)
            <x-confirms-password wire:then="enableTwoFactorAuthentication">
                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" wire:loading.attr="disabled">
                    <i class="bi bi-shield-check"></i>Enable
                </button>
            </x-confirms-password>
        @else
            @if ($showingRecoveryCodes)
                <x-confirms-password wire:then="regenerateRecoveryCodes">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                        <i class="bi bi-arrow-repeat"></i>Regenerate codes
                    </button>
                </x-confirms-password>
            @elseif ($showingConfirmation)
                <x-confirms-password wire:then="confirmTwoFactorAuthentication">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" wire:loading.attr="disabled">
                        <i class="bi bi-check2"></i>Confirm
                    </button>
                </x-confirms-password>
            @else
                <x-confirms-password wire:then="showRecoveryCodes">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                        <i class="bi bi-key"></i>Show recovery codes
                    </button>
                </x-confirms-password>
            @endif

            @if ($showingConfirmation)
                <x-confirms-password wire:then="disableTwoFactorAuthentication">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" wire:loading.attr="disabled">
                        Cancel
                    </button>
                </x-confirms-password>
            @else
                <x-confirms-password wire:then="disableTwoFactorAuthentication">
                    <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" wire:loading.attr="disabled">
                        <i class="bi bi-x-lg"></i>Disable
                    </button>
                </x-confirms-password>
            @endif
        @endif
    </div>
</div>
