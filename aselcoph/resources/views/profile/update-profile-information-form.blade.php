<form wire:submit="updateProfileInformation">
    <div class="box ul-card">
        <div class="box-header ul-card-header">
            <div class="box-title ul-card-title">
                Profile information
                <span class="ul-card-count">Name, email, and photo</span>
            </div>
        </div>
        <div class="box-body pf-form">
            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                @php
                    $currentPhoto = filled($this->user->profile_photo_path)
                        ? asset('storage/'.ltrim((string) $this->user->profile_photo_path, '/'))
                        : asset('/user.png');
                @endphp
                <div x-data="{ photoName: null, photoPreview: null }" class="td-photo-stage">
                    <input
                        type="file"
                        id="photo"
                        class="td-photo-input"
                        accept="image/*"
                        wire:model.live="photo"
                        x-ref="photo"
                        x-on:change="
                            photoName = $refs.photo.files[0].name;
                            const reader = new FileReader();
                            reader.onload = (e) => { photoPreview = e.target.result; };
                            reader.readAsDataURL($refs.photo.files[0]);
                        "
                    >
                    <img
                        class="td-photo-preview"
                        x-show="! photoPreview"
                        src="{{ $currentPhoto }}"
                        alt="{{ $this->user->name }}"
                        onerror="this.onerror=null; this.src='{{ asset('/user.png') }}';"
                    >
                    <span
                        class="td-photo-preview"
                        x-show="photoPreview"
                        x-cloak
                        x-bind:style="'background-image: url(\'' + photoPreview + '\'); background-size: cover; background-position: center;'"
                    ></span>
                    <div class="td-photo-actions">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more" x-on:click.prevent="$refs.photo.click()">
                            <i class="bi bi-camera"></i>Change photo
                        </button>
                        @if ($this->user->profile_photo_path)
                            <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" wire:click="deleteProfilePhoto">
                                <i class="bi bi-trash"></i>Remove
                            </button>
                        @endif
                    </div>
                    <p class="td-photo-hint">JPG or PNG, shown on this portal and your staff card.</p>
                    <x-input-error for="photo" class="td-photo-error" />
                </div>
            @endif

            <div class="ul-field">
                <label class="ti-form-label" for="name">Complete name</label>
                <input id="name" type="text" class="ti-form-input" wire:model="state.name" required autocomplete="name">
                <x-input-error for="name" class="mt-2" />
            </div>

            <div class="ul-field">
                <label class="ti-form-label" for="email">Username / email address</label>
                <input id="email" type="email" class="ti-form-input" wire:model="state.email" required autocomplete="username">
                <x-input-error for="email" class="mt-2" />

                @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::emailVerification()) && ! $this->user->hasVerifiedEmail())
                    <p class="pf-note">
                        Your email address is unverified.
                        <button type="button" class="pf-link" wire:click.prevent="sendEmailVerification">
                            Resend verification email
                        </button>
                    </p>
                    @if ($this->verificationLinkSent)
                        <p class="pf-note is-ok">A new verification link has been sent to your email address.</p>
                    @endif
                @endif
            </div>
        </div>
        <div class="ul-card-footer pf-actions">
            <x-action-message class="pf-saved" on="saved">Saved.</x-action-message>
            <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view" wire:loading.attr="disabled" wire:target="photo">
                <i class="bi bi-check2"></i>Save
            </button>
        </div>
    </div>
</form>
