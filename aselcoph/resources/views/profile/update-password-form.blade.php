<form wire:submit="updatePassword">
    <div class="box ul-card">
        <div class="box-header ul-card-header">
            <div class="box-title ul-card-title">
                Update password
                <span class="ul-card-count">Use a long, unique password</span>
            </div>
        </div>
        <div class="box-body pf-form">
            <div class="ul-field">
                <label class="ti-form-label" for="current_password">Current password</label>
                <input id="current_password" type="password" class="ti-form-input" wire:model="state.current_password" autocomplete="current-password">
                <x-input-error for="current_password" class="mt-2" />
            </div>
            <div class="ul-field">
                <label class="ti-form-label" for="password">New password</label>
                <input id="password" type="password" class="ti-form-input" wire:model="state.password" autocomplete="new-password">
                <x-input-error for="password" class="mt-2" />
            </div>
            <div class="ul-field">
                <label class="ti-form-label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" class="ti-form-input" wire:model="state.password_confirmation" autocomplete="new-password">
                <x-input-error for="password_confirmation" class="mt-2" />
            </div>
        </div>
        <div class="ul-card-footer pf-actions">
            <x-action-message class="pf-saved" on="saved">Saved.</x-action-message>
            <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                <i class="bi bi-check2"></i>Save
            </button>
        </div>
    </div>
</form>
