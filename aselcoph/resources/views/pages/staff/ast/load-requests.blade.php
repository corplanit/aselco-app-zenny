<x-app-layout>
    <x-slot name="title">AST Load Requests</x-slot>
    <x-slot name="url_1">{"link": "/ast/admin/dashboard", "text": "AST Wallet"}</x-slot>
    <x-slot name="url_2">{"link": "/ast/admin/load-requests", "text": "Load Requests"}</x-slot>
    <x-slot name="active">Load Requests</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('ast.admin.request') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
            <i class="bi bi-send me-1"></i> Request AST
        </a>
        @if(Auth::user()->canLoadWallet())
            <a href="{{ route('ast.admin.load') }}" class="ti-btn ti-btn-primary ti-btn-sm">
                <i class="bi bi-plus-circle me-1"></i> New Load
            </a>
        @endif
        <a href="{{ route('ast.admin.dashboard') }}" class="ti-btn ti-btn-light ti-btn-sm ms-1">
            <i class="bi bi-speedometer2 me-1"></i> Dashboard
        </a>
    </x-slot>

    <div class="alert alert-info text-sm mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Support submits a load request from <strong>Request AST</strong>. Staff with <strong>Load wallets</strong> approve or reject it. The wallet is credited only after approval.
    </div>

    <div id="resultMsg" class="mb-4 hidden"></div>

    <x-unified.toolbar
        :action="route('ast.admin.load-requests')"
        :reset-url="route('ast.admin.load-requests', ['status' => 'pending'])"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search customer, reference…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Status</label>
                <select class="ti-form-select" onchange="this.form.querySelector('input[name=status]').value = this.value">
                    @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'] as $val => $label)
                        <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
        <x-slot:hidden>
            <input type="hidden" name="status" value="{{ $status }}">
        </x-slot:hidden>
        <x-slot:footer>
            <div class="flex gap-1 flex-wrap mt-3">
                @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'completed' => 'Completed'] as $val => $label)
                    <a href="{{ route('ast.admin.load-requests', array_filter(['status' => $val, 'search' => $filters['search'] ?? null])) }}"
                       class="ti-btn ti-btn-sm {{ $status === $val ? 'ti-btn-primary' : 'ti-btn-light' }}">
                        {{ $label }}
                        @if($val === 'pending' && $pendingCount > 0)
                            <span class="ul-badge-count">{{ $pendingCount }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </x-slot:footer>
    </x-unified.toolbar>

    @php
        $colspan = ($status === 'pending' && Auth::user()->canLoadWallet()) ? 10 : 9;
    @endphp

    <x-unified.table
        title="Load requests"
        :paginator="$requests"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('ast.admin.load-requests', ['status' => $status])"
        :empty-title="'No '.$status.' requests found.'"
        :colspan="$colspan"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Customer</th>
            <th>Account</th>
            <th>Ref. No.</th>
            <th class="text-end">Amount (AST)</th>
            <th>Made by</th>
            <th>Approved / Rejected by</th>
            <th>Status</th>
            <th>Submitted</th>
            @if($status === 'pending' && Auth::user()->canLoadWallet())
                <th class="text-end ul-col-actions">Actions</th>
            @endif
        </x-slot:head>

        @foreach($requests as $req)
            @php
                $href = $req->customer_id ? route('ast.admin.customer-wallet', $req->customer_id) : null;
                $statusUi = match ($req->status) {
                    'pending' => ['tone' => 'warning', 'icon' => 'bi-hourglass-split'],
                    'approved' => ['tone' => 'success', 'icon' => 'bi-check-circle'],
                    'completed' => ['tone' => 'primary', 'icon' => 'bi-check2-all'],
                    'rejected' => ['tone' => 'danger', 'icon' => 'bi-x-circle'],
                    default => ['tone' => 'neutral', 'icon' => 'bi-circle'],
                };
            @endphp
            <x-unified.row :href="$href" id="row-{{ $req->id }}">
                <x-unified.td-num :paginator="$requests" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href ?: '#'" :text="$req->customer?->name ?? '—'">
                    <x-slot:meta>{{ $req->customer?->email }}</x-slot:meta>
                </x-unified.td-primary>
                <td>
                    <code class="text-xs">{{ $req->account_number ?: '—' }}</code>
                    @if($req->remarks)
                        <div class="text-xs text-textmuted mt-1">{{ \Illuminate\Support\Str::limit($req->remarks, 60) }}</div>
                    @endif
                </td>
                <td><code class="text-xs">{{ $req->reference_no }}</code></td>
                <td class="text-end font-semibold">{{ number_format((float)$req->amount, 2) }}</td>
                <td>
                    <div class="text-sm">{{ $req->maker?->name ?? '—' }}</div>
                    <x-unified.chip icon="bi-person-badge" quiet>{{ $req->maker?->role ?? '—' }}</x-unified.chip>
                </td>
                <td>
                    @if($req->checker)
                        <div class="text-sm">{{ $req->checker->name }}</div>
                        <div class="text-xs text-textmuted">{{ optional($req->approved_at)->format('M d, Y h:i A') }}</div>
                    @else
                        <span class="text-textmuted">—</span>
                    @endif
                </td>
                <td>
                    <x-unified.badge :tone="$statusUi['tone']" :icon="$statusUi['icon']">{{ ucfirst($req->status) }}</x-unified.badge>
                </td>
                <td class="text-nowrap text-sm">{{ optional($req->created_at)->format('M d, Y h:i A') }}</td>
                @if($status === 'pending' && Auth::user()->canLoadWallet())
                    <td class="ul-no-row-click text-end">
                        @if((int)$req->admin_id === Auth::id())
                            <span class="text-xs text-textmuted italic">Awaiting another approver</span>
                        @else
                            <div class="ul-actions">
                                <button type="button" class="ti-btn ti-btn-success ti-btn-sm btn-approve"
                                        data-id="{{ $req->id }}"
                                        data-amount="{{ number_format((float)$req->amount, 2) }}"
                                        data-customer="{{ $req->customer?->name }}">
                                    Approve
                                </button>
                                <button type="button" class="ti-btn ti-btn-danger ti-btn-sm btn-reject" data-id="{{ $req->id }}">
                                    Reject
                                </button>
                            </div>
                        @endif
                    </td>
                @endif
            </x-unified.row>
        @endforeach
    </x-unified.table>

    <div id="rejectModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
            <h5 class="font-semibold mb-1">Reject Load Request</h5>
            <p class="text-sm text-textmuted mb-3">Provide a reason. This is recorded in the audit trail.</p>
            <textarea id="rejectReason" class="ti-form-input mb-4" rows="3" placeholder="Reason for rejection…" maxlength="500"></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" id="cancelReject" class="ti-btn ti-btn-light">Cancel</button>
                <button type="button" id="confirmReject" class="ti-btn ti-btn-danger">Confirm Reject</button>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const resultMsg = document.getElementById('resultMsg');
        function showMsg(type, text) {
            resultMsg.className = `alert alert-${type} mb-4`;
            resultMsg.innerHTML = text;
            resultMsg.classList.remove('hidden');
        }
        function removeRow(id) { document.getElementById(`row-${id}`)?.remove(); }
        document.querySelectorAll('.btn-approve').forEach(btn => {
            btn.addEventListener('click', async function () {
                const approved = await window.ulConfirm({
                    verb: 'Approve',
                    text: `Credit ${this.dataset.amount} AST to ${this.dataset.customer}.`,
                    icon: 'bi-check2',
                });
                if (!approved) return;
                window.ulBusyButton?.(this);
                try {
                    const res = await fetch(`/ast/admin/load-requests/${this.dataset.id}/approve`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({}),
                    });
                    const json = await res.json();
                    if (res.ok) { showMsg('success', `<strong>Approved.</strong> ${json.amount} AST credited.`); removeRow(this.dataset.id); }
                    else { showMsg('danger', json.message ?? 'Approval failed.'); window.ulReadyButton?.(this); }
                } catch { showMsg('danger', 'Network error.'); window.ulReadyButton?.(this); }
            });
        });
        let rejectTargetId = null;
        const rejectModal = document.getElementById('rejectModal');
        const rejectReason = document.getElementById('rejectReason');
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function () {
                rejectTargetId = this.dataset.id;
                rejectReason.value = '';
                rejectModal.classList.remove('hidden');
                rejectModal.classList.add('flex');
            });
        });
        document.getElementById('cancelReject').addEventListener('click', () => {
            rejectModal.classList.add('hidden'); rejectModal.classList.remove('flex');
        });
        document.getElementById('confirmReject').addEventListener('click', async () => {
            const reason = rejectReason.value.trim();
            if (!reason) { rejectReason.focus(); return; }
            const confirmBtn = document.getElementById('confirmReject');
            window.ulBusyButton?.(confirmBtn);
            try {
                const res = await fetch(`/ast/admin/load-requests/${rejectTargetId}/reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ reason }),
                });
                const json = await res.json();
                rejectModal.classList.add('hidden'); rejectModal.classList.remove('flex');
                if (res.ok) { showMsg('warning', `<strong>Rejected.</strong> ${json.message}`); removeRow(rejectTargetId); }
                else { showMsg('danger', json.message ?? 'Rejection failed.'); }
            } catch {
                showMsg('danger', 'Network error.');
            } finally {
                window.ulReadyButton?.(confirmBtn);
            }
        });
    })();
    </script>
    @endpush
</x-app-layout>
