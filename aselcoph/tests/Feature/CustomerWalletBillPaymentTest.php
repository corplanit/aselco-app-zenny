<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\AstLedgerEntry;
use App\Models\BillingUpload;
use App\Models\User;
use App\Services\AstWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerWalletBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.supervisor_roles', ['Supervisor', 'supervisor']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
    }

    public function test_customer_can_partially_pay_bill_with_ast_and_bill_stays_in_local_pending_flow(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-111111');
        $bill = $this->billing($link, $staff, '100.00');

        $this->loadAst($staff, '0102-111111', '60.00', 'seed-load');

        $response = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'bill-pay-1')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '40.00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('type', AstLedgerEntry::TYPE_PAY)
            ->assertJsonPath('amount', 40)
            ->assertJsonPath('billing_upload_id', $bill->id)
            ->assertJsonPath('cis_status', AstLedgerEntry::CIS_PENDING_POST)
            ->assertJsonPath('receipt_no', $response->json('reference'));

        $this->assertDatabaseHas('billing_uploads', [
            'id' => $bill->id,
            'status' => BillingUpload::STATUS_PARTIALLY_PAID,
            'paid_amount' => '40.00',
            'balance_due' => '60.00',
        ]);
    }

    public function test_duplicate_idempotency_key_replays_original_payment_without_double_debit(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-222222');
        $bill = $this->billing($link, $staff, '80.00');

        $this->loadAst($staff, '0102-222222', '80.00', 'seed-load-2');

        $first = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-payment-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '30.00',
            ]);

        $second = $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-payment-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '30.00',
            ]);

        $first->assertCreated();
        $second->assertOk()
            ->assertJsonPath('reference', $first->json('reference'));

        $this->assertSame(2, AstLedgerEntry::query()->count());
        $this->assertDatabaseHas('billing_uploads', [
            'id' => $bill->id,
            'paid_amount' => '30.00',
            'balance_due' => '50.00',
        ]);
    }

    public function test_customer_cannot_pay_bill_they_do_not_own(): void
    {
        $owner = $this->customer('owner');
        $other = $this->customer('other');
        $staff = $this->staff();
        $bill = $this->billing($this->linkAccount($owner, '0102-333333'), $staff, '50.00');

        $this->loadAst($staff, '0102-333333', '50.00', 'seed-load-3');

        $this->actingAs($other, 'sanctum')
            ->withHeader('Idempotency-Key', 'not-owned-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '10.00',
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'AST_BILL_NOT_FOUND');
    }

    public function test_customer_cannot_pay_more_than_remaining_balance_due(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-444444');
        $bill = $this->billing($link, $staff, '20.00');

        $this->loadAst($staff, '0102-444444', '100.00', 'seed-load-4');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'mismatch-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '25.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_AMOUNT_MISMATCH');
    }

    public function test_customer_cannot_pay_with_insufficient_ast_balance(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-555555');
        $bill = $this->billing($link, $staff, '50.00');

        $this->loadAst($staff, '0102-555555', '10.00', 'seed-load-5');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'insufficient-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '20.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_INSUFFICIENT_FUNDS');
    }

    public function test_fully_paid_bill_cannot_be_paid_again(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-666666');
        $bill = $this->billing($link, $staff, '30.00');

        $this->loadAst($staff, '0102-666666', '30.00', 'seed-load-6');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'full-pay-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '30.00',
            ])
            ->assertCreated();

        $this->loadAst($staff, '0102-666666', '30.00', 'seed-load-7');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'full-pay-key-2')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '1.00',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'AST_BILL_ALREADY_PAID');
    }

    public function test_balance_and_transactions_endpoints_return_customer_wallet_data(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-777777');
        $bill = $this->billing($link, $staff, '70.00');

        $this->loadAst($staff, '0102-777777', '100.00', 'seed-load-8');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'balance-key')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '20.00',
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/customer/wallet/balance')
            ->assertOk()
            ->assertJsonPath('account_number', '0102-777777')
            ->assertJsonPath('balance', 80);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/customer/wallet/transactions?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.billing_upload_id', $bill->id);
    }

    public function test_back_to_back_partial_payments_keep_wallet_and_bill_balances_consistent(): void
    {
        $customer = $this->customer();
        $staff = $this->staff();
        $link = $this->linkAccount($customer, '0102-888888');
        $bill = $this->billing($link, $staff, '100.00');

        $this->loadAst($staff, '0102-888888', '100.00', 'seed-load-9');

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'lock-order-a')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '30.00',
            ])
            ->assertCreated();

        $this->actingAs($customer, 'sanctum')
            ->withHeader('Idempotency-Key', 'lock-order-b')
            ->postJson('/api/v1/customer/wallet/pay-bill', [
                'billing_id' => $bill->id,
                'amount' => '20.00',
            ])
            ->assertCreated();

        $bill->refresh();

        $this->assertSame('50.00', $bill->paid_amount);
        $this->assertSame('50.00', $bill->balance_due);
        $this->assertSame('50.00', (string) AstLedgerEntry::query()->latest('id')->value('balance_after'));
    }

    private function customer(string $suffix = ''): User
    {
        $emailSuffix = $suffix === '' ? uniqid('', true) : $suffix.'-'.uniqid('', true);

        return User::factory()->create([
            'email' => 'bill-pay-'.$emailSuffix.'@example.com',
            'role' => 'User',
        ]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => 'Administrator',
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

    private function billing(AccountLink $link, User $staff, string $amount): BillingUpload
    {
        return BillingUpload::query()->create([
            'account_link_id' => $link->id,
            'file_path' => 'billing_pdfs/test.pdf',
            'amount' => $amount,
            'billing_date' => now()->toDateString(),
            'uploaded_by' => $staff->id,
            'status' => BillingUpload::STATUS_PENDING,
            'paid_amount' => '0.00',
            'balance_due' => $amount,
        ]);
    }

    private function loadAst(User $staff, string $accountNumber, string $amount, string $key): void
    {
        app(AstWalletService::class)->load($staff, $accountNumber, $amount, $key);
    }
}
