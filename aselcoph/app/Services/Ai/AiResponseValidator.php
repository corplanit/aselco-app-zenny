<?php

namespace App\Services\Ai;

class AiResponseValidator
{
    public const INTENTS = ['general', 'billing', 'outage', 'complaint', 'account', 'unknown'];

    public const ACTIONS = ['none', 'file_ticket', 'view_tickets', 'pay_bill', 'contact_support'];

    public const CATEGORIES = ['TSD', 'COMD', 'CCAD', 'AO-CDS', 'ISD-CCSMDD', 'FOCAL'];

    /**
     * @param  array<string, mixed>  $payload
     * @return array{reply: string, intent: string, escalate: bool, suggested_action: string, category_hint: ?string}
     */
    public function validateCustomer(array $payload, string $fallbackReply): array
    {
        $reply = trim((string) ($payload['reply'] ?? $payload['draft'] ?? ''));
        if ($reply === '' || mb_strlen($reply) > 4000) {
            $reply = $fallbackReply;
        }

        $intent = $this->pick((string) ($payload['intent'] ?? ''), self::INTENTS, 'unknown');
        $action = $this->pick((string) ($payload['suggested_action'] ?? ''), self::ACTIONS, 'none');
        $escalate = filter_var($payload['escalate'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $hint = $payload['category_hint'] ?? null;
        $category = is_string($hint) && in_array($hint, self::CATEGORIES, true) ? $hint : null;

        if (in_array($action, ['file_ticket', 'contact_support'], true)) {
            $escalate = true;
        }

        return [
            'reply' => $reply,
            'intent' => $intent,
            'escalate' => $escalate,
            'suggested_action' => $action,
            'category_hint' => $category,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{draft: string, intent: string, notes_for_agent: string, escalate: bool}
     */
    public function validateAdmin(array $payload, string $fallbackDraft): array
    {
        $draft = trim((string) ($payload['draft'] ?? $payload['reply'] ?? ''));
        if ($draft === '' || mb_strlen($draft) > 4000) {
            $draft = $fallbackDraft;
        }

        $notes = trim((string) ($payload['notes_for_agent'] ?? ''));
        if (mb_strlen($notes) > 2000) {
            $notes = mb_substr($notes, 0, 2000);
        }

        return [
            'draft' => $draft,
            'intent' => $this->pick((string) ($payload['intent'] ?? ''), self::INTENTS, 'unknown'),
            'notes_for_agent' => $notes,
            'escalate' => filter_var($payload['escalate'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    private function pick(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}
