<?php

namespace App\Services\Ai;

class EscalationDetector
{
    /**
     * @return array{needs_human: bool, reason: string|null, force_action: string|null}
     */
    public function detect(string $message, array $payload = []): array
    {
        $p = mb_strtolower($message);

        if ($this->matches($p, [
            'talk to a human',
            'talk to human',
            'speak to a person',
            'speak to someone',
            'real person',
            'customer service agent',
            'human agent',
            'human representative',
            'live agent',
            'call me',
            'agent please',
            'csr please',
        ])) {
            return [
                'needs_human' => true,
                'reason' => 'customer_requested_human',
                'force_action' => 'contact_support',
            ];
        }

        if ($this->matches($p, [
            'change my bill',
            'adjust my bill',
            'waive',
            'refund my',
            'load ast for me',
            'add tokens',
            'credit my wallet',
            'close my ticket',
            'reassign',
            'approve my account',
            'unlink my account',
        ])) {
            return [
                'needs_human' => true,
                'reason' => 'requires_account_or_billing_intervention',
                'force_action' => 'file_ticket',
            ];
        }

        if (! empty($payload['escalate'])) {
            return [
                'needs_human' => true,
                'reason' => 'assistant_escalation_flag',
                'force_action' => $payload['suggested_action'] ?? 'file_ticket',
            ];
        }

        return [
            'needs_human' => false,
            'reason' => null,
            'force_action' => null,
        ];
    }

    /**
     * @param  list<string>  $needles
     */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
