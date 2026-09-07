<?php

namespace Database\Seeders;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Rag\KnowledgeBaseService;
use Illuminate\Database\Seeder;

class ApprovedKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        /** @var KnowledgeBaseService $kb */
        $kb = app(KnowledgeBaseService::class);

        $actor = User::query()->first() ?? User::factory()->create([
            'name' => 'Knowledge Admin',
            'email' => 'knowledge-admin@example.com',
            'role' => 'Administrator',
        ]);

        $categories = [
            ['slug' => 'company', 'name' => 'Company', 'description' => 'ASELCO identity, services, and coverage', 'sort_order' => 1],
            ['slug' => 'customer', 'name' => 'Customer', 'description' => 'Accounts, connections, and membership', 'sort_order' => 2],
            ['slug' => 'billing', 'name' => 'Billing', 'description' => 'Bills, rates, and disputes', 'sort_order' => 3],
            ['slug' => 'payment', 'name' => 'Payment', 'description' => 'Payment channels, AST, and posting', 'sort_order' => 4],
            ['slug' => 'outage', 'name' => 'Outage', 'description' => 'Interruptions, restoration, and safety', 'sort_order' => 5],
            ['slug' => 'meter', 'name' => 'Meter', 'description' => 'Readings, concerns, and tampering', 'sort_order' => 6],
            ['slug' => 'complaints', 'name' => 'Complaints', 'description' => 'Concern handling and escalation', 'sort_order' => 7],
            ['slug' => 'faq', 'name' => 'FAQ', 'description' => 'Frequently asked questions', 'sort_order' => 8],
            ['slug' => 'ai', 'name' => 'AI', 'description' => 'Assistant response, safety, and escalation rules', 'sort_order' => 9],
        ];

        foreach ($categories as $category) {
            $kb->ensureCategory($category);
        }

        $base = database_path('seeders/knowledge-base');
        $docs = [
            ['file' => 'company/company-profile.md', 'title' => 'Company Profile', 'department' => 'AO-CDS', 'service_type' => 'company'],
            ['file' => 'company/company-services.md', 'title' => 'Company Services', 'department' => 'AO-CDS', 'service_type' => 'service'],
            ['file' => 'company/service-area.md', 'title' => 'Service Area', 'department' => 'AO-CDS', 'service_type' => 'coverage'],
            ['file' => 'customer/consumer-account.md', 'title' => 'Consumer Account', 'department' => 'AO-CDS', 'service_type' => 'account'],
            ['file' => 'customer/new-connection.md', 'title' => 'New Connection', 'department' => 'AO-CDS', 'service_type' => 'connection'],
            ['file' => 'customer/reconnection.md', 'title' => 'Reconnection', 'department' => 'AO-CDS', 'service_type' => 'reconnection'],
            ['file' => 'customer/account-management.md', 'title' => 'Account Management', 'department' => 'AO-CDS', 'service_type' => 'account'],
            ['file' => 'billing/billing.md', 'title' => 'Billing', 'department' => 'CCAD', 'service_type' => 'billing'],
            ['file' => 'billing/rates-and-charges.md', 'title' => 'Rates and Charges', 'department' => 'CCAD', 'service_type' => 'billing'],
            ['file' => 'billing/billing-disputes.md', 'title' => 'Billing Disputes', 'department' => 'CCAD', 'service_type' => 'billing'],
            ['file' => 'payment/payment.md', 'title' => 'Payment', 'department' => 'CCAD', 'service_type' => 'payment'],
            ['file' => 'payment/payment-channels.md', 'title' => 'Payment Channels', 'department' => 'CCAD', 'service_type' => 'payment'],
            ['file' => 'payment/payment-not-reflected.md', 'title' => 'Payment Not Reflected', 'department' => 'CCAD', 'service_type' => 'payment'],
            ['file' => 'outage/outage-reporting.md', 'title' => 'Outage Reporting', 'department' => 'TSD', 'service_type' => 'outage'],
            ['file' => 'outage/planned-outages.md', 'title' => 'Planned Outages', 'department' => 'TSD', 'service_type' => 'outage'],
            ['file' => 'outage/restoration.md', 'title' => 'Restoration', 'department' => 'TSD', 'service_type' => 'outage'],
            ['file' => 'outage/outage-safety.md', 'title' => 'Outage Safety', 'department' => 'TSD', 'service_type' => 'safety'],
            ['file' => 'meter/meter-reading.md', 'title' => 'Meter Reading', 'department' => 'TSD', 'service_type' => 'meter'],
            ['file' => 'meter/meter-concerns.md', 'title' => 'Meter Concerns', 'department' => 'TSD', 'service_type' => 'meter'],
            ['file' => 'meter/meter-tampering.md', 'title' => 'Meter Tampering', 'department' => 'TSD', 'service_type' => 'meter'],
            ['file' => 'complaints/complaint-handling.md', 'title' => 'Complaint Handling', 'department' => 'FOCAL', 'service_type' => 'complaints'],
            ['file' => 'complaints/complaint-categories.md', 'title' => 'Complaint Categories', 'department' => 'FOCAL', 'service_type' => 'complaints'],
            ['file' => 'complaints/escalation.md', 'title' => 'Escalation', 'department' => 'FOCAL', 'service_type' => 'assistance'],
            ['file' => 'faq/frequently-asked-questions.md', 'title' => 'Frequently Asked Questions', 'department' => 'CSR', 'service_type' => 'general'],
            ['file' => 'ai/ai-response-rules.md', 'title' => 'AI Response Rules', 'department' => 'CSR', 'service_type' => 'support'],
            ['file' => 'ai/ai-safety-rules.md', 'title' => 'AI Safety Rules', 'department' => 'CSR', 'service_type' => 'safety'],
            ['file' => 'ai/ai-escalation-rules.md', 'title' => 'AI Escalation Rules', 'department' => 'CSR', 'service_type' => 'assistance'],
        ];

        foreach ($docs as $doc) {
            $path = $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $doc['file']);
            if (! is_file($path)) {
                throw new \RuntimeException('Missing knowledge file: '.$doc['file']);
            }

            $body = trim((string) file_get_contents($path));
            $categorySlug = explode('/', $doc['file'])[0];
            $category = KnowledgeCategory::query()->where('slug', $categorySlug)->firstOrFail();

            $payload = [
                'category_id' => $category->id,
                'title' => $doc['title'],
                'department' => $doc['department'],
                'service_type' => $doc['service_type'],
                'source' => 'ASELCO Knowledge Base 1.0',
                'status' => KnowledgeDocument::STATUS_ACTIVE,
                'body' => $body,
                'effective_at' => now()->subDay(),
            ];

            $existing = KnowledgeDocument::query()->where('title', $doc['title'])->first();
            if ($existing) {
                $kb->update($actor, $existing, $payload);
                continue;
            }

            $kb->create($actor, $payload);
        }
    }
}
