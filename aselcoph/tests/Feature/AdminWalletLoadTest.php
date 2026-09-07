<?php

namespace Tests\Feature;

use App\Mail\WalletLoadedMail;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletAuditLog;
use App\Models\WalletLoadRequest;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminWalletLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ast.max_load_amount', 100000);
        config()->set('ast.load_approval_threshold', 10000);
        config()->set('ast.load_wallet_roles', [
            'Administrator',
            'administrator',
            'Customer Service',
            'staff',
            'support',
        ]);
    }

    public function test_admin_can_load_ast_into_an_active_customer_wallet(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $customer = $this->customer();

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'load-key-1')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '125.50',
                'reference_no' => 'AST-LOAD-1001',
                'remarks' => 'Counter payment',
            ]);

        $response->assertCreated()
            ->assertJson([
                'reference_no' => 'AST-LOAD-1001',
                'amount' => 125.5,
                'new_balance' => 125.5,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ])
            ->assertJsonPath('transaction_id', 1);

        $this->assertDatabaseHas('wallets', [
            'customer_id' => $customer->id,
            'balance' => '125.50',
            'status' => Wallet::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('wallet_transactions', [
            'id' => 1,
            'reference_no' => 'AST-LOAD-1001',
            'idempotency_key' => 'load-key-1',
            'type' => WalletTransaction::TYPE_LOAD,
            'status' => WalletTransaction::STATUS_COMPLETED,
            'source' => WalletTransaction::SOURCE_ADMIN,
            'source_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('wallet_audit_logs', [
            'actor_id' => $admin->id,
            'actor_type' => WalletAuditLog::ACTOR_ADMIN,
            'action' => 'load_completed',
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $customer->id,
            'category' => 'billing',
            'title' => 'AST loaded',
        ]);

        Mail::assertSent(WalletLoadedMail::class, fn (WalletLoadedMail $mail) => $mail->hasTo($customer->email));
    }

    public function test_duplicate_reference_number_is_rejected_without_crediting_wallet(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'load-key-a')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '50.00',
                'reference_no' => 'AST-DUP-REF',
            ])
            ->assertCreated();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'load-key-b')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '75.00',
                'reference_no' => 'AST-DUP-REF',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'reference_no has already been used.');

        $this->assertSame('50.00', Wallet::query()->where('customer_id', $customer->id)->value('balance'));
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    public function test_duplicate_idempotency_key_returns_original_response_without_double_credit(): void
    {
        Mail::fake();

        $admin = $this->admin();
        $customer = $this->customer();

        $first = $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '80.00',
                'reference_no' => 'AST-IDEMP-1',
                'remarks' => 'first submit',
            ]);

        $second = $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '999.00',
                'reference_no' => 'AST-IDEMP-2',
                'remarks' => 'replay attempt',
            ]);

        $first->assertCreated();
        $second->assertOk()
            ->assertExactJson($first->json());

        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame('80.00', Wallet::query()->where('customer_id', $customer->id)->value('balance'));
        $this->assertSame(1, WalletAuditLog::query()->where('action', 'load_duplicate_blocked')->count());
        Mail::assertSent(WalletLoadedMail::class, 1);
    }

    public function test_invalid_amount_is_rejected_and_audited(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', 'bad-amount-key')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '0.50',
                'reference_no' => 'AST-BAD-AMOUNT',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);

        $this->assertSame(1, WalletAuditLog::query()->where('action', 'load_failed')->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_unauthorized_role_is_rejected_and_audited(): void
    {
        $customer = $this->customer();
        $nonAdmin = $this->customer();

        $this->actingAs($nonAdmin, 'sanctum')
            ->withHeader('Idempotency-Key', 'forbidden-key')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '10.00',
                'reference_no' => 'AST-FORBIDDEN',
            ])
            ->assertForbidden();

        $this->assertSame(1, WalletAuditLog::query()->where('action', 'load_unauthorized')->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    public function test_high_value_load_is_queued_listed_and_can_be_approved_by_a_different_admin(): void
    {
        Mail::fake();

        $maker = $this->admin();
        $checker = $this->staff('Customer Service');
        $customer = $this->customer();

        $queueResponse = $this->actingAs($maker, 'sanctum')
            ->withHeader('Idempotency-Key', 'queue-key-1')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '10000.00',
                'reference_no' => 'AST-QUEUE-1',
                'remarks' => 'Needs approval',
            ]);

        $queueResponse->assertStatus(202)
            ->assertJsonPath('status', 'pending_approval')
            ->assertJsonPath('transaction_id', null);

        $requestId = WalletLoadRequest::query()->value('id');

        $this->actingAs($checker, 'sanctum')
            ->getJson('/api/v1/admin/wallet/load-requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.reference_no', 'AST-QUEUE-1');

        $this->actingAs($checker, 'sanctum')
            ->postJson("/api/v1/admin/wallet/load-requests/{$requestId}/approve")
            ->assertCreated()
            ->assertJson([
                'reference_no' => 'AST-QUEUE-1',
                'amount' => 10000,
                'new_balance' => 10000,
                'status' => WalletTransaction::STATUS_COMPLETED,
            ]);

        $this->assertDatabaseHas('wallet_load_requests', [
            'id' => $requestId,
            'status' => WalletLoadRequest::STATUS_COMPLETED,
            'approved_by' => $checker->id,
        ]);

        $this->assertSame('10000.00', Wallet::query()->where('customer_id', $customer->id)->value('balance'));
        $this->assertSame(1, WalletTransaction::query()->where('reference_no', 'AST-QUEUE-1')->count());
        Mail::assertSent(WalletLoadedMail::class, 1);
    }

    public function test_maker_cannot_approve_their_own_load_request(): void
    {
        $maker = $this->admin();
        $customer = $this->customer();

        $this->actingAs($maker, 'sanctum')
            ->withHeader('Idempotency-Key', 'queue-key-maker')
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => '10000.00',
                'reference_no' => 'AST-QUEUE-MAKER',
            ])
            ->assertStatus(202);

        $requestId = WalletLoadRequest::query()->value('id');

        $this->actingAs($maker, 'sanctum')
            ->postJson("/api/v1/admin/wallet/load-requests/{$requestId}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'A different administrator must approve this load.');

        $this->assertDatabaseHas('wallet_load_requests', [
            'id' => $requestId,
            'status' => WalletLoadRequest::STATUS_PENDING,
        ]);
    }

    public function test_transactions_endpoint_returns_paginated_filtered_results(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->seedLoad($admin, $customer, '20.00', 'AST-TXN-1', 'txn-key-1');
        $this->seedLoad($admin, $customer, '30.00', 'AST-TXN-2', 'txn-key-2');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/wallet/{$customer->id}/transactions?type=load&per_page=1")
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.type', WalletTransaction::TYPE_LOAD);
    }

    public function test_summary_endpoint_returns_wallet_aggregates(): void
    {
        $admin = $this->admin();
        $customerA = $this->customer('A');
        $customerB = $this->customer('B');

        $this->seedLoad($admin, $customerA, '200.00', 'AST-SUM-1', 'sum-key-1');
        $this->seedLoad($admin, $customerB, '50.00', 'AST-SUM-2', 'sum-key-2');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/wallet/summary')
            ->assertOk()
            ->assertJsonPath('unit', 'AST')
            ->assertJsonPath('total_in_circulation', 250)
            ->assertJsonPath('loaded_today', 250)
            ->assertJsonPath('loaded_this_month', 250)
            ->assertJsonPath('pending_approvals', 0)
            ->assertJsonPath('top_customers.0.customer_id', $customerA->id)
            ->assertJsonPath('recent_loads.0.reference_no', 'AST-SUM-2');
    }

    public function test_back_to_back_load_requests_preserve_balance_integrity(): void
    {
        $admin = $this->admin();
        $customer = $this->customer();

        $this->seedLoad($admin, $customer, '10.00', 'AST-LOCK-1', 'lock-key-1');
        $this->seedLoad($admin, $customer, '15.00', 'AST-LOCK-2', 'lock-key-2');

        $this->assertSame('25.00', Wallet::query()->where('customer_id', $customer->id)->value('balance'));
        $this->assertSame(2, WalletTransaction::query()->count());
        $this->assertSame('25.00', WalletTransaction::query()->latest('id')->value('balance_after'));
    }

    private function seedLoad(User $admin, User $customer, string $amount, string $referenceNo, string $key): void
    {
        $this->actingAs($admin, 'sanctum')
            ->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/admin/wallet/load', [
                'customer_id' => $customer->id,
                'amount' => $amount,
                'reference_no' => $referenceNo,
            ])
            ->assertCreated();
    }

    private function admin(): User
    {
        return $this->staff('Administrator');
    }

    private function staff(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
        ]);
    }

    private function customer(string $suffix = ''): User
    {
        $emailSuffix = $suffix === '' ? uniqid('', true) : strtolower($suffix).'-'.uniqid('', true);

        return User::factory()->create([
            'name' => 'Customer '.$suffix,
            'email' => 'customer-'.$emailSuffix.'@example.com',
            'role' => 'User',
        ]);
    }
}
