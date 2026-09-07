<?php

namespace App\Services\Rag;

class DocumentProcessor
{
    public function extractFromUpload(?string $contents, ?string $extension, string $fallbackBody = ''): string
    {
        $ext = strtolower((string) $extension);
        $raw = $contents ?? '';

        if ($raw === '') {
            return $this->clean($fallbackBody);
        }

        if (in_array($ext, ['html', 'htm'], true)) {
            $raw = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $this->clean($raw !== '' ? $raw : $fallbackBody);
    }

    public function clean(string $text): string
    {
        $text = str_replace("\x00", '', $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        $size = max(200, (int) config('rag.chunk_size', 700));
        $overlap = max(0, min($size - 50, (int) config('rag.chunk_overlap', 80)));
        $text = $this->clean($text);

        if ($text === '') {
            return [];
        }

        if (mb_strlen($text) <= $size) {
            return [$text];
        }

        $paragraphs = preg_split("/\n\s*\n/", $text) ?: [$text];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($buffer.' '.$paragraph) <= $size) {
                $buffer = trim($buffer.' '.$paragraph);
                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $buffer;
            }

            if (mb_strlen($paragraph) <= $size) {
                $buffer = $paragraph;
                continue;
            }

            $chunks = array_merge($chunks, $this->window($paragraph, $size, $overlap));
            $buffer = '';
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return array_values(array_filter($chunks));
    }

    /**
     * @return list<string>
     */
    private function window(string $text, int $size, int $overlap): array
    {
        $chunks = [];
        $start = 0;
        $len = mb_strlen($text);

        while ($start < $len) {
            $chunks[] = trim(mb_substr($text, $start, $size));
            $next = $start + $size - $overlap;
            if ($next <= $start) {
                break;
            }
            $start = $next;
        }

        return $chunks;
    }
}
