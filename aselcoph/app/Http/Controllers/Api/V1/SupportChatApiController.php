<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ConversationUpdated;
use App\Http\Controllers\Controller;
use App\Models\Chats\Conversation;
use App\Models\Chats\ConversationParticipant;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportChatApiController extends Controller
{
    public function __construct(private SupportChatService $supportChat)
    {
    }

    public function ensure(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($this->supportChat->isSupportAgent($user), 403, 'Support agents join existing customer rooms.');

        $conversation = $this->supportChat->ensureForCustomer($user);

        return response()->json([
            'data' => $this->supportChat->shapeConversation($conversation, $user),
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->ensure($request);
    }

    public function inbox(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->supportChat->isSupportAgent($user), 403);

        $items = Conversation::query()
            ->support()
            ->open()
            ->with(['customer:id,name,email,profile_photo_path', 'participants', 'lastMessage'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $conversation) => $this->supportChat->shapeConversation($conversation, $user))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function messages(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conv = Conversation::query()->findOrFail($id);
        $this->supportChat->assertCanAccess($user, $conv);

        $beforeId = $request->query('before_id');
        $query = $conv->messages()->with('user:id,name,profile_photo_path')->orderByDesc('id');
        if ($beforeId) {
            $query->where('id', '<', (int) $beforeId);
        }
        $items = $query->limit(30)->get()->sortBy('id')->values();

        return response()->json([
            'data' => $items->map(fn ($message) => $this->supportChat->shapeMessage($message))->values(),
            'next_page' => $items->count() === 30 ? $items->first()->id : null,
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conv = Conversation::query()->findOrFail($id);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->supportChat->sendMessage($user, $conv, $data['body']);

        return response()->json([
            'data' => $this->supportChat->shapeMessage($message),
        ]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $conv = Conversation::query()->findOrFail($id);
        $this->supportChat->assertCanAccess($user, $conv);

        $lastId = $conv->messages()->max('id');
        ConversationParticipant::query()
            ->where('conversation_id', $conv->id)
            ->where('user_id', $user->id)
            ->update([
                'last_read_message_id' => $lastId,
                'last_read_at' => now(),
                'unread_count' => 0,
            ]);

        broadcast(new ConversationUpdated($conv->fresh(['lastMessage', 'participants.user', 'customer']), $user->id, 0));

        return response()->json(['ok' => true]);
    }
}
