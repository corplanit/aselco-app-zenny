<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsDeliveryService
{
    public function isConfigured(): bool
    {
        return filled(config('services.sms.webhook_url'));
    }

    public function send(string $to, string $message): bool
    {
        $to = trim($to);
        $message = trim($message);

        if ($to === '' || $message === '') {
            return false;
        }

        if (! $this->isConfigured()) {
            Log::info('sms.skipped_unconfigured', ['to' => $to]);

            return false;
        }

        try {
            $response = Http::withToken((string) config('services.sms.token'))
                ->acceptJson()
                ->timeout(15)
                ->post((string) config('services.sms.webhook_url'), [
                    'to' => $to,
                    'message' => $message,
                    'sender' => config('services.sms.sender'),
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('sms.send_failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('sms.send_exception', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
