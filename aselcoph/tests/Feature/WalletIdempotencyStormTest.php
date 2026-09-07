<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\AstLedgerEntry;
use App\Models\BillingUpload;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\AstWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletIdempotencyStormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        config()->set('ast.max_load_amount', 100000);
        config()->set('ast.load_approval_threshold', 10000);
        config()->set('ast.load_wallet_roles', [
            'Administrator',
            'administrator',
            'Customer Service',
            'staff',
            'support',
        ]);
        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
    }

    public function test_fifty_identical_bill_payments_credit_the_wallet_once(): void
    {
        $customer = $this->member();
        $staff = User::factory()->create(['role' => 'Administrator']);
        $link = AccountLink::query()->create([
            'user_id' => $customer->id,
            'account_number' => '0102-STORM-PAY',
            'owner_name' => $customer->name,
            'validated_at' => now(),
            'validated_by' => 'test',
        ]);
        $bill = BillingUpload::query()->create([
            'account_link_id' => $link->id,
            'file_path' => 'billing_pdfs/storm.pdf',
            'amount' => '200.00',
            'billing_date' => now()->toDateString(),
            'uploaded_by' => $staff->id,
            'status' => BillingUpload::STATUS_PENDING,
            'paid_amount' => '0.00',
            'balance_due' => '200.00',
        ]);
        app(AstWalletService::class)->load($staff, '0102-STORM-PAY', '200.00', 'storm-seed');

        $statuses = [];
        for ($i = 0; $i < 50; $i++) {
            $response = $this->actingAs($customer, 'sanctum')
                ->withHeader('Idempotency-Key', 'storm-pay-key')
                ->postJson('/api/v1/customer/wallet/pay-bill', [
                    'billing_id' => $bill->id,
                    'amount' => '25.00',
                ]);
            $statuses[] = $response->status();
        }

        $this->assertContains(201, $statuses);
        $this->assertTrue(collect($statuses)->every(fn ($status) => in_array($status, [200, 201], true)));
        $this->assertSame(1, AstLedgerEntry::query()->where('type', AstLedgerEntry::TYPE_PAY)->count());
        $this->assertSame('25.00', $bill->fresh()->paid_amount);
        $this->assertSame('175.00', (string) AstLedgerEntry::query()->where('type', AstLedgerEntry::TYPE_PAY)->value('balance_after'));
    }

    public function test_fifty_identical_admin_loads_credit_the_wallet_once(): void
    {
        $admin = User::factory()->create(['role' => 'Administrator']);
        $customer = $this->member();

        $statuses = [];
        for ($i = 0; $i < 50; $i++) {
            $response = $this->actingAs($admin, 'sanctum')
                ->withHeader('Idempotency-Key', 'storm-load-key')
                ->postJson('/api/v1/admin/wallet/load', [
                    'customer_id' => $customer->id,
                    'amount' => '40.00',
                    'reference_no' => 'AST-STORM-1',
                ]);
            $statuses[] = $response->status();
        }

        $this->assertContains(201, $statuses);
        $this->assertTrue(collect($statuses)->every(fn ($status) => in_array($status, [200, 201], true)));
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame('40.00', Wallet::query()->where('customer_id', $customer->id)->value('balance'));
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'storm-'.uniqid('', true).'@example.com',
            'role' => 'User',
        ]);
    }
}
