<div id="create-contact" class="ul-modal" hidden>
    <div class="ul-modal-backdrop" data-ul-modal-close></div>
    <div class="ul-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="consumer-register-title">
        <div class="ul-modal-header">
            <span class="ul-modal-icon ul-badge is-indigo"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
            <div class="ul-modal-copy">
                <h6 id="consumer-register-title">Register consumer</h6>
                <p>Add a CIS account number and owner name to the consumer list.</p>
            </div>
            <button type="button" class="ul-modal-close" data-ul-modal-close aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('account.register') }}" class="ul-modal-body" autocomplete="off">
            @csrf
            <div class="ul-form-grid" style="padding: 0 0 1rem;">
                <div class="ul-field">
                    <label class="ti-form-label" for="register-account-no">Account No.</label>
                    <input id="register-account-no" type="text" name="account_no" class="ti-form-input" placeholder="Account number" required>
                </div>
                <div class="ul-field">
                    <label class="ti-form-label" for="register-consumer">Owner name</label>
                    <input id="register-consumer" type="text" name="consumer" class="ti-form-input" placeholder="Consumer name" required>
                </div>
            </div>
            <div class="ul-modal-footer">
                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" data-ul-modal-close>
                    <i class="bi bi-x-lg"></i>Cancel
                </button>
                <button type="submit" id="submit_btn" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                    <i class="bi bi-person-plus"></i>Create consumer
                </button>
            </div>
        </form>
    </div>
</div>
