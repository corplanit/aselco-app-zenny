<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccountLink;
use App\Models\TAccountRaw;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class LedgerController extends Controller
{
    /**
     * Account ledger + billing history for a member's electric account.
     * Proxies the same GetConsumersLeger source the web dashboard uses.
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $allowed = $this->allowedAccountNumbers($userId);

        if ($allowed === []) {
            return response()->json([
                'message' => 'No electric account is linked to this member yet.',
            ], 404);
        }

        $requested = trim((string) $request->query('account_number', ''));
        if ($requested !== '') {
            if (! in_array($requested, $allowed, true)) {
                return response()->json([
                    'message' => 'That account number is not linked to your profile.',
                ], 403);
            }
            $accountNumber = $requested;
        } else {
            $accountNumber = $allowed[0];
        }

        try {
            $record = $this->fetchExternalLedger($accountNumber);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Could not load the cooperative ledger. Try again shortly.',
            ], 502);
        }

        $details = is_array($record['details'] ?? null) ? $record['details'] : [];
        $entries = $this->mapEntries($details);
        $history = $this->mapHistory($details);
        $summary = $this->mapSummary($accountNumber, $record, $details, $history);

        $sort = $request->query('sort', 'latest') === 'oldest' ? 'oldest' : 'latest';
        usort($entries, function (array $a, array $b) use ($sort) {
            $cmp = strcmp((string) ($b['posted_at'] ?? ''), (string) ($a['posted_at'] ?? ''));

            return $sort === 'oldest' ? -$cmp : $cmp;
        });

        $snapshot = $request->boolean('snapshot');
        $type = strtolower(trim((string) $request->query('type', 'all')));
        if (! $snapshot && ($type === 'bill' || $type === 'payment')) {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry) => $entry['type'] === $type
            ));
        }

        $total = count($entries);
        if ($snapshot) {
            $perPage = max(1, $total);
            $page = 1;
            $lastPage = 1;
            $slice = $entries;
        } else {
            $perPage = min(50, max(5, (int) $request->query('per_page', 10)));
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = max(1, (int) $request->query('page', 1));
            $page = min($page, $lastPage);
            $slice = array_values(array_slice($entries, ($page - 1) * $perPage, $perPage));
        }

        return response()->json([
            'account' => [
                'account_number' => (string) ($record['consumerId'] ?? $accountNumber),
                'consumer_name' => $record['consumerName'] ?? null,
                'consumer_address' => $record['consumerAddress'] ?? null,
                'consumer_status' => $record['consumerStatus'] ?? null,
            ],
            'accounts' => $allowed,
            'summary' => $summary,
            'history' => $history,
            'entries' => $slice,
            'sort' => $sort,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($total, $page * $perPage),
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedAccountNumbers(int $userId): array
    {
        $fromLinks = AccountLink::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->pluck('account_number')
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        $fromRaw = collect();
        try {
            $fromRaw = TAccountRaw::query()
                ->where('user_id', $userId)
                ->pluck('account_no')
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->values();
        } catch (Throwable) {
            // Raw ledger table may be unavailable.
        }

        return $fromRaw->merge($fromLinks)->unique()->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchExternalLedger(string $accountNumber): array
    {
        $base = rtrim((string) config('services.aselco_ledger.url'), '/');
        $timeout = (int) config('services.aselco_ledger.timeout', 20);
        $url = $base.'/'.rawurlencode($accountNumber);

        $response = Http::acceptJson()
            ->timeout($timeout)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Ledger upstream returned HTTP '.$response->status());
        }

        $json = $response->json();
        if (! is_array($json) || $json === []) {
            throw new \RuntimeException('Ledger upstream returned no data.');
        }

        $rows = array_is_list($json) ? $json : [$json];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = trim((string) ($row['consumerId'] ?? ''));
            if ($id !== '' && $id === $accountNumber) {
                return $row;
            }
        }

        $first = $rows[0] ?? null;

        return is_array($first) ? $first : [];
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private function mapEntries(array $details): array
    {
        $entries = [];

        foreach ($details as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $debit = (float) ($row['debit'] ?? 0);
            $credit = (float) ($row['credit'] ?? 0);
            $isPayment = $credit > 0 && $credit >= $debit;
            $billMonth = isset($row['billMonth']) ? (string) $row['billMonth'] : null;
            $postedAt = $this->entryCarbon($row);

            $entries[] = [
                'id' => (string) ($row['reference'] ?? 'L-'.$index).'-'.$index,
                'type' => $isPayment ? 'payment' : 'bill',
                'title' => $this->entryTitle($row, $isPayment),
                'date' => $this->entryDate($row),
                'posted_at' => $postedAt->toDateString(),
                'ref' => (string) ($row['reference'] ?? '—'),
                'amount' => $isPayment ? $credit : $debit,
                'debit' => $debit,
                'credit' => $credit,
                'kwh' => $this->nullableNumber($row['kwhUsed'] ?? null),
                'demand_kw' => $this->nullableNumber($row['demandKW'] ?? null),
                'previous_reading' => $this->nullableNumber($row['previousReading'] ?? null),
                'present_reading' => $this->nullableNumber($row['presentReading'] ?? null),
                'balance' => $this->nullableNumber($row['balance'] ?? null),
                'bill_month' => $billMonth,
                'due_date' => $this->dueDateFromBillMonth($billMonth),
            ];
        }

        return $entries;
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private function mapHistory(array $details): array
    {
        $groups = [];

        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['billMonth'] ?? 'Unknown');
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'bill_month' => $key === 'Unknown' ? null : $key,
                    'label' => $this->monthLabel($key) ?? 'Unknown',
                    'debit' => 0.0,
                    'credit' => 0.0,
                    'kwh' => 0.0,
                    'balance' => null,
                    'due_date' => $this->dueDateFromBillMonth($key),
                ];
            }
            $groups[$key]['debit'] += (float) ($row['debit'] ?? 0);
            $groups[$key]['credit'] += (float) ($row['credit'] ?? 0);
            $groups[$key]['kwh'] += (float) ($row['kwhUsed'] ?? 0);
            if (isset($row['balance']) && $row['balance'] !== null && $row['balance'] !== '') {
                $groups[$key]['balance'] = (float) $row['balance'];
            }
        }

        $keys = array_keys($groups);
        usort($keys, function (string $a, string $b) {
            if ($a === 'Unknown') {
                return 1;
            }
            if ($b === 'Unknown') {
                return -1;
            }

            return (int) $b <=> (int) $a;
        });

        return array_map(fn (string $key) => $groups[$key], $keys);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  list<array<string, mixed>>  $details
     * @param  list<array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    private function mapSummary(string $accountNumber, array $record, array $details, array $history): array
    {
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $paidYtd = 0.0;
        $year = (int) now()->format('Y');
        $closingBalance = null;

        foreach ($details as $row) {
            if (! is_array($row)) {
                continue;
            }
            $debit = (float) ($row['debit'] ?? 0);
            $credit = (float) ($row['credit'] ?? 0);
            $totalDebit += $debit;
            $totalCredit += $credit;
            $billMonth = (string) ($row['billMonth'] ?? '');
            if ($credit > 0 && strlen($billMonth) >= 4 && (int) substr($billMonth, 0, 4) === $year) {
                $paidYtd += $credit;
            }
            if (isset($row['balance']) && $row['balance'] !== null && $row['balance'] !== '') {
                $closingBalance = (float) $row['balance'];
            }
        }

        $latest = $history[0] ?? null;
        $currentDue = round($totalDebit - $totalCredit, 2);
        if ($currentDue < 0) {
            $currentDue = 0.0;
        }

        return [
            'account_number' => (string) ($record['consumerId'] ?? $accountNumber),
            'current_balance' => $closingBalance !== null ? round($closingBalance, 2) : $currentDue,
            'current_due' => $currentDue,
            'total_paid' => round($paidYtd > 0 ? $paidYtd : $totalCredit, 2),
            'kwh_used' => $latest ? (float) $latest['kwh'] : 0.0,
            'billing_period' => $latest['label'] ?? null,
            'due_date' => $latest['due_date'] ?? null,
            'pending_count' => collect($history)->filter(fn ($month) => $month['debit'] > $month['credit'])->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function entryTitle(array $row, bool $isPayment): string
    {
        $desc = trim((string) ($row['descriptions'] ?? ''));
        if ($desc !== '' && ! preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $desc)) {
            return $desc;
        }

        $label = $this->monthLabel((string) ($row['billMonth'] ?? '')) ?? '';

        return $isPayment
            ? trim('Payment'.($label !== '' ? ' — '.$label : ''))
            : trim('Billing statement'.($label !== '' ? ' — '.$label : ''));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function entryDate(array $row): string
    {
        $carbon = $this->entryCarbon($row);
        if ($carbon->getTimestamp() === 0) {
            return '—';
        }

        return $carbon->format('M d, Y');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function entryCarbon(array $row): Carbon
    {
        $desc = trim((string) ($row['descriptions'] ?? ''));
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $desc, $match)) {
            return Carbon::createFromDate((int) $match[3], (int) $match[1], (int) $match[2])->startOfDay();
        }

        $billMonth = (string) ($row['billMonth'] ?? '');
        if (strlen($billMonth) === 6 && ctype_digit($billMonth)) {
            try {
                return Carbon::createFromFormat('Ym', $billMonth)->startOfMonth();
            } catch (Throwable) {
                // Fall through.
            }
        }

        return Carbon::createFromTimestamp(0);
    }

    private function dueDateFromBillMonth(?string $yyyymm): ?string
    {
        if (! $yyyymm || strlen($yyyymm) !== 6 || ! ctype_digit($yyyymm)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Ym', $yyyymm)->startOfMonth()->endOfMonth()->format('M d, Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function monthLabel(?string $yyyymm): ?string
    {
        if (! $yyyymm || strlen($yyyymm) !== 6 || ! ctype_digit($yyyymm)) {
            return $yyyymm === 'Unknown' ? 'Unknown' : null;
        }

        try {
            return Carbon::createFromFormat('Ym', $yyyymm)->startOfMonth()->format('M Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
