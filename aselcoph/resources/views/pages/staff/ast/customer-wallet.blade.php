<x-app-layout>
    @php
        $hasProfilePhoto = filled($customer->profile_photo_path);
        $defaultAvatarUrl = asset('/user.png');
        $profilePhotoUrl = $hasProfilePhoto
            ? asset('storage/'.ltrim((string) $customer->profile_photo_path, '/'))
            : $defaultAvatarUrl;
        $customerUrl = route('access.customers.show', $customer);
        $loadUrl = route('ast.admin.load', ['customer' => $customer->id]);
        $requestUrl = route('ast.admin.request', ['customer' => $customer->id]);
        $adjustUrl = route('ast.admin.load', ['customer' => $customer->id, 'mode' => 'reduce']);
        $setUrl = route('ast.admin.load', ['customer' => $customer->id, 'mode' => 'set']);
        $displayBalance = $astBalance ?? (float) ($wallet->balance ?? 0);
        $wallets = $wallets ?? collect();
        $loadCount = (int) ($ledgerStats['load']->total ?? 0);
        $payCount = (int) ($ledgerStats['pay']->total ?? 0);
        $txnCount = $entries?->count() ?? 0;
    @endphp

    <x-slot name="title">{{ $customer->name }}</x-slot>
    <x-slot name="subtitle">AST wallet detail for {{ $customer->email }}.</x-slot>
    <x-slot name="url_1">{"link": "{{ route('ast.admin.dashboard') }}", "text": "AST Wallet"}</x-slot>
    <x-slot name="url_2">{"link": "{{ url()->current() }}", "text": "Wallet Detail"}</x-slot>
    <x-slot name="active">{{ $customer->name }}</x-slot>
    <x-slot name="headerAvatar">
        <span class="page-header-card__avatar-face">
            <img
                src="{{ $profilePhotoUrl }}"
                alt="{{ $customer->name }}"
                class="page-header-card__avatar-img"
                onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
            >
        </span>
    </x-slot>
    <x-slot name="buttons">
        @can('customers.view')
            <a href="{{ $customerUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
                <i class="bi bi-arrow-left"></i>Customer profile
            </a>
        @else
            <a href="{{ route('ast.admin.dashboard') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
                <i class="bi bi-arrow-left"></i>Dashboard
            </a>
        @endcan
        <a href="{{ $requestUrl }}" class="ti-btn ti-btn-sm ul-btn {{ $canLoad ? 'ul-btn-cancel' : 'ul-btn-view' }}">
            <i class="bi bi-send"></i>Request AST
        </a>
        @if($canLoad)
            <a href="{{ $loadUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                <i class="bi bi-plus-lg"></i>Load AST
            </a>
            <a href="{{ $adjustUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-edit">
                <i class="bi bi-sliders"></i>Adjust AST
            </a>
        @endif
        @can('customers.view')
            <a href="{{ route('ast.admin.dashboard') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-grid"></i>Dashboard
            </a>
        @endcan
    </x-slot>

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-3">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">{{ $customer->name }}</div>
                        <div class="td-hero-badges">
                            <x-unified.badge tone="sky" icon="bi-person">Customer</x-unified.badge>
                            <x-unified.badge
                                :tone="\App\Support\AccessUi::accountStatusTone($customer->account_status)"
                                :icon="\App\Support\AccessUi::accountStatusIcon($customer->account_status)"
                            >{{ \App\Support\AccessUi::accountStatusLabel($customer->account_status) }}</x-unified.badge>
                            @if($customer->email_verified_at)
                                <x-unified.badge tone="lime" icon="bi-envelope-check">Verified</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-envelope">Unverified</x-unified.badge>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Email</th>
                                    <td>
                                        <div class="font-semibold">{{ $customer->email }}</div>
                                        <div class="ul-date-meta">{{ $customer->email_verified_at?->timezone('Asia/Manila')?->format('M d, Y h:i A') ?: 'Not verified' }}</div>
                                    </td>
                                    <th>Mobile</th>
                                    <td>{{ $customer->contact_no ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <th>Wallets</th>
                                    <td>{{ $wallets->count() }} linked account{{ $wallets->count() === 1 ? '' : 's' }}</td>
                                    <th>Remaining AST</th>
                                    <td class="font-semibold">{{ number_format($displayBalance, 2) }} AST</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6" data-ul-table-filter>
            <x-unified.toolbar
                local
                :action="route('ast.admin.customer-wallet', $customer->id)"
                :reset-url="route('ast.admin.customer-wallet', $customer->id)"
                search-placeholder="Search reference, account, or type…"
                :active-filter-count="0"
            >
                <x-slot:filters>
                    <div>
                        <label class="ti-form-label">Type</label>
                        <select name="type" class="ti-form-select" data-ul-filter="type">
                            <option value="">All</option>
                            @foreach(['load' => 'Load', 'pay' => 'Payment', 'adjust' => 'Adjust', 'reversal' => 'Reversal'] as $type => $label)
                                <option value="{{ $type }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-slot:filters>
            </x-unified.toolbar>

            <x-unified.table
                title="Transaction history"
                data-ul-filter-table
                :items="$entries ?? collect()"
                empty-title="No transactions on record."
                empty-text="Loads and payments for this wallet will appear here."
                :colspan="7"
            >
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Type</th>
                    <th>Account</th>
                    <th>Reference</th>
                    <th class="text-end">Amount</th>
                    <th>Performed by</th>
                    <th>When</th>
                </x-slot:head>
                @if($entries)
                    @foreach($entries as $entry)
                        @php
                            $typeMap = [
                                'load' => ['label' => 'Load', 'tone' => 'lime', 'icon' => 'bi-plus-circle'],
                                'pay' => ['label' => 'Payment', 'tone' => 'sky', 'icon' => 'bi-credit-card'],
                                'adjust' => ['label' => 'Adjust', 'tone' => 'slate', 'icon' => 'bi-sliders'],
                                'reversal' => ['label' => 'Reversal', 'tone' => 'amber', 'icon' => 'bi-arrow-counterclockwise'],
                            ];
                            $t = $typeMap[$entry->type] ?? ['label' => ucfirst((string) $entry->type), 'tone' => 'slate', 'icon' => 'bi-circle'];
                            $isCredit = in_array($entry->type, ['load', 'reversal'], true)
                                || ($entry->type === 'adjust' && (($entry->meta['direction'] ?? '') === 'credit'));
                        @endphp
                        <x-unified.row
                            class="ul-row"
                            data-ul-row
                            data-type="{{ $entry->type }}"
                            data-ul-search="{{ trim(($entry->type ?? '').' '.($t['label'] ?? '').' '.($entry->reference ?? '').' '.($entry->wallet?->account_number ?? '').' '.($entry->creator?->name ?? '').' '.($entry->meta['reason'] ?? '')) }}"
                        >
                            <x-unified.td-num :index="$loop->iteration" />
                            <td>
                                <x-unified.badge :tone="$t['tone']" :icon="$t['icon']">{{ $t['label'] }}</x-unified.badge>
                                <div class="ul-date-meta">
                                    After {{ number_format((float) $entry->balance_after, 2) }} AST
                                    @if($entry->type === 'adjust' && filled($entry->meta['reason'] ?? null))
                                        · {{ $entry->meta['reason'] }}
                                    @endif
                                </div>
                            </td>
                            <td class="font-semibold">{{ $entry->wallet?->account_number ?: '—' }}</td>
                            <td>
                                <div class="font-semibold">{{ $entry->reference ?: '—' }}</div>
                            </td>
                            <td class="text-end font-semibold">
                                {{ $isCredit ? '+' : '−' }}{{ number_format((float) $entry->amount, 2) }}
                            </td>
                            <td>
                                <div class="font-semibold">{{ $entry->creator?->name ?: 'System' }}</div>
                                @if($entry->creator?->role)
                                    <div class="ul-date-meta">{{ $entry->creator->role }}</div>
                                @endif
                            </td>
                            <td class="ul-date">
                                {{ optional($entry->created_at)->timezone('Asia/Manila')?->format('M d, Y') }}
                                <div class="ul-date-meta">{{ optional($entry->created_at)->timezone('Asia/Manila')?->format('h:i A') }}</div>
                            </td>
                        </x-unified.row>
                    @endforeach
                @endif
            </x-unified.table>

            <x-unified.table
                title="Audit trail"
                data-ul-filter-table
                :items="$auditLogs"
                :colspan="6"
                empty-title="No audit events."
                empty-text="Wallet loads and adjustments for this customer will appear here."
            >
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Action</th>
                    <th class="text-end">Amount</th>
                    <th>Actor</th>
                    <th>IP</th>
                    <th>When</th>
                </x-slot:head>
                @foreach($auditLogs as $log)
                    <tr
                        class="ul-row"
                        data-ul-row
                        data-type="{{ $log->action === 'load' ? 'load' : ($log->action === 'pay' ? 'pay' : ($log->action === 'adjust' ? 'adjust' : '')) }}"
                        data-ul-search="{{ trim(($log->action ?? '').' '.\App\Support\AccessUi::label($log->action).' '.($log->wallet?->account_number ?? '').' '.($log->actor?->name ?? '').' '.($log->ip_address ?? '')) }}"
                    >
                        <td class="ul-row-num"><span class="ul-row-num-value">{{ $loop->iteration }}.</span></td>
                        <td>
                            <div class="font-semibold">{{ \App\Support\AccessUi::label($log->action) }}</div>
                            <div class="ul-date-meta">{{ $log->wallet?->account_number ?: '—' }} · after {{ number_format((float) $log->balance_after, 2) }}</div>
                        </td>
                        <td class="text-end font-semibold">{{ number_format((float) $log->amount, 2) }}</td>
                        <td>
                            <div class="font-semibold">{{ $log->actor?->name ?: 'System' }}</div>
                            <div class="ul-date-meta">{{ $log->actor_role ?: '—' }}</div>
                        </td>
                        <td>{{ $log->ip_address ?: '—' }}</td>
                        <td class="ul-date">
                            {{ optional($log->created_at)->timezone('Asia/Manila')?->format('M d, Y') }}
                            <div class="ul-date-meta">{{ optional($log->created_at)->timezone('Asia/Manila')?->format('h:i A') }}</div>
                        </td>
                    </tr>
                @endforeach
            </x-unified.table>
            </div>
        </div>

        <div class="xl:col-span-4 col-span-12 space-y-6">
            <section class="ul-ast-card" aria-label="Remaining ASELCO Tokens">
                <div class="ul-ast-card-top">
                    <span class="ul-ast-card-badge">
                        <i class="bi bi-wallet2" aria-hidden="true"></i>
                        Wallet
                    </span>
                    @if($canLoad)
                        <a class="ul-ast-card-chip" href="{{ $loadUrl }}">Load</a>
                    @endif
                </div>
                <p class="ul-ast-card-label">Remaining AST</p>
                <p class="ul-ast-card-amount">{{ number_format($displayBalance, 2) }} AST</p>
                <p class="ul-ast-card-meta">
                    ASELCO Token · 1 AST ≈ ₱1 · only payment method
                </p>
                @if($wallets->isNotEmpty())
                    <ul class="ul-ast-card-accounts">
                        @foreach($wallets as $ast)
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
                @endif
                <div class="ul-ast-card-actions">
                    @if($canLoad)
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $loadUrl }}">
                            <i class="bi bi-plus-lg"></i>Load AST
                        </a>
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $adjustUrl }}">
                            <i class="bi bi-sliders"></i>Adjust
                        </a>
                    @endif
                    @can('customers.view')
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $customerUrl }}">
                            <i class="bi bi-person"></i>Profile
                        </a>
                    @endcan
                </div>
            </section>

            @if($wallets->isEmpty())
                <div class="box ul-card">
                    <div class="box-body">
                        <p class="td-photo-hint" style="text-align: left;">This customer does not have an AST wallet yet. A wallet is created automatically on the first load.</p>
                    </div>
                </div>
            @endif

            <div class="ul-kpi-grid">
                <div class="ul-kpi is-green">
                    <span class="ul-kpi-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($displayBalance, 2) }}</span>
                        <span class="ul-kpi-label">AST</span>
                        <span class="ul-kpi-hint">Token balance</span>
                    </span>
                </div>
                <div class="ul-kpi is-cyan">
                    <span class="ul-kpi-icon"><i class="bi bi-plug"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $wallets->count() }}</span>
                        <span class="ul-kpi-label">Accounts</span>
                        <span class="ul-kpi-hint">Linked wallets</span>
                    </span>
                </div>
                <div class="ul-kpi is-indigo">
                    <span class="ul-kpi-icon"><i class="bi bi-receipt"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $txnCount }}</span>
                        <span class="ul-kpi-label">Transactions</span>
                        <span class="ul-kpi-hint">All activity</span>
                    </span>
                </div>
                <div class="ul-kpi is-lime">
                    <span class="ul-kpi-icon"><i class="bi bi-plus-circle"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $loadCount }}</span>
                        <span class="ul-kpi-label">Loads</span>
                        <span class="ul-kpi-hint">Credits posted</span>
                    </span>
                </div>
                <div class="ul-kpi is-sky">
                    <span class="ul-kpi-icon"><i class="bi bi-credit-card"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $payCount }}</span>
                        <span class="ul-kpi-label">Payments</span>
                        <span class="ul-kpi-hint">Bills paid</span>
                    </span>
                </div>
                <div class="ul-kpi is-amber">
                    <span class="ul-kpi-icon"><i class="bi bi-shield-lock"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ $auditLogs->count() }}</span>
                        <span class="ul-kpi-label">Audit</span>
                        <span class="ul-kpi-hint">Latest events</span>
                    </span>
                </div>
            </div>

            <div class="box ul-card">
                <div class="box-header ul-card-header"><div class="box-title ul-card-title">Quick actions</div></div>
                <div class="ul-qa-grid">
                    @if($canLoad)
                        <a class="ul-qa" href="{{ $loadUrl }}">
                            <span class="ul-qa-icon"><i class="bi bi-plus-lg"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Load AST</span>
                                <span class="ul-qa-hint">Credit this wallet</span>
                            </span>
                        </a>
                        <a class="ul-qa is-orange" href="{{ $adjustUrl }}">
                            <span class="ul-qa-icon"><i class="bi bi-dash-lg"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Reduce AST</span>
                                <span class="ul-qa-hint">Deduct from this wallet</span>
                            </span>
                        </a>
                        <a class="ul-qa is-slate" href="{{ $setUrl }}">
                            <span class="ul-qa-icon"><i class="bi bi-sliders"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Set balance</span>
                                <span class="ul-qa-hint">Set the remaining AST</span>
                            </span>
                        </a>
                    @endif
                    @can('customers.view')
                        <a class="ul-qa is-sky" href="{{ $customerUrl }}">
                            <span class="ul-qa-icon"><i class="bi bi-person"></i></span>
                            <span class="ul-qa-copy">
                                <span class="ul-qa-title">Customer profile</span>
                                <span class="ul-qa-hint">Open account details</span>
                            </span>
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
