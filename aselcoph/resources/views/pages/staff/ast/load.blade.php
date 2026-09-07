<x-app-layout>
    @php
        $presetCustomer = $presetCustomer ?? null;
        $presetAccount = $presetAccount ?? '';
        $presetWallets = $presetWallets ?? collect();
        $presetAccounts = $presetAccounts ?? collect();
        $presetAstBalance = (float) ($presetAstBalance ?? 0);
        $defaultAvatarUrl = asset('/user.png');
        $presetPhotoUrl = ($presetCustomer && filled($presetCustomer->profile_photo_path))
            ? asset('storage/'.ltrim((string) $presetCustomer->profile_photo_path, '/'))
            : $defaultAvatarUrl;
        $presetWalletUrl = $presetCustomer
            ? route('ast.admin.customer-wallet', $presetCustomer->id)
            : route('ast.admin.dashboard');
        $selectedPreset = $presetAccounts->firstWhere('account_number', $presetAccount);
        $selectedPresetBalance = (float) (is_array($selectedPreset) ? ($selectedPreset['balance'] ?? $presetAstBalance) : $presetAstBalance);
        $backUrl = $presetCustomer ? $presetWalletUrl : route('ast.admin.dashboard');
        $backLabel = $presetCustomer ? 'Wallet detail' : 'Dashboard';
        $formMode = in_array($formMode ?? 'load', ['load', 'reduce', 'set', 'request'], true) ? ($formMode ?? 'load') : 'load';
        $isRequest = $formMode === 'request';
        $pageTitle = match ($formMode) {
            'reduce' => 'Reduce AST',
            'set' => 'Set AST balance',
            'request' => 'Request AST',
            default => 'Load AST',
        };
    @endphp

    <x-slot name="title">{{ $pageTitle }}</x-slot>
    <x-slot name="subtitle">{{ $isRequest ? 'Ask a wallet loader to credit AST. The wallet is not credited until they approve.' : ($formMode === 'load' ? 'Credit AST to a customer wallet. Large loads may require a second staff approval.' : 'Correct a wallet by reducing AST or setting an exact remaining balance.') }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('ast.admin.dashboard') }}", "text": "AST Wallet"}</x-slot>
    <x-slot name="url_2">{"link": "{{ $isRequest ? route('ast.admin.request') : route('ast.admin.load') }}", "text": "{{ $isRequest ? 'Request AST' : 'Load AST' }}"}</x-slot>
    <x-slot name="active">{{ $presetCustomer?->name ?: $pageTitle }}</x-slot>
    @if($presetCustomer)
        <x-slot name="headerAvatar">
            <span class="page-header-card__avatar-face">
                <img
                    src="{{ $presetPhotoUrl }}"
                    alt="{{ $presetCustomer->name }}"
                    class="page-header-card__avatar-img"
                    onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                >
            </span>
        </x-slot>
    @endif
    <x-slot name="buttons">
        <a href="{{ $backUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>{{ $backLabel }}
        </a>
        @if($presetCustomer)
            @can('customers.view')
                <a href="{{ route('access.customers.show', $presetCustomer) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                    <i class="bi bi-person"></i>Customer profile
                </a>
            @endcan
        @endif
        <a href="{{ route('ast.admin.dashboard') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-grid"></i>Dashboard
        </a>
    </x-slot>

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-7 col-span-12 space-y-6">
            <div class="box ul-card ul-ast-form is-{{ $formMode }}" id="astFormCard">
                <div class="box-header ul-card-header ul-ast-form-head">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0" id="formCardTitle">
                            {{ $formMode === 'reduce' ? 'Reduce AST on this wallet' : ($formMode === 'set' ? 'Set an exact AST balance' : ($isRequest ? 'Request a load for this wallet' : 'Credit AST to customer wallet')) }}
                        </div>
                        <div class="td-hero-badges">
                            <span id="formCardBadge" class="ul-badge {{ $formMode === 'reduce' ? 'is-amber' : ($formMode === 'set' ? 'is-slate' : ($isRequest ? 'is-sky' : 'is-lime')) }}">
                                <i class="bi {{ $formMode === 'reduce' ? 'bi-dash-circle' : ($formMode === 'set' ? 'bi-sliders' : ($isRequest ? 'bi-send' : 'bi-plus-circle')) }}"></i>
                                {{ $formMode === 'reduce' ? 'Reduce' : ($formMode === 'set' ? 'Set balance' : ($isRequest ? 'Request' : 'Load')) }}
                            </span>
                            <x-unified.badge tone="lime" icon="bi-wallet2">1 AST ≈ ₱1</x-unified.badge>
                        </div>
                    </div>
                </div>

                <div class="ul-ast-form-modes" role="radiogroup" aria-label="AST action" @if($isRequest) hidden @endif>
                    <label class="ul-ast-mode{{ $formMode === 'load' ? ' is-active' : '' }}">
                        <input type="radio" name="ast_mode_ui" value="load" data-ast-mode="load" @checked($formMode === 'load')>
                        <span class="ul-ast-mode-icon is-lime" aria-hidden="true"><i class="bi bi-plus-circle"></i></span>
                        <span class="ul-ast-mode-copy">
                            <span class="ul-ast-mode-title">Load</span>
                            <span class="ul-ast-mode-hint">Credit AST to the selected account</span>
                        </span>
                    </label>
                    <label class="ul-ast-mode{{ $formMode === 'reduce' ? ' is-active' : '' }}">
                        <input type="radio" name="ast_mode_ui" value="reduce" data-ast-mode="reduce" @checked($formMode === 'reduce')>
                        <span class="ul-ast-mode-icon is-amber" aria-hidden="true"><i class="bi bi-dash-circle"></i></span>
                        <span class="ul-ast-mode-copy">
                            <span class="ul-ast-mode-title">Reduce</span>
                            <span class="ul-ast-mode-hint">Deduct AST from remaining balance</span>
                        </span>
                    </label>
                    <label class="ul-ast-mode{{ $formMode === 'set' ? ' is-active' : '' }}">
                        <input type="radio" name="ast_mode_ui" value="set" data-ast-mode="set" @checked($formMode === 'set')>
                        <span class="ul-ast-mode-icon is-slate" aria-hidden="true"><i class="bi bi-sliders"></i></span>
                        <span class="ul-ast-mode-copy">
                            <span class="ul-ast-mode-title">Set balance</span>
                            <span class="ul-ast-mode-hint">Write the exact remaining AST</span>
                        </span>
                    </label>
                </div>

                <div
                    id="selectedCustomerCard"
                    class="ul-ast-form-pick{{ $presetCustomer && $presetAccount !== '' ? '' : ' is-empty' }}"
                >
                    <img
                        id="pickPhoto"
                        class="ul-ast-form-photo"
                        src="{{ $presetCustomer ? $presetPhotoUrl : $defaultAvatarUrl }}"
                        alt=""
                        onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                    >
                    <div class="ul-ast-form-pick-copy">
                        <span id="pickName" class="ul-pick-name">{{ $presetCustomer?->name ?: 'Select a customer' }}</span>
                        <span id="pickEmail" class="ul-pick-meta">{{ $presetCustomer?->email ?: 'Search by name or account number' }}</span>
                        <span id="pickAccount" class="ul-pick-acct">{{ $presetAccount !== '' ? $presetAccount : 'No account selected' }}</span>
                    </div>
                    <span class="ul-ast-form-pick-amt">
                        <span class="ul-ast-form-pick-amt-label">Remaining</span>
                        <span id="pickBalance">{{ $presetCustomer ? number_format($selectedPresetBalance, 2).' AST' : '— AST' }}</span>
                    </span>
                </div>

                <div class="box-body ul-ast-form-body">
                    <div id="modeNoteRequest" @if(! $isRequest) hidden @endif>
                        <x-unified.note tone="sky" icon="bi-send" title="Support request">
                            This does not credit the wallet yet. A staff member with Load wallets permission reviews the request and loads AST if they approve.
                        </x-unified.note>
                    </div>
                    <div id="modeNoteLoad" @if($formMode !== 'load') hidden @endif>
                        <x-unified.note tone="lime" icon="bi-plus-circle" title="Instant credit">
                            AST is closed-loop credit. Loads are instant and irreversible once posted.
                            @if($makerCheckerEnabled)
                                Amounts of {{ number_format($approvalThreshold, 0) }} AST or more need a second staff approval.
                            @endif
                        </x-unified.note>
                    </div>
                    <div id="modeNoteReduce" @if($formMode !== 'reduce') hidden @endif>
                        <x-unified.note tone="amber" icon="bi-dash-circle" title="Manual reduction">
                            Deduct AST from the selected account. The balance cannot go below zero. A reason is required and is written to the audit trail.
                        </x-unified.note>
                    </div>
                    <div id="modeNoteSet" @if($formMode !== 'set') hidden @endif>
                        <x-unified.note tone="slate" icon="bi-sliders" title="Set exact balance">
                            Enter the remaining AST this account should have. The difference is posted as an adjustment. A reason is required.
                        </x-unified.note>
                    </div>

                    <div id="resultBanner" class="hidden mt-4"></div>

                    <form id="loadForm" class="ul-ast-form-fields" autocomplete="off" novalidate>
                        @csrf
                        <input type="hidden" id="formMode" name="mode" value="{{ $formMode }}">

                        <div class="ul-field">
                            <label class="ti-form-label required" for="accountSearch">Customer account</label>
                            <div class="td-search-wrap ul-ast-form-search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    id="accountSearch"
                                    class="ti-form-input"
                                    placeholder="Name, email, or account number"
                                    autocomplete="off"
                                    spellcheck="false"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="searchDropdown"
                                    value="{{ $presetCustomer && $presetAccount !== '' ? $presetCustomer->name.' — '.$presetAccount : '' }}"
                                    @if(! $presetCustomer) autofocus @endif
                                >
                                <button type="button" id="accountSearchClear" class="ul-ast-search-clear{{ $presetAccount === '' ? ' hidden' : '' }}" title="Clear" aria-label="Clear selected account">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                                <div id="searchDropdown" class="td-search-menu hidden" role="listbox"></div>
                            </div>
                            <input type="hidden" id="accountNumber" name="account_number" value="{{ $presetAccount }}">
                            <input type="hidden" id="customerId" value="{{ $presetCustomer?->id }}">
                            <p id="accountInfo" class="td-photo-hint" style="text-align: left;">
                                {{ $presetAccount !== '' ? 'Selected. Type again to pick a different account.' : 'Suggestions appear as you type. Use arrow keys, then Enter to select.' }}
                            </p>
                        </div>

                        @if($presetAccounts->count() > 1)
                            <div class="ul-field">
                                <label class="ti-form-label" for="accountSelect">This customer’s accounts</label>
                                <select id="accountSelect" class="ti-form-select">
                                    @foreach($presetAccounts as $account)
                                        <option
                                            value="{{ $account['account_number'] }}"
                                            data-balance="{{ number_format((float) $account['balance'], 2, '.', '') }}"
                                            @selected($presetAccount === $account['account_number'])
                                        >
                                            {{ $account['account_number'] }} · {{ number_format((float) $account['balance'], 2) }} AST
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="ul-field">
                            <label class="ti-form-label required" for="amount" id="amountLabel">{{ $formMode === 'reduce' ? 'Reduce by' : ($formMode === 'set' ? 'New balance' : 'Amount') }}</label>
                            <div class="ul-ast-amount-row">
                                <div class="ul-ast-amount">
                                    <span class="ul-ast-amount-peso" aria-hidden="true">₱</span>
                                    <input
                                        type="number"
                                        id="amount"
                                        name="amount"
                                        min="{{ $formMode === 'set' ? '0' : '0.01' }}"
                                        step="0.01"
                                        placeholder="0.00"
                                        required
                                    >
                                    <span class="td-amount-unit">AST</span>
                                </div>
                                <div class="ul-ast-chips" id="amountChips">
                                    @foreach([100, 500, 1000, 5000] as $chip)
                                        <button type="button" data-ast-chip="{{ number_format($chip, 2, '.', '') }}">₱{{ number_format($chip, 2) }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <p id="amountHintLoad" class="td-photo-hint" style="text-align: left;{{ ! in_array($formMode, ['load', 'request'], true) ? ' display:none;' : '' }}">
                                @if($isRequest)
                                    Requested amount. The wallet stays unchanged until a loader approves.
                                @elseif($makerCheckerEnabled)
                                    Loads of {{ number_format($approvalThreshold, 0) }} AST or more go to the approval queue.
                                @else
                                    Official credit for this electric account.
                                @endif
                            </p>
                            <p id="amountHintReduce" class="td-photo-hint" style="text-align: left;{{ $formMode !== 'reduce' ? ' display:none;' : '' }}">
                                Amount to deduct from the current remaining AST.
                            </p>
                            <p id="amountHintSet" class="td-photo-hint" style="text-align: left;{{ $formMode !== 'set' ? ' display:none;' : '' }}">
                                New remaining balance after this correction.
                            </p>
                        </div>

                        <div class="ul-field">
                            <label class="ti-form-label" for="referenceNo">Reference no.</label>
                            <input
                                type="text"
                                id="referenceNo"
                                name="reference_no"
                                class="ti-form-input"
                                maxlength="120"
                                placeholder="e.g. OR-2026-0001"
                            >
                            <p class="td-photo-hint" style="text-align: left;">Optional. Auto-generated if left blank.</p>
                        </div>

                        <div class="ul-field ul-ast-form-remarks">
                            <label class="ti-form-label{{ $formMode !== 'load' ? ' required' : '' }}" for="remarks" id="remarksLabel">{{ $formMode === 'load' ? 'Remarks' : 'Reason' }}</label>
                            <textarea
                                id="remarks"
                                name="remarks"
                                class="ti-form-input"
                                rows="2"
                                maxlength="500"
                                placeholder="{{ $isRequest ? 'Why this load is needed — required for the approver' : ($formMode === 'load' ? 'Internal note — not shown to the customer' : 'Required reason for this correction') }}"
                            ></textarea>
                        </div>
                    </form>
                </div>
                <div class="ul-card-footer ul-ast-form-footer">
                    <button type="submit" form="loadForm" id="submitBtn" class="ti-btn ti-btn-sm ul-btn ul-btn-primary">
                        <i class="bi bi-send"></i>{{ $formMode === 'reduce' ? 'Submit reduction' : ($formMode === 'set' ? 'Save balance' : ($isRequest ? 'Submit request' : 'Submit load')) }}
                    </button>
                    <button type="button" id="resetBtn" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel hidden">
                        <i class="bi bi-plus-lg"></i>Start another
                    </button>
                </div>
            </div>
        </div>

        <div class="xl:col-span-5 col-span-12 space-y-6">
            <section id="loadAstCard" class="ul-ast-card" aria-label="Customer AST wallet" @if(! $presetCustomer) hidden @endif>
                <div class="ul-ast-card-top">
                    <span class="ul-ast-card-badge">
                        <i class="bi bi-wallet2" aria-hidden="true"></i>
                        Wallet
                    </span>
                    <a id="loadAstChip" class="ul-ast-card-chip" href="{{ $presetWalletUrl }}">View</a>
                </div>
                <p class="ul-ast-card-label">Remaining AST</p>
                <p id="loadAstAmount" class="ul-ast-card-amount">{{ number_format($presetAstBalance, 2) }} AST</p>
                <p class="ul-ast-card-meta">ASELCO Token · 1 AST ≈ ₱1 · only payment method</p>
                @if($presetWallets->isNotEmpty())
                    <ul id="loadAstAccounts" class="ul-ast-card-accounts">
                        @foreach($presetWallets as $ast)
                            <li class="ul-ast-card-account">
                                <span class="ul-ast-card-account-icon" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                                <span class="ul-ast-card-account-copy">
                                    <span class="ul-ast-card-account-no">{{ $ast->account_number }}</span>
                                    <span class="ul-ast-card-account-meta">Electric account</span>
                                </span>
                                <span class="ul-ast-card-account-amt">{{ number_format((float) $ast->balance, 2) }} AST</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <ul id="loadAstAccounts" class="ul-ast-card-accounts"></ul>
                @endif
            </section>

            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title">How it works</div>
                </div>
                <div class="ul-step-list">
                    <div class="ul-step">
                        <span class="ul-step-mark">1</span>
                        <p>Search the customer by account number or name, then select the correct electric account.</p>
                    </div>
                    @if($isRequest)
                        <div class="ul-step">
                            <span class="ul-step-mark">2</span>
                            <p>Enter the AST amount and a reason (payment received, office load, correction, and so on).</p>
                        </div>
                        <div class="ul-step">
                            <span class="ul-step-mark">3</span>
                            <p>Submit the request. It appears in Load Requests. A staff member with Load wallets permission approves or rejects it.</p>
                        </div>
                        <div class="ul-step">
                            <span class="ul-step-mark"><i class="bi bi-shield-check"></i></span>
                            <p>The customer wallet is credited only after approval. You cannot approve your own request.</p>
                        </div>
                    @else
                        <div class="ul-step">
                            <span class="ul-step-mark">2</span>
                            <p>Choose Load, Reduce, or Set balance. Loads credit the wallet. Reduce deducts AST. Set writes the exact remaining balance.</p>
                        </div>
                        <div class="ul-step">
                            <span class="ul-step-mark">3</span>
                            <p>Confirm the change. Reductions and set-balance corrections need a reason. The submit button locks after the first click, and the backend also enforces idempotency.</p>
                        </div>
                        @if($makerCheckerEnabled)
                            <div class="ul-step">
                                <span class="ul-step-mark"><i class="bi bi-shield-check"></i></span>
                                <p>Loads of {{ number_format($approvalThreshold, 0) }} AST or more wait for a different staff member to approve before the wallet is credited.</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title">Audit trail</div>
                </div>
                <div class="box-body">
                    <x-unified.note tone="slate" icon="bi-shield-lock" title="Append-only">
                        Every load or adjustment records your user, role, IP, idempotency key, balances before and after, and a UTC timestamp. The same events appear on Wallet Detail.
                    </x-unified.note>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const defaultPhoto = @json($defaultAvatarUrl);
        const walletUrlTemplate = @json(route('ast.admin.customer-wallet', ['userId' => 0]));
        const approvalThreshold = {{ (float) $approvalThreshold }};
        const makerCheckerEnabled = @json((bool) $makerCheckerEnabled);
        const loadUrl = @json(route('ast.admin.load.submit'));
        const requestUrl = @json(route('ast.admin.request.submit'));
        const adjustUrl = @json(route('ast.admin.adjust.submit'));
        let currentMode = @json($formMode);
        const requestOnly = currentMode === 'request';
        let selectedBalance = {{ json_encode($selectedPresetBalance) }};

        const searchInput = document.getElementById('accountSearch');
        const dropdown = document.getElementById('searchDropdown');
        const accountField = document.getElementById('accountNumber');
        const customerIdField = document.getElementById('customerId');
        const accountInfo = document.getElementById('accountInfo');
        const accountSelect = document.getElementById('accountSelect');
        const selectedCard = document.getElementById('selectedCustomerCard');
        const pickPhoto = document.getElementById('pickPhoto');
        const pickName = document.getElementById('pickName');
        const pickEmail = document.getElementById('pickEmail');
        const pickAccount = document.getElementById('pickAccount');
        const pickBalance = document.getElementById('pickBalance');
        const loadAstCard = document.getElementById('loadAstCard');
        const loadAstAmount = document.getElementById('loadAstAmount');
        const loadAstChip = document.getElementById('loadAstChip');
        const loadAstAccounts = document.getElementById('loadAstAccounts');

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            }[char]));
        }

        function formatAst(value) {
            const amount = Number(value || 0);
            return amount.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' AST';
        }

        function walletUrl(id) {
            return walletUrlTemplate.replace('/0', '/' + String(id));
        }

        function setMode(mode) {
            if (requestOnly) {
                currentMode = 'request';
                return;
            }
            currentMode = ['load', 'reduce', 'set'].includes(mode) ? mode : 'load';
            document.getElementById('formMode').value = currentMode;
            document.querySelectorAll('[data-ast-mode]').forEach((input) => {
                input.checked = input.dataset.astMode === currentMode;
                input.closest('.ul-ast-mode')?.classList.toggle('is-active', input.checked);
            });
            document.getElementById('modeNoteLoad').hidden = currentMode !== 'load';
            document.getElementById('modeNoteReduce').hidden = currentMode !== 'reduce';
            document.getElementById('modeNoteSet').hidden = currentMode !== 'set';
            document.getElementById('amountHintLoad').style.display = currentMode === 'load' ? '' : 'none';
            document.getElementById('amountHintReduce').style.display = currentMode === 'reduce' ? '' : 'none';
            document.getElementById('amountHintSet').style.display = currentMode === 'set' ? '' : 'none';

            const titles = {
                load: 'Credit AST to customer wallet',
                reduce: 'Reduce AST on this wallet',
                set: 'Set an exact AST balance',
            };
            const amountLabels = { load: 'Amount', reduce: 'Reduce by', set: 'New balance' };
            const submitLabels = { load: 'Submit load', reduce: 'Submit reduction', set: 'Save balance' };
            document.getElementById('formCardTitle').textContent = titles[currentMode];
            const card = document.getElementById('astFormCard');
            card?.classList.remove('is-load', 'is-reduce', 'is-set');
            card?.classList.add('is-' + currentMode);
            const badge = document.getElementById('formCardBadge');
            if (badge) {
                const badgeUi = {
                    load: { tone: 'is-lime', icon: 'bi-plus-circle', text: 'Load' },
                    reduce: { tone: 'is-amber', icon: 'bi-dash-circle', text: 'Reduce' },
                    set: { tone: 'is-slate', icon: 'bi-sliders', text: 'Set balance' },
                }[currentMode];
                badge.className = `ul-badge ${badgeUi.tone}`;
                badge.innerHTML = `<i class="bi ${badgeUi.icon}"></i>${badgeUi.text}`;
            }
            document.getElementById('amountLabel').textContent = amountLabels[currentMode];
            document.getElementById('amount').min = currentMode === 'set' ? '0' : '0.01';
            const remarksLabel = document.getElementById('remarksLabel');
            remarksLabel.classList.toggle('required', currentMode !== 'load');
            remarksLabel.textContent = currentMode === 'load' ? 'Remarks' : 'Reason';
            document.getElementById('remarks').placeholder = currentMode === 'load'
                ? 'Internal note — not shown to the customer'
                : 'Required reason for this correction';
            submitBtn.innerHTML = `<i class="bi bi-send"></i>${submitLabels[currentMode]}`;
            const url = new URL(window.location.href);
            if (currentMode === 'load') {
                url.searchParams.delete('mode');
            } else {
                url.searchParams.set('mode', currentMode);
            }
            window.history.replaceState({}, '', url);
        }

        function showSelected(customer) {
            if (!customer?.account_number) {
                selectedCard.hidden = false;
                selectedCard.classList.add('is-empty');
                pickName.textContent = 'Select a customer';
                pickEmail.textContent = 'Search by name or account number';
                pickAccount.textContent = 'No account selected';
                pickBalance.textContent = '— AST';
                pickPhoto.src = defaultPhoto;
                loadAstCard.hidden = true;
                selectedBalance = 0;
                return;
            }

            accountField.value = customer.account_number;
            if (customerIdField) {
                customerIdField.value = customer.id || '';
            }
            searchInput.value = `${customer.name || 'Customer'} — ${customer.account_number}`;
            accountInfo.textContent = 'Selected. Type again to pick a different account.';
            setClearVisible(true);

            pickPhoto.src = customer.photo || defaultPhoto;
            pickName.textContent = customer.name || 'Customer';
            pickEmail.textContent = customer.email || '';
            pickAccount.textContent = customer.account_number;
            pickBalance.textContent = formatAst(customer.balance);
            selectedBalance = Number(customer.balance || 0);
            selectedCard.hidden = false;
            selectedCard.classList.remove('is-empty');

            loadAstAmount.textContent = formatAst(customer.balance);
            loadAstChip.href = customer.id ? walletUrl(customer.id) : '{{ route('ast.admin.dashboard') }}';
            loadAstAccounts.innerHTML = `
                <li class="ul-ast-card-account">
                    <span class="ul-ast-card-account-icon" aria-hidden="true"><i class="bi bi-lightning-charge"></i></span>
                    <span class="ul-ast-card-account-copy">
                        <span class="ul-ast-card-account-no">${escapeHtml(customer.account_number)}</span>
                        <span class="ul-ast-card-account-meta">Electric account</span>
                    </span>
                    <span class="ul-ast-card-account-amt">${escapeHtml(formatAst(customer.balance))}</span>
                </li>
            `;
            loadAstCard.hidden = false;
        }

        const searchClear = document.getElementById('accountSearchClear');
        const searchUrl = @json(route('ast.admin.customer-search'));
        let debounceTimer;
        let searchResults = [];
        let activeIndex = -1;
        let lastQuery = '';

        function setSearchOpen(open) {
            dropdown.classList.toggle('hidden', !open);
            searchInput.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function setClearVisible(show) {
            searchClear?.classList.toggle('hidden', !show);
        }

        function customerFromItem(item) {
            return {
                id: item.dataset.id,
                name: item.dataset.name,
                email: item.dataset.email,
                photo: item.dataset.photo,
                balance: item.dataset.balance,
                account_number: item.dataset.account,
            };
        }

        function highlightActive() {
            const items = dropdown.querySelectorAll('[data-account]');
            items.forEach((item, index) => {
                item.classList.toggle('is-active', index === activeIndex);
                if (index === activeIndex) {
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function pickItem(item) {
            if (!item) {
                return;
            }
            showSelected(customerFromItem(item));
            setSearchOpen(false);
            setClearVisible(true);
            document.getElementById('amount')?.focus();
        }

        function renderResults(rows, query) {
            searchResults = rows || [];
            activeIndex = searchResults.length ? 0 : -1;
            if (!searchResults.length) {
                dropdown.innerHTML = `<p class="td-search-empty">No customer matches “${escapeHtml(query)}”.</p>`;
                setSearchOpen(true);
                return;
            }

            dropdown.innerHTML = searchResults.map((customer, index) => `
                <button type="button" class="td-search-item${index === 0 ? ' is-active' : ''}" role="option"
                     data-account="${escapeHtml(customer.account_number ?? '')}"
                     data-name="${escapeHtml(customer.name ?? '')}"
                     data-email="${escapeHtml(customer.email ?? '')}"
                     data-photo="${escapeHtml(customer.photo ?? defaultPhoto)}"
                     data-balance="${escapeHtml(customer.balance ?? 0)}"
                     data-id="${escapeHtml(customer.id)}">
                    <img class="td-search-item-photo" src="${escapeHtml(customer.photo ?? defaultPhoto)}" alt="" onerror="this.onerror=null;this.src='${defaultPhoto}'">
                    <span class="td-search-item-copy">
                        <span class="td-search-item-name">${escapeHtml(customer.name ?? '—')}</span>
                        <span class="td-search-item-meta">${escapeHtml(customer.email || customer.owner_name || '')}</span>
                    </span>
                    <span class="td-search-item-side">
                        <span class="td-search-item-acct">${escapeHtml(customer.account_number ?? '—')}</span>
                        <span class="td-search-item-bal">${escapeHtml(formatAst(customer.balance))}</span>
                    </span>
                </button>
            `).join('');
            setSearchOpen(true);

            const exact = searchResults.find((row) => String(row.account_number || '').toLowerCase() === query.toLowerCase());
            if (exact && searchResults.length === 1) {
                pickItem(dropdown.querySelector('[data-account]'));
            }
        }

        async function runSearch(query) {
            const q = query.trim();
            const minLength = /^\d/.test(q) ? 1 : 2;
            if (q.length < minLength) {
                setSearchOpen(false);
                return;
            }
            if (q === lastQuery && searchResults.length) {
                setSearchOpen(true);
                highlightActive();
                return;
            }

            lastQuery = q;
            dropdown.innerHTML = '<p class="td-search-empty">Searching…</p>';
            setSearchOpen(true);

            try {
                const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const json = await res.json();
                if (searchInput.value.trim() !== q) {
                    return;
                }
                renderResults(json.data || [], q);
            } catch (err) {
                dropdown.innerHTML = '<p class="td-search-empty">Could not search right now. Try again.</p>';
                setSearchOpen(true);
            }
        }

        searchInput.addEventListener('input', function () {
            const q = this.value.trim();
            const selectedLabel = accountField.value
                ? `${pickName.textContent} — ${accountField.value}`
                : '';
            if (this.value.trim() !== selectedLabel.trim()) {
                accountField.value = '';
                accountInfo.textContent = 'Suggestions appear as you type. Use arrow keys, then Enter to select.';
            }
            setClearVisible(q.length > 0);
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => runSearch(q), 150);
        });

        searchInput.addEventListener('focus', function () {
            const q = this.value.trim();
            if (q.length >= 1 && !accountField.value) {
                runSearch(q);
            } else if (searchResults.length && !accountField.value) {
                setSearchOpen(true);
            }
        });

        searchInput.addEventListener('keydown', function (event) {
            const items = dropdown.querySelectorAll('[data-account]');
            const open = !dropdown.classList.contains('hidden');
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!open) {
                    runSearch(this.value);
                    return;
                }
                activeIndex = items.length ? (activeIndex + 1) % items.length : -1;
                highlightActive();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                activeIndex = items.length ? (activeIndex <= 0 ? items.length - 1 : activeIndex - 1) : -1;
                highlightActive();
            } else if (event.key === 'Enter' && open && items.length) {
                event.preventDefault();
                pickItem(items[Math.max(0, activeIndex)]);
            } else if (event.key === 'Escape') {
                setSearchOpen(false);
            }
        });

        dropdown.addEventListener('click', function (event) {
            pickItem(event.target.closest('[data-account]'));
        });

        dropdown.addEventListener('mousemove', function (event) {
            const item = event.target.closest('[data-account]');
            if (!item) {
                return;
            }
            const items = Array.from(dropdown.querySelectorAll('[data-account]'));
            activeIndex = items.indexOf(item);
            highlightActive();
        });

        searchClear?.addEventListener('click', function () {
            searchInput.value = '';
            accountField.value = '';
            lastQuery = '';
            searchResults = [];
            accountInfo.textContent = 'Suggestions appear as you type. Use arrow keys, then Enter to select.';
            setClearVisible(false);
            setSearchOpen(false);
            showSelected(null);
            searchInput.focus();
        });

        document.addEventListener('click', function (event) {
            if (!dropdown.contains(event.target) && event.target !== searchInput && event.target !== searchClear) {
                setSearchOpen(false);
            }
        });

        accountSelect?.addEventListener('change', function () {
            const option = this.selectedOptions[0];
            showSelected({
                id: customerIdField?.value,
                name: @json($presetCustomer?->name),
                email: @json($presetCustomer?->email),
                photo: pickPhoto.src,
                balance: option?.dataset.balance || 0,
                account_number: this.value,
            });
        });

        const form = document.getElementById('loadForm');
        const submitBtn = document.getElementById('submitBtn');
        const resetBtn = document.getElementById('resetBtn');
        const banner = document.getElementById('resultBanner');

        document.querySelectorAll('[data-ast-mode]').forEach((input) => {
            input.addEventListener('change', () => {
                if (input.checked) {
                    setMode(input.dataset.astMode);
                }
            });
        });
        function formatAmountInput(input) {
            if (!input || input.value === '') {
                return;
            }
            const value = Number(input.value);
            if (Number.isNaN(value)) {
                return;
            }
            input.value = value.toFixed(2);
        }

        document.querySelectorAll('[data-ast-chip]').forEach((button) => {
            button.addEventListener('click', () => {
                const amountInput = document.getElementById('amount');
                amountInput.value = button.getAttribute('data-ast-chip');
                formatAmountInput(amountInput);
                amountInput.focus();
            });
        });
        document.getElementById('amount')?.addEventListener('blur', function () {
            formatAmountInput(this);
        });
        setMode(currentMode);

        function generateUUID() {
            return ([1e7]+-1e3+-4e3+-8e3+-1e11).replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            );
        }

        let idempotencyKey = generateUUID();

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const accountNumber = accountField.value.trim();
            const amount = document.getElementById('amount').value.trim();
            const referenceNo = document.getElementById('referenceNo').value.trim();
            const remarks = document.getElementById('remarks').value.trim();
            const amountValue = parseFloat(amount);

            if (!accountNumber) {
                showBanner('error', 'Please select a customer account from the search results.');
                return;
            }
            if (currentMode === 'set') {
                if (amount === '' || Number.isNaN(amountValue) || amountValue < 0) {
                    showBanner('error', 'Please enter a valid new balance of zero or more.');
                    return;
                }
            } else if (!amount || amountValue <= 0) {
                showBanner('error', 'Please enter a valid amount greater than zero.');
                return;
            }
            if (currentMode !== 'load' && remarks.length < 5) {
                showBanner('error', 'Please enter a reason of at least 5 characters.');
                return;
            }

            const needsApproval = currentMode === 'load' && makerCheckerEnabled && amountValue >= approvalThreshold;
            const confirmCopy = (() => {
                if (currentMode === 'reduce') {
                    return {
                        verb: 'Reduce',
                        title: 'Reduce AST',
                        text: `Deduct ${formatAst(amountValue)} from account ${accountNumber}? Remaining would be ${formatAst(Math.max(0, selectedBalance - amountValue))}.`,
                        tone: 'danger',
                        icon: 'bi-dash-circle',
                    };
                }
                if (currentMode === 'set') {
                    return {
                        verb: 'Set balance',
                        title: 'Set AST balance',
                        text: `Change account ${accountNumber} from ${formatAst(selectedBalance)} to ${formatAst(amountValue)}?`,
                        tone: amountValue < selectedBalance ? 'danger' : 'primary',
                        icon: 'bi-sliders',
                    };
                }
                if (currentMode === 'request') {
                    return {
                        verb: 'Submit request',
                        title: 'Request AST load',
                        text: `Ask a wallet loader to credit ${formatAst(amountValue)} to account ${accountNumber}? The wallet stays unchanged until they approve.`,
                        tone: 'primary',
                        icon: 'bi-send',
                    };
                }
                return {
                    verb: needsApproval ? 'Queue load' : 'Load',
                    title: needsApproval ? 'Queue AST load' : 'Load AST',
                    text: needsApproval
                        ? `Queue ${formatAst(amountValue)} to account ${accountNumber} for second-staff approval?`
                        : `Credit ${formatAst(amountValue)} to account ${accountNumber}? This is instant and irreversible once posted.`,
                    tone: 'primary',
                    icon: needsApproval ? 'bi-shield-check' : 'bi-wallet2',
                };
            })();
            const confirmed = await window.ulConfirm(confirmCopy);
            if (!confirmed) {
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting…';
            banner.classList.add('hidden');

            try {
                const isRequest = currentMode === 'request';
                const isAdjust = currentMode !== 'load' && !isRequest;
                const res = await fetch(isRequest ? requestUrl : (isAdjust ? adjustUrl : loadUrl), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        account_number: accountNumber,
                        amount: amount,
                        mode: currentMode === 'reduce' ? 'debit' : (currentMode === 'set' ? 'set' : 'credit'),
                        reference_no: referenceNo,
                        remarks: remarks,
                        idempotency_key: idempotencyKey,
                    }),
                });

                const json = await res.json();

                if (res.status === 201 || res.status === 200) {
                    if (json.idempotent) {
                        showBanner('warning',
                            `<strong>Duplicate detected.</strong> This request was already recorded with reference <code>${escapeHtml(json.reference)}</code>. No new change was applied.`
                        );
                    } else if (isRequest) {
                        showBanner('success',
                            `<strong>Request submitted.</strong> ${escapeHtml(formatAst(json.amount))} for account <strong>${escapeHtml(json.account_number)}</strong> is waiting for a wallet loader. ` +
                            `Reference: <code>${escapeHtml(json.reference)}</code>.`
                        );
                    } else if (isAdjust) {
                        const verb = json.direction === 'debit' ? 'removed from' : 'applied to';
                        showBanner('success',
                            `<strong>AST adjusted.</strong> ${escapeHtml(formatAst(json.amount))} ${verb} account <strong>${escapeHtml(json.account_number)}</strong>. ` +
                            `Reference: <code>${escapeHtml(json.reference)}</code>. New balance: <strong>${escapeHtml(formatAst(json.balance_after))}</strong>.`
                        );
                    } else {
                        showBanner('success',
                            `<strong>AST loaded.</strong> ${escapeHtml(formatAst(json.amount))} credited to account <strong>${escapeHtml(json.account_number)}</strong>. ` +
                            `Reference: <code>${escapeHtml(json.reference)}</code>. New balance: <strong>${escapeHtml(formatAst(json.balance_after))}</strong>.`
                        );
                    }
                    form.classList.add('hidden');
                    submitBtn.classList.add('hidden');
                    resetBtn.classList.remove('hidden');
                } else {
                    showBanner('error', json.message ?? 'An unexpected error occurred. Please try again.');
                    submitBtn.disabled = false;
                    setMode(currentMode);
                    submitBtn.disabled = false;
                    idempotencyKey = generateUUID();
                }
            } catch (err) {
                showBanner('error', 'Network error. Please check your connection and try again.');
                setMode(currentMode);
                submitBtn.disabled = false;
                idempotencyKey = generateUUID();
            }
        });

        resetBtn.addEventListener('click', function () {
            form.reset();
            form.classList.remove('hidden');
            banner.classList.add('hidden');
            resetBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
            submitBtn.disabled = false;
            setMode(currentMode);
            accountField.value = '';
            lastQuery = '';
            searchResults = [];
            accountInfo.textContent = 'Suggestions appear as you type. Use arrow keys, then Enter to select.';
            setClearVisible(false);
            showSelected(null);
            loadAstCard.hidden = true;
            idempotencyKey = generateUUID();
        });

        function showBanner(type, html) {
            const map = {
                success: 'alert alert-success mb-4',
                error: 'alert alert-danger mb-4',
                warning: 'alert alert-warning mb-4',
            };
            banner.className = map[type] ?? 'alert alert-info mb-4';
            banner.innerHTML = html;
            banner.classList.remove('hidden');
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    })();
    </script>
    @endpush
</x-app-layout>
