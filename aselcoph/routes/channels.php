<?php

use App\Models\Chats\Conversation;
use App\Models\Chats\ConversationParticipant;
use App\Models\SuppConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('users.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{conversationId}', function ($user, $conversationId) {
    return ConversationParticipant::query()
        ->where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

// Temporary rollback channels for legacy Supp* clients
Broadcast::channel('supp.conversations.{conversationId}', function ($user, $conversationId) {
    $conv = SuppConversation::query()->find($conversationId);
    if (! $conv) {
        return false;
    }

    $isSupport = ($user->role === 'support' || $user->role === 'Administrator'
        || (method_exists($user, 'hasRole') && $user->hasRole('support')));

    if ((int) $conv->customer_id === (int) $user->id) {
        return true;
    }

    return $isSupport;
});

Broadcast::channel('supp.users.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
