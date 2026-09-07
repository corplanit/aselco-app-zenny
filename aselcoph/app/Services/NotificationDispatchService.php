<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationDispatchService
{
    public function __construct(private FcmPushService $fcm)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{notification: ?AppNotification, pushed_count: int, had_device_tokens: bool}
     */
    public function deliverUserNotification(
        User|int $user,
        string $category,
        string $title,
        string $body,
        array $data = [],
        bool $push = true
    ): array {
        $userId = $user instanceof User ? $user->id : $user;
        $category = $this->normalizeCategory($category, $data);
        $data = $this->withStaffUrl($data, $category);

        $prefs = NotificationPreference::forUser($userId);
        if (! $prefs->allows($category)) {
            Log::info('notifications.skipped_pref', [
                'user_id' => $userId,
                'category' => $category,
            ]);

            return [
                'notification' => null,
                'pushed_count' => 0,
                'had_device_tokens' => false,
            ];
        }

        $notification = AppNotification::query()->create([
            'user_id' => $userId,
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'data' => $data === [] ? null : $data,
        ]);

        $tokens = collect();
        $pushedCount = 0;

        if ($push) {
            $tokens = DeviceToken::query()
                ->where('user_id', $userId)
                ->pluck('token');

            $payload = array_merge($data, [
                'notification_id' => (string) $notification->id,
                'category' => $category,
            ]);

            $pushedCount = $this->fcm->sendToTokens($tokens, $title, $body, $payload);
        }

        return [
            'notification' => $notification,
            'pushed_count' => $pushedCount,
            'had_device_tokens' => $tokens->isNotEmpty(),
        ];
    }

    /**
     * Persist an in-app notification and optionally push via FCM.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(
        User|int $user,
        string $category,
        string $title,
        string $body,
        array $data = [],
        bool $push = true
    ): ?AppNotification {
        return $this->deliverUserNotification($user, $category, $title, $body, $data, $push)['notification'];
    }

    public function notifyBilling(User|int $user, string $title, string $body, array $data = []): ?AppNotification
    {
        return $this->notifyUser($user, 'billing', $title, $body, array_merge([
            'deep_link' => '/tabs/ledger',
        ], $data));
    }

    public function notifyService(User|int $user, string $title, string $body, array $data = []): ?AppNotification
    {
        return $this->notifyUser($user, 'service', $title, $body, array_merge([
            'deep_link' => '/tabs/home',
        ], $data));
    }

    public function notifyAlert(User|int $user, string $title, string $body, array $data = []): ?AppNotification
    {
        return $this->notifyUser($user, 'alert', $title, $body, array_merge([
            'deep_link' => '/notifications',
        ], $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyChat(User|int $user, string $title, string $body, array $data = []): ?AppNotification
    {
        return $this->notifyUser($user, 'chat', $title, $body, array_merge([
            'deep_link' => '/support/chat',
        ], $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeCategory(string $category, array $data): string
    {
        $category = strtolower(trim($category));
        if (in_array($category, AppNotification::CATEGORIES, true)) {
            return $category;
        }
        if (isset($data['announcement_id'])) {
            return 'announcement';
        }
        if (isset($data['ticket_id'])) {
            return 'ticket';
        }
        if (isset($data['load_request_id'])) {
            return 'wallet';
        }
        if (isset($data['conversation_id']) || isset($data['message_id'])) {
            return 'chat';
        }
        if (isset($data['document_id'])) {
            return 'knowledge';
        }
        if (isset($data['complaint_id'])) {
            return 'complaint';
        }

        return 'alert';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withStaffUrl(array $data, string $category): array
    {
        if (filled($data['url'] ?? null)) {
            return $data;
        }

        try {
            $data['url'] = match (true) {
                filled($data['ticket_id'] ?? null) => route('tickets.show', $data['ticket_id']),
                filled($data['load_request_id'] ?? null) => route('ast.admin.load-requests', ['status' => 'pending']),
                filled($data['announcement_id'] ?? null) => route('announcements.show', $data['announcement_id']),
                filled($data['document_id'] ?? null) => route('knowledge.show', $data['document_id']),
                filled($data['complaint_id'] ?? null) => url('/complaint'),
                filled($data['conversation_id'] ?? null) => url('/chats'),
                filled($data['customer_id'] ?? null) => route('ast.admin.customer-wallet', $data['customer_id']),
                $category === 'wallet' => route('ast.admin.dashboard'),
                $category === 'knowledge' => route('knowledge.index'),
                $category === 'announcement' => route('announcements.index'),
                $category === 'complaint' => url('/complaint'),
                $category === 'chat' => url('/chats'),
                default => route('workspace.notifications'),
            };
        } catch (\Throwable) {
            $data['url'] = '/workspace/notifications';
        }

        return $data;
    }
}
