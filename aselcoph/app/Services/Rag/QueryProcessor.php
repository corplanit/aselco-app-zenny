<?php

namespace App\Services\Rag;

class QueryProcessor
{
    /**
     * @var array<string, list<string>>
     */
    private array $synonyms = [
        'bill' => ['billing', 'invoice', 'amount', 'due'],
        'billing' => ['bill', 'invoice', 'ast'],
        'ast' => ['token', 'wallet', 'pay'],
        'token' => ['ast', 'wallet'],
        'pay' => ['payment', 'settle', 'ast'],
        'outage' => ['interruption', 'brownout', 'blackout', 'power'],
        'interruption' => ['outage', 'brownout'],
        'complaint' => ['concern', 'ticket', 'report'],
        'concern' => ['complaint', 'ticket'],
        'ticket' => ['complaint', 'concern'],
        'connection' => ['service', 'membership'],
        'membership' => ['account', 'link'],
        'office' => ['hours', 'branch', 'faq'],
    ];

    /**
     * @return array{normalized: string, tokens: list<string>}
     */
    public function process(string $question): array
    {
        $normalized = mb_strtolower(trim($question));
        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? '';
        $normalized = trim(preg_replace('/\s+/u', ' ', $normalized) ?? '');

        $tokens = $this->tokenize($normalized);
        $expanded = $tokens;

        foreach ($tokens as $token) {
            foreach ($this->synonyms[$token] ?? [] as $syn) {
                $expanded[] = $syn;
            }
        }

        return [
            'normalized' => $normalized,
            'tokens' => array_values(array_unique($expanded)),
        ];
    }

    /**
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stop = [
            'the', 'a', 'an', 'and', 'or', 'to', 'of', 'in', 'on', 'for', 'is', 'are',
            'was', 'were', 'be', 'my', 'our', 'you', 'your', 'i', 'we', 'it', 'this',
            'that', 'with', 'from', 'at', 'as', 'do', 'how', 'what', 'when', 'can',
            'please', 'also', 'just', 'about', 'movie', 'watching',
        ];

        $out = [];
        foreach ($parts as $part) {
            if (mb_strlen($part) < 2 || in_array($part, $stop, true)) {
                continue;
            }
            $out[] = $part;
        }

        return array_values(array_unique($out));
    }
}
