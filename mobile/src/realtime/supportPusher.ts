import Pusher, { type Channel } from 'pusher-js';
import { getBroadcastAuthUrl } from '../api/chat';

let pusher: Pusher | null = null;
let pusherToken: string | null = null;

export function getSupportPusher(token: string): Pusher | null {
  const key = import.meta.env.VITE_PUSHER_APP_KEY as string | undefined;
  const cluster = (import.meta.env.VITE_PUSHER_APP_CLUSTER as string | undefined) ?? 'ap1';
  if (!key) {
    return null;
  }

  if (pusher && pusherToken === token) {
    return pusher;
  }

  if (pusher) {
    pusher.disconnect();
    pusher = null;
  }

  pusherToken = token;
  pusher = new Pusher(key, {
    cluster,
    forceTLS: true,
    authEndpoint: getBroadcastAuthUrl(),
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      },
    },
  });

  return pusher;
}

function bindMessage(channel: Channel, onMessage: (payload: unknown) => void): void {
  channel.bind('message.sent', onMessage);
}

export function subscribeConversation(
  token: string,
  conversationId: number,
  onMessage: (payload: unknown) => void,
): Channel | null {
  const client = getSupportPusher(token);
  if (!client) {
    return null;
  }
  const channel = client.subscribe(`private-conversations.${conversationId}`);
  bindMessage(channel, onMessage);
  return channel;
}

export function subscribeUserChannel(
  token: string,
  userId: number,
  onMessage: (payload: unknown) => void,
): Channel | null {
  const client = getSupportPusher(token);
  if (!client || !userId) {
    return null;
  }
  const channel = client.subscribe(`private-users.${userId}`);
  bindMessage(channel, onMessage);
  return channel;
}

export function leaveConversation(conversationId: number): void {
  if (!pusher) {
    return;
  }
  pusher.unsubscribe(`private-conversations.${conversationId}`);
}

export function leaveUserChannel(userId: number): void {
  if (!pusher) {
    return;
  }
  pusher.unsubscribe(`private-users.${userId}`);
}
