<x-app-layout>
    <x-slot name="title">AST CIS Queue</x-slot>
    <x-slot name="url_1">{"link": "/ast/cis-queue", "text": "AST"}</x-slot>
    <x-slot name="url_2">{"link": "/ast/cis-queue", "text": "Post to CIS"}</x-slot>
    <x-slot name="active">Queue</x-slot>

    <div class="alert alert-info text-sm mb-4">
        <i class="bi bi-info-circle me-1"></i>
        AST bill payments are deducted from the wallet immediately. Mark a payment posted after it is entered in the official CIS ledger.
    </div>

    @if (session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger mb-4">{{ session('error') }}</div> @endif

    <x-unified.toolbar
        :action="route('ast.cis.queue')"
        :reset-url="route('ast.cis.queue')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search reference, account, member…"
        :active-filter-count="$activeFilterCount"
        :show-filter="false"
    />

    <x-unified.table
        title="CIS post queue"
        :paginator="$payments"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('ast.cis.queue')"
        empty-title="No AST payments waiting to be posted."
        :colspan="7"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Reference</th>
            <th>Account</th>
            <th>Member</th>
            <th class="text-end">AST</th>
            <th>Paid at</th>
            <th style="width:220px">Post to CIS</th>
        </x-slot:head>

        @foreach ($payments as $payment)
            <x-unified.row>
                <x-unified.td-num :paginator="$payments" :iteration="$loop->iteration" />
                <td class="font-medium">{{ $payment->reference }}</td>
                <td>{{ $payment->wallet?->account_number }}</td>
                <td>{{ $payment->creator?->name ?? '—' }}</td>
                <td class="text-end">{{ number_format((float) $payment->amount, 2) }}</td>
                <td>{{ optional($payment->created_at)->format('M d, Y h:i A') }}</td>
                <td class="ul-no-row-click">
                    <form method="POST" action="{{ route('ast.cis.post', $payment->id) }}" class="flex gap-2 items-center ul-no-row-click">
                        @csrf
                        <input type="text" name="cis_external_ref" class="ti-form-input ti-form-input-sm"
                            placeholder="CIS reference" required maxlength="120">
                        <button type="submit" class="ti-btn ti-btn-primary ti-btn-sm">Post</button>
                    </form>
                </td>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
