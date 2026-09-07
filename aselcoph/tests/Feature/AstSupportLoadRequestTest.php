<?php

namespace Tests\Feature;

use App\Events\WalletLoadApprovalRequested;
use App\Models\AccountLink;
use App\Models\AstWallet;
use App\Models\User;
use App\Models\WalletLoadRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AstSupportLoadRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ast.load_wallet_roles', ['Administrator', 'administrator']);
    }

    public function test_support_cannot_load_ast_directly(): void
    {
        $support = $this->support();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $this->actingAs($support)->postJson(route('ast.admin.load.submit'), [
            'account_number' => '0102-334455',
            'amount' => '10.00',
            'idempotency_key' => 'direct-load',
        ])->assertForbidden();
    }

    public function test_support_can_queue_a_load_request_without_crediting(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $support = $this->support();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $response = $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '150.00',
            'remarks' => 'Paid at the office teller',
            'idempotency_key' => 'req-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', WalletLoadRequest::STATUS_PENDING)
            ->assertJsonPath('account_number', '0102-334455')
            ->assertJsonPath('amount', 150);

        $this->assertDatabaseHas('wallet_load_requests', [
            'customer_id' => $member->id,
            'account_number' => '0102-334455',
            'admin_id' => $support->id,
            'amount' => '150.00',
            'status' => WalletLoadRequest::STATUS_PENDING,
            'source' => WalletLoadRequest::SOURCE_SUPPORT,
        ]);
        $this->assertSame(0, AstWallet::query()->count());
        Event::assertDispatched(WalletLoadApprovalRequested::class);
    }

    public function test_loader_approval_credits_ast_wallet(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $support = $this->support();
        $loader = $this->loader();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $requestId = $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '80.00',
            'remarks' => 'Walk-in cash payment',
            'idempotency_key' => 'req-approve',
        ])->assertCreated()->json('load_request_id');

        $this->actingAs($loader)->postJson(route('ast.admin.load-requests.approve', $requestId))
            ->assertOk()
            ->assertJsonPath('amount', 80);

        $this->assertSame('80.00', AstWallet::query()->where('account_number', '0102-334455')->value('balance'));
        $this->assertDatabaseHas('wallet_load_requests', [
            'id' => $requestId,
            'status' => WalletLoadRequest::STATUS_COMPLETED,
            'approved_by' => $loader->id,
        ]);
    }

    public function test_loader_rejection_does_not_credit_wallet(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $support = $this->support();
        $loader = $this->loader();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $requestId = $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '40.00',
            'remarks' => 'Need load for reconnection',
            'idempotency_key' => 'req-reject',
        ])->assertCreated()->json('load_request_id');

        $this->actingAs($loader)->postJson(route('ast.admin.load-requests.reject', $requestId), [
            'reason' => 'No official receipt attached',
        ])->assertOk();

        $this->assertSame(0, AstWallet::query()->count());
        $this->assertDatabaseHas('wallet_load_requests', [
            'id' => $requestId,
            'status' => WalletLoadRequest::STATUS_REJECTED,
            'approved_by' => $loader->id,
        ]);
    }

    public function test_maker_cannot_approve_own_request(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $loader = $this->loader();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $requestId = $this->actingAs($loader)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '25.00',
            'remarks' => 'Self-submitted for audit',
            'idempotency_key' => 'req-self',
        ])->assertCreated()->json('load_request_id');

        $this->actingAs($loader)->postJson(route('ast.admin.load-requests.approve', $requestId))
            ->assertStatus(422);

        $this->assertSame(0, AstWallet::query()->count());
    }

    public function test_support_cannot_approve_or_submit_without_reason(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $support = $this->support();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '10.00',
            'remarks' => 'ok',
            'idempotency_key' => 'req-short',
        ])->assertStatus(422);

        $requestId = $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '10.00',
            'remarks' => 'Office payment received',
            'idempotency_key' => 'req-ok',
        ])->assertCreated()->json('load_request_id');

        $this->actingAs($support)->postJson(route('ast.admin.load-requests.approve', $requestId))
            ->assertForbidden();

        $this->assertSame(0, AstWallet::query()->count());
        $this->assertDatabaseHas('wallet_load_requests', [
            'id' => $requestId,
            'status' => WalletLoadRequest::STATUS_PENDING,
        ]);
    }

    public function test_duplicate_pending_request_for_same_account_is_blocked(): void
    {
        Event::fake([WalletLoadApprovalRequested::class]);

        $support = $this->support();
        $member = $this->member();
        $this->linkAccount($member, '0102-334455');

        $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '10.00',
            'remarks' => 'First request for this account',
            'idempotency_key' => 'req-a',
        ])->assertCreated();

        $this->actingAs($support)->postJson(route('ast.admin.request.submit'), [
            'account_number' => '0102-334455',
            'amount' => '20.00',
            'remarks' => 'Second request for this account',
            'idempotency_key' => 'req-b',
        ])->assertStatus(422);
    }

    private function support(): User
    {
        return User::factory()->create([
            'role' => 'support',
        ]);
    }

    private function loader(): User
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
}
