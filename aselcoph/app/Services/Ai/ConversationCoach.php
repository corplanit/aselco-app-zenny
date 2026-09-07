<?php

namespace App\Services\Ai;

/**
 * Follow-up prompts and guided suggestions for the customer assistant UI.
 */
class ConversationCoach
{
    /**
     * @param  list<array<string, mixed>>  $citations
     * @return list<string>
     */
    public function followUps(string $intent, bool $escalate, array $citations = []): array
    {
        return match ($intent) {
            'billing' => [
                'How do I pay with AST?',
                'My bill amount looks wrong',
                'Where do I see amount due?',
            ],
            'outage' => [
                'Help me file a power interruption concern',
                'What information should I include?',
                'How do I track my ticket?',
            ],
            'complaint' => [
                'Which category should I choose?',
                'Help me write a complaint description',
                'Show my open tickets',
            ],
            'account' => [
                'How do I link my membership account?',
                'My account link is still pending',
                'Contact Customer Service',
            ],
            default => $citations !== []
                ? ['Tell me more about that', 'How do I file a concern?', 'Contact Customer Service']
                : (
                    $escalate
                        ? ['Create a complaint ticket', 'Contact Customer Service', 'View my tickets']
                        : ['Billing Inquiry', 'Report Outage', 'How do I file a complaint?']
                ),
        };
    }

    /**
     * @return list<array{label: string, message: string}>
     */
    public function suggestedQuestions(): array
    {
        return [
            ['label' => 'Billing help', 'message' => 'Explain how billing and AST payments work'],
            ['label' => 'Amount due', 'message' => 'How can I see and pay my amount due?'],
            ['label' => 'Report outage', 'message' => 'I have a power interruption. What should I do?'],
            ['label' => 'File a complaint', 'message' => 'Help me choose a complaint category and prepare a description'],
            ['label' => 'Ticket status', 'message' => 'What is the status of my open tickets?'],
            ['label' => 'Talk to CSR', 'message' => 'I want to talk to a human customer service representative'],
        ];
    }

    /**
     * @return list<array{label: string, href: string, action: string}>
     */
    public function actions(string $suggestedAction, bool $escalate): array
    {
        $actions = [
            [
                'label' => 'Contact Customer Service',
                'href' => '/support',
                'action' => 'contact_support',
            ],
            [
                'label' => 'Create Complaint / Ticket',
                'href' => '/complaints',
                'action' => 'file_ticket',
            ],
        ];

        if ($suggestedAction === 'pay_bill') {
            array_unshift($actions, [
                'label' => 'Pay with AST',
                'href' => '/tabs/pay',
                'action' => 'pay_bill',
            ]);
        }

        if ($suggestedAction === 'view_tickets' || $escalate) {
            $actions[] = [
                'label' => 'View My Tickets',
                'href' => '/tabs/tickets',
                'action' => 'view_tickets',
            ];
        }

        return $actions;
    }
}
