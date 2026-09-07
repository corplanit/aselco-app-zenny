<?php

namespace App\Services\Rag;

class GroundedResponder
{
    /**
     * @param  array{hits: list<RetrievalHit>, sufficient: bool}  $retrieval
     * @return array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string, grounded: bool}
     */
    public function respond(string $message, array $retrieval, bool $injectionAttempt = false): array
    {
        if ($injectionAttempt) {
            return [
                'reply' => 'I can only help using approved ASELCO service information. I cannot change accounts, wallets, or tickets.',
                'intent' => 'unknown',
                'escalate' => false,
                'suggested_action' => 'none',
                'category_hint' => null,
                'grounded' => false,
            ];
        }

        if (! ($retrieval['sufficient'] ?? false) || ($retrieval['hits'] ?? []) === []) {
            return [
                'reply' => 'I do not have enough approved ASELCO documentation to answer that. I will not guess a policy or procedure. Please file a concern in the app or open Support so a staff member can help.',
                'intent' => 'unknown',
                'escalate' => true,
                'suggested_action' => 'contact_support',
                'category_hint' => null,
                'grounded' => false,
            ];
        }

        /** @var RetrievalHit $top */
        $top = $retrieval['hits'][0];
        $excerpt = $this->excerpt($top->content);
        $intent = $this->intentFromCategory((string) ($top->metadata['category'] ?? ''));

        return [
            'reply' => "Based on approved ASELCO documentation ({$top->title}): {$excerpt} If this does not match your situation, file a concern or contact Support — I cannot change bills, wallets, or tickets.",
            'intent' => $intent,
            'escalate' => in_array($intent, ['outage', 'complaint'], true),
            'suggested_action' => $this->actionFromIntent($intent),
            'category_hint' => $this->hintFromIntent($intent),
            'grounded' => true,
        ];
    }

    private function excerpt(string $content): string
    {
        $content = trim(preg_replace('/\s+/', ' ', $content) ?? $content);

        return \Illuminate\Support\Str::limit($content, 420, '…');
    }

    private function intentFromCategory(string $slug): string
    {
        return match ($slug) {
            'billing-information' => 'billing',
            'complaint-handling' => 'complaint',
            'approved-service-information' => 'outage',
            'service-documentation' => 'account',
            default => 'general',
        };
    }

    private function actionFromIntent(string $intent): string
    {
        return match ($intent) {
            'billing' => 'pay_bill',
            'outage', 'complaint' => 'file_ticket',
            default => 'none',
        };
    }

    private function hintFromIntent(string $intent): ?string
    {
        return match ($intent) {
            'outage' => 'TSD',
            'billing' => 'CCAD',
            'complaint' => 'FOCAL',
            'account' => 'AO-CDS',
            default => null,
        };
    }
}
