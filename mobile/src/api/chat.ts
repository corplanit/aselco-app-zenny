import { apiRequest, getApiBaseUrl } from './client';

export type SupportChatMessage = {
  id: number;
  conversation_id: number;
  body: string;
  type?: string | null;
  created_at?: string | null;
  user?: {
    id?: number | null;
    name?: string | null;
    avatar?: string | null;
  } | null;
};

export type SupportConversation = {
  id: number;
  kind: string;
  status: string;
  customer_id: number | null;
  title: string;
  last_message?: string | null;
  last_at?: string | null;
  unread_count: number;
  customer?: {
    id: number;
    name: string;
    email?: string | null;
    avatar?: string | null;
  } | null;
};

export function ensureSupportChat(token: string): Promise<{ data: SupportConversation }> {
  return apiRequest<{ data: SupportConversation }>('/customer/support-chat/ensure', {
    token,
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function getSupportChat(token: string): Promise<{ data: SupportConversation }> {
  return apiRequest<{ data: SupportConversation }>('/customer/support-chat', { token });
}

export function listSupportMessages(
  token: string,
  conversationId: number,
  beforeId?: number,
): Promise<{ data: SupportChatMessage[]; next_page: number | null }> {
  const query = beforeId ? `?before_id=${beforeId}` : '';
  return apiRequest<{ data: SupportChatMessage[]; next_page: number | null }>(
    `/customer/support-chat/${conversationId}/messages${query}`,
    { token },
  );
}

export function sendSupportMessage(
  token: string,
  conversationId: number,
  body: string,
): Promise<{ data: SupportChatMessage }> {
  return apiRequest<{ data: SupportChatMessage }>(`/customer/support-chat/${conversationId}/messages`, {
    token,
    method: 'POST',
    body: JSON.stringify({ body }),
  });
}

export function markSupportChatRead(token: string, conversationId: number): Promise<{ ok: boolean }> {
  return apiRequest<{ ok: boolean }>(`/customer/support-chat/${conversationId}/read`, {
    token,
    method: 'POST',
    body: JSON.stringify({}),
  });
}

export function getBroadcastAuthUrl(): string {
  const apiBase = getApiBaseUrl();
  // Strip /api/v1 → origin
  return apiBase.replace(/\/api\/v1\/?$/, '') + '/broadcasting/auth';
}
