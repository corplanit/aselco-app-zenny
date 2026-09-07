import { apiRequest } from './client';
import type {
  AiBootstrap,
  AiConversationDetail,
  AiConversationListItem,
  AiCustomerReply,
  AiEscalateResponse,
} from './types';

export function fetchAiBootstrap(token: string): Promise<AiBootstrap> {
  return apiRequest<AiBootstrap>('/customer/ai/bootstrap', { token });
}

export function sendAiChat(
  token: string,
  message: string,
  conversationId?: number | null,
): Promise<AiCustomerReply> {
  return apiRequest<AiCustomerReply>('/customer/ai/chat', {
    token,
    method: 'POST',
    body: JSON.stringify({
      message,
      ...(conversationId ? { conversation_id: conversationId } : {}),
    }),
  });
}

export function sendAiInquiry(token: string, message: string): Promise<AiCustomerReply> {
  return apiRequest<AiCustomerReply>('/customer/ai/inquiry', {
    token,
    method: 'POST',
    body: JSON.stringify({ message }),
  });
}

export function listAiConversations(token: string): Promise<{
  data: AiConversationListItem[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}> {
  return apiRequest('/customer/ai/conversations?per_page=20', { token });
}

export function showAiConversation(token: string, id: number): Promise<AiConversationDetail> {
  return apiRequest<AiConversationDetail>(`/customer/ai/conversations/${id}`, { token });
}

export function deleteAiConversation(token: string, id: number): Promise<{ message: string; code: string }> {
  return apiRequest(`/customer/ai/conversations/${id}`, {
    token,
    method: 'DELETE',
  });
}

export function escalateAiConversation(
  token: string,
  payload: {
    conversation_id?: number | null;
    ticket_id?: number | null;
    category_id?: number | null;
    category_hint?: string | null;
    message?: string;
    description?: string;
  },
): Promise<AiEscalateResponse> {
  return apiRequest<AiEscalateResponse>('/customer/ai/escalate', {
    token,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
