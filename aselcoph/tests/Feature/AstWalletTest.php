<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\AstAuditLog;
use App\Models\AstLedgerEntry;
use App\Models\AstWallet;
use App\Models\User;
use App\Services\AstWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AstWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_load_credits_wallet_and_writes_audit(): void
    {
        $staff = $this->staff();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $response = $this->actingAs($staff)->postJson('/ast/load', [
            'account_number' => '0102-334455',
            'amount' => '100.50',
            'idempotency_key' => 'load-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('balance', 100.5)
            ->assertJsonPath('cis_status', AstLedgerEntry::CIS_NOT_APPLICABLE);
        $this->assertStringStartsWith('AST-', (string) $response->json('reference'));

        $this->assertSame('100.50', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertDatabaseHas('ast_audit_logs', [
            'action' => 'load',
            'actor_id' => $staff->id,
            'idempotency_key' => 'load-1',
        ]);
    }

    public function test_member_pay_debits_wallet_and_queues_cis_post(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($this->staff(), '0102-334455', '80.00', 'seed-load');

        $response = $this->payAs($member, '0102-334455', '25.00', 'pay-1');

        $response->assertCreated()
            ->assertJsonPath('balance', 55)
            ->assertJsonPath('cis_status', AstLedgerEntry::CIS_PENDING_POST)
            ->assertJsonPath('type', AstLedgerEntry::TYPE_PAY);

        $this->assertSame('55.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
    }

    public function test_pay_rejects_insufficient_funds(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($this->staff(), '0102-334455', '10.00', 'seed-load');

        $this->payAs($member, '0102-334455', '10.01', 'pay-over')
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_INSUFFICIENT_FUNDS');

        $this->assertSame('10.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertSame(1, AstLedgerEntry::query()->count());
    }

    public function test_pay_is_idempotent(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($this->staff(), '0102-334455', '50.00', 'seed-load');

        $first = $this->payAs($member, '0102-334455', '20.00', 'same-key');
        $second = $this->payAs($member, '0102-334455', '20.00', 'same-key');

        $first->assertCreated();
        $second->assertOk();
        $this->assertSame($first->json('reference'), $second->json('reference'));
        $this->assertSame(1, AstLedgerEntry::query()->where('type', AstLedgerEntry::TYPE_PAY)->count());
        $this->assertSame('30.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
    }

    public function test_second_pay_cannot_overdraw_remaining_balance(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($this->staff(), '0102-334455', '100.00', 'seed-load');

        $this->payAs($member, '0102-334455', '60.00', 'pay-a')->assertCreated();
        $this->payAs($member, '0102-334455', '50.00', 'pay-b')
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_INSUFFICIENT_FUNDS');

        $this->assertSame('40.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertSame(1, AstLedgerEntry::query()->where('type', AstLedgerEntry::TYPE_PAY)->count());
    }

    public function test_non_staff_cannot_load_ast(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $this->actingAs($member)->postJson('/ast/load', [
            'account_number' => '0102-334455',
            'amount' => '10.00',
            'idempotency_key' => 'forbidden-load',
        ])->assertForbidden();

        $this->assertSame(0, AstWallet::query()->count());
    }

    public function test_member_cannot_pay_another_account(): void
    {
        $member = $this->member();
        $other = $this->member();
        $this->linkAccount($member, '0102-111111');
        $this->linkAccount($other, '0102-222222');
        $this->loadAst($this->staff(), '0102-222222', '40.00', 'other-load');

        $this->payAs($member, '0102-222222', '10.00', 'steal')
            ->assertForbidden()
            ->assertJsonPath('code', 'AST_ACCOUNT_FORBIDDEN');

        $this->assertSame('40.00', AstWallet::query()->where('account_number', '0102-222222')->value('balance'));
    }

    public function test_dashboard_returns_live_wallet(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($this->staff(), '0102-334455', '12.00', 'dash-load');

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('wallet.unit', 'AST')
            ->assertJsonPath('wallet.account_number', '0102-334455')
            ->assertJsonPath('wallet.balance', 12)
            ->assertJsonPath('wallet.total_balance', 12);
    }

    public function test_summary_prefers_funded_account_when_customer_has_multiple_links(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '050001891');
        $this->linkAccount($member, '700053691');
        $this->loadAst($this->staff(), '700053691', '57.12', 'multi-load');

        $summary = app(AstWalletService::class)->summaryForUser($member->id);

        $this->assertSame('700053691', $summary['account_number']);
        $this->assertSame(57.12, $summary['balance']);
        $this->assertSame(57.12, $summary['total_balance']);

        $this->actingAs($member, 'sanctum')
            ->getJson('/api/v1/customer/wallet/balance')
            ->assertOk()
            ->assertJsonPath('account_number', '700053691')
            ->assertJsonPath('balance', 57.12)
            ->assertJsonPath('total_balance', 57.12);
    }

    public function test_staff_can_mark_payment_posted_to_cis(): void
    {
        $member = $this->member();
        $staff = $this->staff();
        $this->linkAccount($member, '0102-334455');
        $this->loadAst($staff, '0102-334455', '20.00', 'cis-load');
        $pay = $this->payAs($member, '0102-334455', '5.00', 'cis-pay');
        $entryId = (int) $pay->json('id');

        $this->actingAs($staff)
            ->postJson("/ast/payments/{$entryId}/post-cis", [
                'cis_external_ref' => 'CIS-9001',
            ])
            ->assertOk()
            ->assertJsonPath('cis_status', AstLedgerEntry::CIS_POSTED)
            ->assertJsonPath('cis_external_ref', 'CIS-9001');

        $this->assertDatabaseHas('ast_audit_logs', [
            'action' => 'post_cis',
            'actor_id' => $staff->id,
        ]);
        $this->assertSame(1, AstAuditLog::query()->where('action', 'post_cis')->count());
    }

    public function test_staff_can_reduce_ast_and_write_audit(): void
    {
        $staff = $this->staff();
        $this->linkAccount($this->member(), '0102-334455');
        $service = app(AstWalletService::class);
        $service->load($staff, '0102-334455', '80.00', 'adj-load');

        $entry = $service->adjust($staff, '0102-334455', '30.00', 'debit', 'Correction for duplicate load', 'adj-debit');

        $this->assertTrue($entry->wasRecentlyCreated);
        $this->assertSame(AstLedgerEntry::TYPE_ADJUST, $entry->type);
        $this->assertSame('30.00', (string) $entry->amount);
        $this->assertSame('50.00', (string) $entry->balance_after);
        $this->assertSame('debit', $entry->meta['direction'] ?? null);
        $this->assertSame('50.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertDatabaseHas('ast_audit_logs', [
            'action' => 'adjust',
            'actor_id' => $staff->id,
            'idempotency_key' => 'adj-debit',
        ]);
    }

    public function test_staff_can_set_ast_balance_up_or_down(): void
    {
        $staff = $this->staff();
        $this->linkAccount($this->member(), '0102-334455');
        $service = app(AstWalletService::class);
        $service->load($staff, '0102-334455', '40.00', 'set-load');

        $down = $service->adjust($staff, '0102-334455', '15.00', 'set', 'Reset after billing error', 'adj-set-down');
        $this->assertSame('25.00', (string) $down->amount);
        $this->assertSame('15.00', (string) $down->balance_after);
        $this->assertSame('debit', $down->meta['direction'] ?? null);

        $up = $service->adjust($staff, '0102-334455', '22.50', 'set', 'Restore missed load amount', 'adj-set-up');
        $this->assertSame('7.50', (string) $up->amount);
        $this->assertSame('22.50', (string) $up->balance_after);
        $this->assertSame('credit', $up->meta['direction'] ?? null);
    }

    public function test_staff_reduce_rejects_overdraw_and_requires_reason(): void
    {
        $staff = $this->staff();
        $this->linkAccount($this->member(), '0102-334455');
        $service = app(AstWalletService::class);
        $service->load($staff, '0102-334455', '10.00', 'adj-over-load');

        try {
            $service->adjust($staff, '0102-334455', '10.01', 'debit', 'Trying to remove too much', 'adj-over');
            $this->fail('Expected insufficient AST on reduce.');
        } catch (\App\Exceptions\InsufficientAstBalanceException $e) {
            $this->assertSame('10.00', $e->balance);
        }

        try {
            $service->adjust($staff, '0102-334455', '1.00', 'debit', 'no', 'adj-short');
            $this->fail('Expected a reason validation error.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', strtolower($e->getMessage()));
        }

        $this->assertSame('10.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertSame(1, AstLedgerEntry::query()->count());
    }

    public function test_wallet_service_lock_prevents_negative_balance_on_back_to_back_debits(): void
    {
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');
        $service = app(AstWalletService::class);
        $service->load($this->staff(), '0102-334455', '15.00', 'svc-load');

        $service->pay($member, '0102-334455', '10.00', 'svc-pay-1');
        $this->expectException(\App\Exceptions\InsufficientAstBalanceException::class);
        $service->pay($member, '0102-334455', '10.00', 'svc-pay-2');
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => 'Administrator',
        ]);
    }

    private function member(): User
    {
        return User::factory()->create([
            'role' => 'User',
        ]);
    }

    private function linkAccount(User $user, string $accountNumber): AccountLink
    {
        return AccountLink::query()->create([
            'user_id' => $user->id,
            'account_number' => $accountNumber,
            'owner_name' => $user->name,
            'validated_at' => now(),
            'validated_by' => 'test',
        ]);
    }

    private function loadAst(User $staff, string $accountNumber, string $amount, string $key): void
    {
        $this->actingAs($staff)->postJson('/ast/load', [
            'account_number' => $accountNumber,
            'amount' => $amount,
            'idempotency_key' => $key,
        ])->assertCreated();
    }

    private function payAs(User $member, string $accountNumber, string $amount, string $key)
    {
        return $this->actingAs($member, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/wallet/pay', [
                'account_number' => $accountNumber,
                'amount' => $amount,
                'idempotency_key' => $key,
            ]);
    }
}
