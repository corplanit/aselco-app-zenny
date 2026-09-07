<?php

namespace Tests\Feature;

use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\ApprovedKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RagKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tickets.admin_roles', ['Administrator', 'administrator']);
        config()->set('tickets.supervisor_roles', ['Supervisor', 'supervisor']);
        config()->set('tickets.csr_roles', ['Customer Service', 'support']);
        config()->set('ai.enabled', true);
        config()->set('ai.openai.api_key', null);
        config()->set('rag.enabled', true);
        config()->set('rag.min_score', 0.12);

        $this->seed(ApprovedKnowledgeSeeder::class);
    }

    public function test_admin_can_list_and_search_knowledge(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/admin/knowledge')
            ->assertOk()
            ->assertJsonPath('total', 27);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/admin/knowledge/search', [
                'query' => 'How do I pay my electric bill with AST tokens?',
            ])
            ->assertOk()
            ->assertJsonPath('sufficient', true)
            ->assertJsonFragment(['title' => 'Payment']);
    }

    public function test_retrieval_covers_policy_faq_billing_service_and_complaints(): void
    {
        $staff = $this->staff();

        $cases = [
            ['What is the membership privacy policy for linked accounts?', 'Consumer Account'],
            ['What are ASELCO customer service office hours?', 'Frequently Asked Questions'],
            ['How do I settle my bill using AST?', 'Payment'],
            ['How do I request a new service connection?', 'New Connection'],
            ['How do I submit a complaint in the app?', 'Complaint Handling'],
            ['There is a power interruption and sparking line', 'Outage Reporting'],
        ];

        foreach ($cases as [$query, $title]) {
            $this->actingAs($staff, 'sanctum')
                ->postJson('/api/v1/admin/knowledge/search', ['query' => $query])
                ->assertOk()
                ->assertJsonPath('sufficient', true)
                ->assertJsonFragment(['title' => $title]);
        }
    }

    public function test_unknown_question_is_insufficient_and_chat_does_not_fabricate(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/search', [
                'query' => 'What is the CEO salary and secret board bonus formula?',
            ])
            ->assertOk()
            ->assertJsonPath('sufficient', false)
            ->assertJsonPath('hits', []);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'What is the CEO salary and secret board bonus formula?',
            ])
            ->assertOk()
            ->assertJsonPath('knowledge_sufficient', false)
            ->assertJsonPath('escalate', true);

        $this->assertStringContainsString('approved ASELCO documentation', (string) $response->json('reply'));
    }

    public function test_paraphrase_and_noisy_question_still_retrieve_billing(): void
    {
        $customer = $this->customer();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/customer/ai/search', [
                'query' => 'I was watching a movie and also how can I settle my electric account using tokens?',
            ])
            ->assertOk()
            ->assertJsonPath('sufficient', true)
            ->assertJsonFragment(['title' => 'Payment']);
    }

    public function test_inactive_documents_are_excluded_from_retrieval(): void
    {
        $staff = $this->staff();
        $doc = KnowledgeDocument::query()->where('title', 'Payment')->firstOrFail();

        $this->actingAs($staff, 'sanctum')
            ->putJson('/api/v1/admin/knowledge/'.$doc->id, [
                'status' => 'inactive',
            ])
            ->assertOk();

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/admin/knowledge/search', [
                'query' => 'How do I pay with AST tokens?',
            ])
            ->assertOk()
            ->assertJsonMissing(['title' => 'Payment']);
    }

    public function test_upload_indexes_and_reindex_endpoint_works(): void
    {
        Storage::fake('local');
        $staff = $this->staff();
        $categoryId = \App\Models\KnowledgeCategory::query()->where('slug', 'faq')->value('id');

        $file = UploadedFile::fake()->createWithContent('hours.txt', "ASELCO Saturday satellite desk is closed. Weekday helpdesk remains 8 AM to 5 PM.");

        $created = $this->actingAs($staff, 'sanctum')
            ->post('/api/v1/admin/knowledge', [
                'title' => 'Weekend Desk Policy',
                'category_id' => $categoryId,
                'department' => 'CSR',
                'source' => 'Ops memo',
                'status' => 'active',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json();

        $this->assertDatabaseHas('knowledge_chunks', [
            'document_id' => $created['id'],
            'active' => true,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/v1/admin/knowledge/'.$created['id'].'/index')
            ->assertOk()
            ->assertJsonPath('message', 'Document re-indexed.');
    }

    public function test_customer_cannot_manage_admin_knowledge(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->getJson('/api/v1/admin/knowledge')
            ->assertForbidden();
    }

    public function test_chat_uses_retrieved_knowledge_when_available(): void
    {
        $this->actingAs($this->customer(), 'sanctum')
            ->postJson('/api/v1/customer/ai/chat', [
                'message' => 'Explain ASELCO Tokens AST for paying bills',
            ])
            ->assertOk()
            ->assertJsonPath('knowledge_used', true)
            ->assertJsonPath('knowledge_sufficient', true)
            ->assertJsonPath('source', 'rag')
            ->assertJsonStructure(['citations' => [['title', 'score', 'excerpt']]]);
    }

    private function staff(): User
    {
        return User::factory()->create([
            'role' => 'Customer Service',
            'department_code' => 'CSR',
        ]);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'email' => 'rag-customer-'.uniqid('', true).'@example.com',
            'role' => 'User',
        ]);
    }
}
