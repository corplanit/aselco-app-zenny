<x-app-layout>
    @php
        $canLoad = Auth::user()->canLoadWallet();
        $defaultAvatarUrl = asset('/user.png');
        $todayLabel = now('Asia/Manila')->format('M d, Y');
        $monthLabel = now('Asia/Manila')->format('F Y');
        $loadUrl = route('ast.admin.load');
        $requestUrl = route('ast.admin.request');
        $reduceUrl = route('ast.admin.load', ['mode' => 'reduce']);
        $setUrl = route('ast.admin.load', ['mode' => 'set']);
        $approvalsUrl = route('ast.admin.load-requests');
    @endphp

    <x-slot name="title">AST Wallet</x-slot>
    <x-slot name="subtitle">Circulation, loads, and approvals across customer wallets. 1 AST ≈ ₱1.</x-slot>
    <x-slot name="url_1">{"link": "{{ route('ast.admin.dashboard') }}", "text": "AST Wallet"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('ast.admin.dashboard') }}", "text": "Dashboard"}</x-slot>
    <x-slot name="active">Dashboard</x-slot>
    <x-slot name="buttons">
        <a href="{{ $requestUrl }}" class="ti-btn ti-btn-sm ul-btn {{ $canLoad ? 'ul-btn-cancel' : 'ul-btn-view' }}">
            <i class="bi bi-send"></i>Request AST
        </a>
        @if($canLoad)
            <a href="{{ $loadUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                <i class="bi bi-plus-lg"></i>Load AST
            </a>
            <a href="{{ $reduceUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-edit">
                <i class="bi bi-dash-lg"></i>Reduce
            </a>
            <a href="{{ $setUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-sliders"></i>Set balance
            </a>
        @endif
        <a href="{{ $approvalsUrl }}" class="ti-btn ti-btn-sm ul-btn {{ $pendingApprovals > 0 ? 'ul-btn-edit' : 'ul-btn-cancel' }}">
            <i class="bi bi-hourglass-split"></i>Approvals
            @if($pendingApprovals > 0)
                <span class="ul-badge-count">{{ $pendingApprovals }}</span>
            @endif
        </a>
    </x-slot>

    <div class="grid grid-cols-12 items-stretch ul-ast-dash has-qa">
        <section class="xl:col-span-8 col-span-12 ul-ast-card ul-ast-card-with-logo" aria-label="AST in circulation">
                <div class="ul-ast-card-main">
                    <div class="ul-ast-card-top">
                        <span class="ul-ast-card-badge">
                            <i class="bi bi-wallet2" aria-hidden="true"></i>
                            Circulation
                        </span>
                        @if($canLoad)
                            <a class="ul-ast-card-chip" href="{{ $loadUrl }}">Load</a>
                        @endif
                    </div>
                    <p class="ul-ast-card-label">Total AST in circulation</p>
                    <p class="ul-ast-card-amount">{{ number_format($totalCirculation, 2) }} AST</p>
                    <p class="ul-ast-card-meta">ASELCO Token · 1 AST ≈ ₱1 · remaining balances across {{ number_format($walletCount) }} wallets</p>
                    <div class="ul-ast-card-actions">
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $requestUrl }}">
                            <i class="bi bi-send"></i>Request AST
                        </a>
                        @if($canLoad)
                            <a class="ti-btn ti-btn-sm ul-btn" href="{{ $loadUrl }}">
                                <i class="bi bi-plus-lg"></i>Load AST
                            </a>
                            <a class="ti-btn ti-btn-sm ul-btn" href="{{ $reduceUrl }}">
                                <i class="bi bi-dash-lg"></i>Reduce
                            </a>
                            <a class="ti-btn ti-btn-sm ul-btn" href="{{ $setUrl }}">
                                <i class="bi bi-sliders"></i>Set balance
                            </a>
                        @endif
                        <a class="ti-btn ti-btn-sm ul-btn" href="{{ $approvalsUrl }}">
                            <i class="bi bi-clock-history"></i>Approvals
                        </a>
                    </div>
                </div>
                <img
                    class="ul-ast-card-logo"
                    src="{{ asset('assets/logo_tag.png') }}"
                    alt=""
                    aria-hidden="true"
                >
            </section>

            <div class="xl:col-span-4 col-span-12 ul-qa-card-wrap">
                    <div class="box ul-card ul-qa-card" id="quickActionsCard">
                        <div class="box-header ul-card-header"><div class="box-title ul-card-title">Quick actions</div></div>
                        <div class="ul-qa-grid">
                            <a class="ul-qa is-sky" href="{{ $requestUrl }}">
                                <span class="ul-qa-icon"><i class="bi bi-send"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Request AST</span>
                                    <span class="ul-qa-hint">Ask a loader to credit a wallet</span>
                                </span>
                            </a>
                            @if($canLoad)
                            <a class="ul-qa" href="{{ $loadUrl }}">
                                <span class="ul-qa-icon"><i class="bi bi-plus-lg"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Load AST</span>
                                    <span class="ul-qa-hint">Credit a customer wallet</span>
                                </span>
                            </a>
                            <a class="ul-qa is-orange" href="{{ $reduceUrl }}">
                                <span class="ul-qa-icon"><i class="bi bi-dash-lg"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">Reduce AST</span>
                                    <span class="ul-qa-hint">Deduct remaining balance</span>
                                </span>
                            </a>
                            @endif
                            <a class="ul-qa is-amber" href="{{ $approvalsUrl }}">
                                <span class="ul-qa-icon"><i class="bi bi-hourglass-split"></i></span>
                                <span class="ul-qa-copy">
                                    <span class="ul-qa-title">{{ $canLoad ? 'Approvals' : 'My requests' }}</span>
                                    <span class="ul-qa-hint">{{ $pendingApprovals > 0 ? $pendingApprovals.' waiting for review' : ($canLoad ? 'Review pending loads' : 'Track support load requests') }}</span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>

            <div class="col-span-12 ul-ast-dash-kpis">
            <div class="ul-kpi-grid is-wide">
                <div class="ul-kpi is-lime">
                    <span class="ul-kpi-icon"><i class="bi bi-plus-circle"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($loadedToday, 2) }}</span>
                        <span class="ul-kpi-label">Loaded today</span>
                        <span class="ul-kpi-hint">{{ $todayLabel }}</span>
                    </span>
                </div>
                <div class="ul-kpi is-green">
                    <span class="ul-kpi-icon"><i class="bi bi-calendar-month"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($loadedMonth, 2) }}</span>
                        <span class="ul-kpi-label">Loaded this month</span>
                        <span class="ul-kpi-hint">{{ $monthLabel }}</span>
                    </span>
                </div>
                <div class="ul-kpi is-sky">
                    <span class="ul-kpi-icon"><i class="bi bi-credit-card"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($paidToday, 2) }}</span>
                        <span class="ul-kpi-label">Paid today</span>
                        <span class="ul-kpi-hint">Bills settled in AST</span>
                    </span>
                </div>
                <div class="ul-kpi is-cyan">
                    <span class="ul-kpi-icon"><i class="bi bi-wallet2"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($activeWalletCount) }}</span>
                        <span class="ul-kpi-label">Active wallets</span>
                        <span class="ul-kpi-hint">Balance above zero</span>
                    </span>
                </div>
                <div class="ul-kpi is-slate">
                    <span class="ul-kpi-icon"><i class="bi bi-people"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($walletCount) }}</span>
                        <span class="ul-kpi-label">All wallets</span>
                        <span class="ul-kpi-hint">Opened accounts</span>
                    </span>
                </div>
                <a href="{{ $approvalsUrl }}" class="ul-kpi is-amber">
                    <span class="ul-kpi-icon"><i class="bi bi-hourglass-split"></i></span>
                    <span class="ul-kpi-copy">
                        <span class="ul-kpi-value">{{ number_format($pendingApprovals) }}</span>
                        <span class="ul-kpi-label">Pending approvals</span>
                        <span class="ul-kpi-hint">{{ $pendingApprovals > 0 ? 'Needs a second staff check' : 'Nothing waiting' }}</span>
                    </span>
                </a>
            </div>
            </div>

            <div class="xl:col-span-8 col-span-12 ul-chart-card-wrap">
            <div class="box ul-card ul-chart-card" id="loadChartCard">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">AST loaded — last 30 days</div>
                </div>
                <div class="box-body">
                    <canvas id="loadChart" height="90"></canvas>
                </div>
            </div>
            </div>

        <div class="xl:col-span-4 col-span-12 ul-ast-dash-side">
            <div class="box ul-card ul-rank-card" id="topWalletsCard">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">
                        Top wallets
                        <span class="ul-card-count" id="topWalletsCount">{{ $topWallets->count() }} {{ $topWallets->count() === 1 ? 'record' : 'records' }}</span>
                    </div>
                </div>
                <div class="box-body p-0" id="topWalletsBody">
                    <ul class="ul-rank-list" id="topWalletsList">
                        @foreach($topWallets as $wallet)
                            @php
                                $walletName = trim((string) ($wallet->user?->name ?? ''));
                                $walletAccount = trim((string) ($wallet->account_number ?? ''));
                                $photoUrl = filled($wallet->user?->profile_photo_path)
                                    ? asset('storage/'.ltrim((string) $wallet->user->profile_photo_path, '/'))
                                    : $defaultAvatarUrl;
                                $walletUrl = $wallet->user_id
                                    ? route('ast.admin.customer-wallet', $wallet->user_id)
                                    : null;
                            @endphp
                            <li class="ul-rank-item">
                                <span class="ul-rank-num">{{ $loop->iteration }}</span>
                                <img
                                    class="ul-rank-photo"
                                    src="{{ $photoUrl }}"
                                    alt=""
                                    onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                                >
                                <div class="ul-rank-copy">
                                    @if($walletUrl && $walletName !== '')
                                        <a class="ul-rank-name ul-primary-link" href="{{ $walletUrl }}">{{ $walletName }}</a>
                                    @else
                                        <span class="ul-rank-name">{{ $walletName !== '' ? $walletName : '—' }}</span>
                                    @endif
                                    <span class="ul-rank-meta">{{ $walletAccount !== '' ? $walletAccount : '—' }}</span>
                                </div>
                                <span class="ul-rank-amt">{{ number_format((float) ($wallet->balance ?? 0), 2) }} AST</span>
                            </li>
                        @endforeach
                        @for($slot = $topWallets->count(); $slot < 9; $slot++)
                            <li class="ul-rank-item is-empty">
                                <span class="ul-rank-num">{{ $slot + 1 }}</span>
                                <img class="ul-rank-photo" src="{{ $defaultAvatarUrl }}" alt="">
                                <div class="ul-rank-copy">
                                    <span class="ul-rank-name">—</span>
                                    <span class="ul-rank-meta">—</span>
                                </div>
                                <span class="ul-rank-amt">0.00 AST</span>
                            </li>
                        @endfor
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-span-12">
            <x-unified.table title="Recent load activity" :items="$recentLoads" :colspan="8" :fit="true" empty-title="No load activity yet." empty-text="Loads posted by staff will appear here.">
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Account</th>
                    <th class="text-end">Amount</th>
                    <th>Loaded by</th>
                    <th>Date</th>
                    <th class="text-end ul-col-actions">Actions</th>
                </x-slot:head>
                @foreach($recentLoads as $entry)
                    @php
                        $walletUrl = $entry->wallet?->user_id
                            ? route('ast.admin.customer-wallet', $entry->wallet->user_id)
                            : null;
                    @endphp
                    <x-unified.row :href="$walletUrl">
                        <td class="ul-row-num"><span class="ul-row-num-value">{{ $loop->iteration }}.</span></td>
                        <td><code class="text-xs">{{ $entry->reference }}</code></td>
                        <td>
                            @if($walletUrl)
                                <x-unified.td-primary :href="$walletUrl" :text="$entry->wallet?->user?->name ?: 'Customer'" />
                            @else
                                {{ $entry->wallet?->user?->name ?: '—' }}
                            @endif
                        </td>
                        <td>{{ $entry->wallet?->account_number ?? '—' }}</td>
                        <td class="text-end font-semibold">+{{ number_format((float) $entry->amount, 2) }} AST</td>
                        <td>
                            <div class="font-semibold">{{ $entry->creator?->name ?? '—' }}</div>
                            <div class="ul-date-meta">{{ $entry->creator?->role ?? 'Staff' }}</div>
                        </td>
                        <td class="ul-date">
                            {{ optional($entry->created_at)->timezone('Asia/Manila')->format('M d, Y') }}
                            <div class="ul-date-meta">{{ optional($entry->created_at)->timezone('Asia/Manila')->format('h:i A') }}</div>
                        </td>
                        <x-unified.actions :view-url="$walletUrl" view-label="View wallet" />
                    </x-unified.row>
                @endforeach
            </x-unified.table>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
    <script>
    (function () {
        function matchCardPair(first, second) {
            if (!first || !second) {
                return;
            }
            first.style.height = '';
            second.style.height = '';
            if (window.innerWidth < 1280) {
                return;
            }
            const height = Math.max(first.offsetHeight, second.offsetHeight);
            first.style.height = height + 'px';
            second.style.height = height + 'px';
        }

        function balanceDashCards() {
            matchCardPair(
                document.querySelector('.ul-ast-card-with-logo'),
                document.getElementById('quickActionsCard')
            );
            matchCardPair(
                document.getElementById('loadChartCard'),
                document.getElementById('topWalletsCard')
            );
        }

        function fitTopWallets() {
            balanceDashCards();
            const body = document.getElementById('topWalletsBody');
            const list = document.getElementById('topWalletsList');
            const count = document.getElementById('topWalletsCount');
            const items = list ? Array.from(list.querySelectorAll('.ul-rank-item')) : [];
            if (body && items.length) {
                items.forEach((item) => { item.hidden = false; });
                const styles = window.getComputedStyle(list);
                const pad = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
                const itemHeight = items[0].getBoundingClientRect().height;
                const available = body.clientHeight - pad;
                if (itemHeight > 0 && available > 0) {
                    const max = Math.min(9, Math.max(1, Math.floor(available / itemHeight)));
                    items.forEach((item, index) => {
                        item.hidden = index >= max;
                    });
                    if (count) {
                        count.textContent = max + (max === 1 ? ' record' : ' records');
                    }
                }
            }
            if (typeof window.ulFitTables === 'function') {
                window.ulFitTables();
            }
        }

        window.addEventListener('resize', fitTopWallets);
        window.addEventListener('load', fitTopWallets);
        requestAnimationFrame(fitTopWallets);

        (async function () {
            const canvas = document.getElementById('loadChart');
            if (!canvas) {
                fitTopWallets();
                return;
            }
            try {
                const res = await fetch(@json(route('ast.admin.daily-chart')), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const json = await res.json();
                const labels = (json.data || []).map((row) => row.date);
                const values = (json.data || []).map((row) => row.amount);
                const accent = 'rgb(14, 124, 58)';

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: 'AST loaded',
                            data: values,
                            backgroundColor: 'rgba(14, 124, 58, 0.22)',
                            borderColor: accent,
                            borderWidth: 1.5,
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            onComplete: fitTopWallets,
                        },
                        plugins: { legend: { display: false } },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: { callback: (value) => Number(value).toLocaleString() },
                                grid: { color: 'rgba(148, 163, 184, 0.18)' },
                            },
                            x: {
                                ticks: {
                                    maxTicksLimit: 10,
                                    callback: function (val, index) {
                                        return String(labels[index] || '').slice(5);
                                    }
                                },
                                grid: { display: false },
                            }
                        }
                    }
                });
            } catch (err) {
                canvas.replaceWith(Object.assign(document.createElement('p'), {
                    className: 'td-photo-hint',
                    textContent: 'Chart could not load right now.',
                }));
            } finally {
                requestAnimationFrame(fitTopWallets);
            }
        })();
    })();
    </script>
    @endpush
</x-app-layout>
