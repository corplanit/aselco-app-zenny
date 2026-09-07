<?php

namespace App\Services\Ai;

class LocalAiFallback
{
    /**
     * @return array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string}
     */
    public function customer(string $message, bool $injectionAttempt = false): array
    {
        if ($injectionAttempt) {
            return [
                'reply' => 'I can only help with ASELCO services, billing questions, and filing a concern. I cannot change accounts, wallets, or tickets. Tell me what you need help with, or open Support / Report a Concern.',
                'intent' => 'unknown',
                'escalate' => false,
                'suggested_action' => 'none',
                'category_hint' => null,
            ];
        }

        $p = mb_strtolower($message);

        if ($this->matches($p, [
            'talk to a human', 'talk to human', 'human agent', 'real person',
            'customer service agent', 'live agent', 'speak to someone', 'speak to a person',
        ])) {
            return [
                'reply' => 'I can connect you with Customer Service. Open Support for contact channels, or create a complaint ticket so CSR can route your case through the official workflow. I cannot change bills or accounts myself.',
                'intent' => 'general',
                'escalate' => true,
                'suggested_action' => 'contact_support',
                'category_hint' => 'FOCAL',
            ];
        }

        if ($this->matches($p, ['outage', 'brownout', 'no power', 'power interruption', 'blackout', 'downed line', 'sparking'])) {
            return [
                'reply' => 'For a power interruption or unsafe line, file a Distribution Line / Power Interruption concern in Report a Concern so crews are routed through the ticket workflow. Include your location and what you observed. I cannot dispatch a crew myself.',
                'intent' => 'outage',
                'escalate' => true,
                'suggested_action' => 'file_ticket',
                'category_hint' => 'COMD',
            ];
        }

        if ($this->matches($p, ['bill', 'billing', 'amount due', 'overcharge', 'meter', 'kwh', 'ast', 'token', 'pay'])) {
            return [
                'reply' => 'You can review amount due on Home and pay with ASELCO Tokens (AST) from the Pay tab. AST is closed-loop credit, not cash. If a bill looks wrong, file a Billing & Collection concern — I cannot change amounts or process payments.',
                'intent' => 'billing',
                'escalate' => $this->matches($p, ['wrong', 'dispute', 'overcharge', 'not mine']),
                'suggested_action' => $this->matches($p, ['wrong', 'dispute', 'overcharge']) ? 'file_ticket' : 'pay_bill',
                'category_hint' => $this->matches($p, ['wrong', 'dispute', 'overcharge']) ? 'CCAD' : null,
            ];
        }

        if ($this->matches($p, ['status of my', 'my ticket', 'open ticket', 'ticket status', 'my concern'])) {
            return [
                'reply' => 'Open My Tickets to see status updates for concerns you already filed. I can only describe tickets that belong to your account, and I cannot change ticket status.',
                'intent' => 'complaint',
                'escalate' => false,
                'suggested_action' => 'view_tickets',
                'category_hint' => null,
            ];
        }

        if ($this->matches($p, ['complaint', 'concern', 'file', 'report', 'ticket', 'category'])) {
            return [
                'reply' => 'To submit a complaint: open Report a Concern, pick the matching category (outage, billing, meter/connection, institutional, or other), describe what happened, then track it under My Tickets. I guide you into that workflow — I do not create uncontrolled parallel cases.',
                'intent' => 'complaint',
                'escalate' => true,
                'suggested_action' => 'file_ticket',
                'category_hint' => 'FOCAL',
            ];
        }

        if ($this->matches($p, ['account', 'membership', 'link account', 'member', 'office hours', 'faq', 'service'])) {
            $isAccount = $this->matches($p, ['account', 'membership', 'link account', 'member']);

            return [
                'reply' => $isAccount
                    ? 'Account linking is handled in Membership setup. Staff validate links — the assistant cannot approve an account. If something is stuck, file a concern or contact Support.'
                    : 'I can help with ASELCO services, FAQs, billing guidance, and complaint steps using approved knowledge. What would you like to know?',
                'intent' => $isAccount ? 'account' : 'general',
                'escalate' => false,
                'suggested_action' => $isAccount ? 'contact_support' : 'none',
                'category_hint' => $isAccount ? 'AO-CDS' : null,
            ];
        }

        return [
            'reply' => 'I can help with billing, service information, FAQs, and complaint guidance. What do you need help with today?',
            'intent' => 'general',
            'escalate' => false,
            'suggested_action' => 'none',
            'category_hint' => null,
        ];
    }

    /**
     * @return array{draft: string, intent: string, notes_for_agent: string, escalate: bool}
     */
    public function admin(string $message): array
    {
        $customer = $this->customer($message);

        return [
            'draft' => $customer['reply'],
            'intent' => $customer['intent'],
            'notes_for_agent' => $customer['escalate']
                ? 'Suggested next step: keep the existing ticket workflow (endorse/assign). Do not apply AI text as a status change.'
                : 'Guidance only. Confirm against CIS/ticket records before sending.',
            'escalate' => $customer['escalate'],
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
