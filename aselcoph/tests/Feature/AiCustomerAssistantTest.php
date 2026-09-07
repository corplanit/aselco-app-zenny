<?php

namespace Tests\Feature;

use App\Models\AccountLink;
use App\Models\AiConversation;
use App\Models\BillingUpload;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiCustomerAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
        config()->set('ai.enabled', true);
        config()->set('ai.openai.api_key', null);
        config()->set('rag.enabled', false);
    }

    public function test_bootstrap_lists_capabilities_and_limits(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/customer/ai/bootstrap')
            ->assertOk()
            ->assertJsonPath('limits.can_mutate_billing', false)
            ->assertJsonPath('limits.can_process_payments', false)
            ->assertJsonStructure(['welcome', 'suggested_questions', 'actions', 'capabilities']);
    }

    public function test_chat_includes_owned_billing_snapshot_without_mutating(): void
    {
        $customer = $this->customer();
        $staff = User::factory()->create(['role' => 'Customer Service', 'department_code' => 'CSR']);
        $link = AccountLink::query()->create([
            'user_id' => $customer->id,
            'account_number' => '0102-998877',
            'owner_name' => $customer->name,
            'validated_at' => now(),
            'validated_by' => 'test',
        ]);
        BillingUpload::query()->create([
            'account_link_id' => $link->id,
            'file_path' => 'billing_pdfs/test.pdf',
            'amount' => '250.00',
            'billing_date' => now()->toDateString(),
            'uploaded_by' => $staff->id,
            'status' => BillingUpload::STATUS_PENDING,
            'paid_amount' => '0.00',
            'balance_due' => '250.00',
        ]);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'How much is my amount due and how do I pay with AST?',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'billing')
            ->assertJsonPath('can_mutate_billing', false)
            ->assertJsonPath('billing_snapshot.amount_due', 250)
            ->assertJsonPath('billing_snapshot.pending_bill_count', 1);

        $this->assertStringContainsString('250', (string) $response->json('reply'));
        $this->assertSame(0, Ticket::query()->count());
        $this->assertDatabaseHas('billing_uploads', [
            'account_link_id' => $link->id,
            'balance_due' => '250.00',
            'status' => BillingUpload::STATUS_PENDING,
        ]);
    }

    public function test_human_request_marks_escalation(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'I want to talk to a human customer service agent please',
            ])
            ->assertOk()
            ->assertJsonPath('human_required', true)
            ->assertJsonPath('escalate', true)
            ->assertJsonPath('suggested_action', 'contact_support');
    }

    public function test_escalate_creates_ticket_in_official_workflow(): void
    {
        $customer = $this->customer();
        $category = TicketCategory::query()->where('department_code', 'FOCAL')->firstOrFail();

        $chat = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'Help me file a concern about slow response',
            ])
            ->assertOk();

        $conversationId = (int) $chat->json('conversation_id');

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/escalate', [
                'conversation_id' => $conversationId,
                'category_id' => $category->id,
                'description' => 'Need CSR follow-up on my concern about slow response.',
            ])
            ->assertCreated()
            ->assertJsonPath('escalated', true)
            ->assertJsonPath('mode', 'create_ticket')
            ->assertJsonPath('applies_financial_changes', false)
            ->assertJsonStructure(['ticket' => ['id', 'ticket_no', 'status']]);

        $this->assertDatabaseHas('tickets', [
            'customer_id' => $customer->id,
            'category_id' => $category->id,
            'subcategory' => 'ai_escalation',
            'status' => Ticket::STATUS_NEW,
        ]);
    }

    public function test_escalate_continues_existing_owned_ticket(): void
    {
        $customer = $this->customer();
        $category = TicketCategory::query()->where('department_code', 'CCAD')->firstOrFail();

        $ticket = app(\App\Services\TicketService::class)->createCustomerTicket($customer, [
            'category_id' => $category->id,
            'description' => 'Billing dispute already filed.',
            'channel' => 'app',
        ]);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/escalate', [
                'ticket_id' => $ticket->id,
            ])
            ->assertCreated()
            ->assertJsonPath('mode', 'continue_ticket')
            ->assertJsonPath('ticket.ticket_no', $ticket->ticket_no);

        $this->assertSame(1, Ticket::query()->where('customer_id', $customer->id)->count());
    }

    public function test_delete_conversation_is_scoped(): void
    {
        $alice = $this->customer('alice');
        $bob = $this->customer('bob');

        $id = (int) $this->actingAs($alice, 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', ['message' => 'Hello'])
            ->json('conversation_id');

        $this->actingAs($bob, 'sanctum')
            ->deleteJson('/api/v1/customer/ai/conversations/'.$id)
            ->assertNotFound();

        $this->actingAs($alice, 'sanctum')
            ->deleteJson('/api/v1/customer/ai/conversations/'.$id)
            ->assertOk()
            ->assertJsonPath('code', 'CONVERSATION_DELETED');

        $this->assertDatabaseMissing('ai_conversations', ['id' => $id]);
        $this->assertSame(0, AiConversation::query()->where('user_id', $alice->id)->count());
    }

    public function test_complaint_guidance_returns_categories_and_actions(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'Help me choose a complaint category for a billing overcharge',
            ])
            ->assertOk()
            ->assertJsonPath('intent', 'billing')
            ->assertJsonStructure([
                'actions',
                'follow_ups',
                'complaint_categories',
                'next_step',
            ]);
    }

    private function customer(string $suffix = ''): User
    {
        $emailSuffix = $suffix === '' ? uniqid('', true) : $suffix.'-'.uniqid('', true);

        return User::factory()->create([
            'email' => 'assistant-'.$emailSuffix.'@example.com',
            'role' => 'User',
            'name' => 'Maria Santos',
        ]);
    }
}
