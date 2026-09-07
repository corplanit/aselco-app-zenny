<?php

namespace App\Services;

use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Models\Chats\Conversation;
use App\Models\Chats\ConversationParticipant;
use App\Models\Chats\Message;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SupportChatService
{
    public const KIND_SUPPORT = 'support';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public function __construct(
        private AccessService $access,
        private NotificationDispatchService $notifications,
    ) {
    }

    public function isSupportAgent(?User $user): bool
    {
        return $this->access->isSupport($user);
    }

    /**
     * @return Collection<int, User>
     */
    public function supportAgents(): Collection
    {
        return User::query()
            ->where(function ($query) {
                $query->whereIn('role', [
                    'support',
                    'Administrator',
                    'administrator',
                    'Customer Service',
                    'Supervisor',
                    'supervisor',
                ])->orWhere('user_type', 'support');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'user_type', 'profile_photo_path']);
    }

    public function ensureForCustomer(User $customer): Conversation
    {
        $existing = Conversation::query()
            ->where('kind', self::KIND_SUPPORT)
            ->where('customer_id', $customer->id)
            ->where('status', self::STATUS_OPEN)
            ->first();

        if ($existing) {
            $this->syncParticipants($existing, $customer);

            return $existing->fresh([
                'participants.user:id,name,email,profile_photo_path',
                'lastMessage',
            ]);
        }

        return DB::transaction(function () use ($customer) {
            $conversation = Conversation::query()->create([
                'type' => 'group',
                'kind' => self::KIND_SUPPORT,
                'customer_id' => $customer->id,
                'status' => self::STATUS_OPEN,
                'name' => 'Support - '.$customer->name,
                'is_group' => true,
                'created_by' => $customer->id,
            ]);

            $this->syncParticipants($conversation, $customer);

            return $conversation->fresh([
                'participants.user:id,name,email,profile_photo_path',
                'lastMessage',
            ]);
        });
    }

    public function syncParticipants(Conversation $conversation, User $customer): void
    {
        $now = now();
        $agentIds = $this->supportAgents()->pluck('id')->all();
        $memberIds = array_values(array_unique(array_merge([$customer->id], $agentIds)));

        foreach ($memberIds as $userId) {
            // DB column is typically ENUM('owner','member') — not customer/agent.
            $role = (int) $userId === (int) $customer->id ? 'owner' : 'member';
            ConversationParticipant::query()->updateOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ],
                [
                    'role' => $role,
                    'joined_at' => $now,
                    'left_at' => null,
                ]
            );
        }
    }

    public function assertCanAccess(User $user, Conversation $conversation): void
    {
        abort_unless($conversation->kind === self::KIND_SUPPORT, 404);

        if ($this->isSupportAgent($user)) {
            return;
        }

        abort_unless((int) $conversation->customer_id === (int) $user->id, 403);
        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->whereNull('left_at')->exists(),
            403
        );
    }

    public function sendMessage(User $sender, Conversation $conversation, string $body): Message
    {
        $this->assertCanAccess($sender, $conversation);
        abort_unless($conversation->status === Conversation::STATUS_OPEN, 422, 'This conversation is closed.');

        $message = DB::transaction(function () use ($conversation, $sender, $body) {
            $msg = Message::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $sender->id,
                'type' => 'text',
                'body' => $body,
            ]);

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $sender->id)
                ->update([
                    'last_read_message_id' => $msg->id,
                    'last_read_at' => now(),
                    'unread_count' => 0,
                ]);

            $conversation->touch();

            $participantIds = $conversation->participants()->whereNull('left_at')->pluck('user_id')->all();
            broadcast(new MessageSent($msg->load('user'), $participantIds))->toOthers();

            foreach ($participantIds as $pid) {
                if ((int) $pid === (int) $sender->id) {
                    $unread = 0;
                } else {
                    ConversationParticipant::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('user_id', $pid)
                        ->increment('unread_count');
                    $unread = (int) ConversationParticipant::query()
                        ->where('conversation_id', $conversation->id)
                        ->where('user_id', $pid)
                        ->value('unread_count');
                }
                broadcast(new ConversationUpdated(
                    $conversation->fresh(['lastMessage', 'participants.user', 'customer']),
                    $pid,
                    $unread
                ));
            }

            return $msg->load('user:id,name,profile_photo_path');
        });

        $this->pushNewMessageNotifications($sender, $conversation, $message);

        return $message;
    }

    private function pushNewMessageNotifications(User $sender, Conversation $conversation, Message $message): void
    {
        $preview = trim((string) $message->body);
        if ($preview === '') {
            $preview = 'Sent an attachment';
        }
        if (mb_strlen($preview) > 140) {
            $preview = mb_substr($preview, 0, 137).'…';
        }

        $senderName = $message->user?->name ?? $sender->name ?? 'Support';
        $customerName = $conversation->relationLoaded('customer')
            ? ($conversation->customer?->name)
            : User::query()->whereKey($conversation->customer_id)->value('name');

        $participantIds = $conversation->participants()
            ->whereNull('left_at')
            ->pluck('user_id')
            ->all();

        foreach ($participantIds as $pid) {
            if ((int) $pid === (int) $sender->id) {
                continue;
            }

            $isCustomerRecipient = (int) $pid === (int) $conversation->customer_id;
            $title = $isCustomerRecipient
                ? 'Support Team'
                : ($customerName ?: 'Customer chat');
            $body = $isCustomerRecipient
                ? $preview
                : $senderName.': '.$preview;

            try {
                $this->notifications->notifyChat($pid, $title, $body, [
                    'conversation_id' => (string) $conversation->id,
                    'message_id' => (string) $message->id,
                    'deep_link' => '/support/chat',
                ]);
            } catch (Throwable $e) {
                Log::warning('support_chat.push_failed', [
                    'conversation_id' => $conversation->id,
                    'recipient_id' => $pid,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function shapeConversation(Conversation $conversation, User $viewer): array
    {
        $customer = $conversation->relationLoaded('customer')
            ? $conversation->customer
            : User::query()->find($conversation->customer_id);

        $latest = $conversation->relationLoaded('lastMessage')
            ? $conversation->lastMessage
            : $conversation->messages()->latest('id')->first();

        $pivot = $conversation->participants->firstWhere('user_id', $viewer->id);
        $isAgent = $this->isSupportAgent($viewer);

        return [
            'id' => $conversation->id,
            'kind' => $conversation->kind,
            'status' => $conversation->status,
            'customer_id' => $conversation->customer_id,
            'title' => $isAgent
                ? ($customer?->name ?? 'Customer')
                : 'Support Team',
            'customer' => $customer ? [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'avatar' => $customer->profile_photo_url ?? null,
            ] : null,
            'last_message' => $latest?->body,
            'last_at' => optional($latest?->created_at ?? $conversation->updated_at)?->toIso8601String(),
            'unread_count' => (int) ($pivot->unread_count ?? 0),
            'updated_at' => optional($conversation->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shapeMessage($message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'body' => $message->body,
            'type' => $message->type,
            'created_at' => optional($message->created_at)?->toIso8601String(),
            'user' => [
                'id' => $message->user?->id,
                'name' => $message->user?->name,
                'avatar' => $message->user?->profile_photo_url ?? null,
            ],
        ];
    }
}
