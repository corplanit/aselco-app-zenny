import { apiRequest, getApiBaseUrl } from './client';
import type {
  CustomerTicketDetail,
  CustomerTicketListItem,
  ApiErrorBody,
  TicketAttachmentItem,
} from './types';
import { ApiError } from './types';

export function createCustomerTicket(
  token: string,
  payload: {
    category_id: number;
    subcategory?: string | null;
    description: string;
    priority?: 'low' | 'normal' | 'high' | 'urgent' | string;
  },
): Promise<CustomerTicketDetail> {
  return apiRequest<CustomerTicketDetail>('/customer/tickets', {
    token,
    method: 'POST',
    body: JSON.stringify({
      ...payload,
    }),
  });
}

export function listCustomerTickets(
  token: string,
  params?: { status?: string; perPage?: number },
): Promise<{
  data: CustomerTicketListItem[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}> {
  const query = new URLSearchParams();
  if (params?.status) query.set('status', params.status);
  query.set('per_page', String(params?.perPage ?? 50));
  return apiRequest<{
    data: CustomerTicketListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  }>(`/customer/tickets?${query.toString()}`, { token });
}

export function showCustomerTicket(token: string, id: number): Promise<CustomerTicketDetail> {
  return apiRequest<CustomerTicketDetail>(`/customer/tickets/${id}`, { token });
}

export function uploadTicketAttachment(
  token: string,
  ticketId: number,
  file: File,
): Promise<{ data: TicketAttachmentItem[] }> {
  const url = `${getApiBaseUrl()}/customer/tickets/${ticketId}/attachments`;

  const form = new FormData();
  form.append('attachment', file, file.name);

  return fetch(url, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
    },
    body: form,
  })
    .then(async (res) => {
      const data = (await res.json().catch(() => ({}))) as ApiErrorBody;
      if (!res.ok) {
        throw new ApiError(data.message ?? res.statusText, res.status, data);
      }
      return data as { data: TicketAttachmentItem[] };
    })
    .catch((err) => {
      // Preserve ApiError; otherwise rethrow.
      throw err;
    });
}

export function submitCustomerTicketFeedback(
  token: string,
  ticketId: number,
  payload: {
    method: 'call' | 'message';
    customer_confirmed: boolean;
    notes?: string | null;
  },
): Promise<CustomerTicketDetail> {
  return apiRequest<CustomerTicketDetail>(`/customer/tickets/${ticketId}/feedback`, {
    token,
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

