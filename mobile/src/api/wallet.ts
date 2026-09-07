import { apiRequest } from './client';
import type {
  CustomerWalletTransactionsResponse,
  PayBillResponse,
  PayWalletResponse,
  WalletSummary,
  WalletTransactionsResponse,
} from './types';

export function getWallet(token: string, accountNumber?: string): Promise<WalletSummary> {
  const query = accountNumber
    ? `?account_number=${encodeURIComponent(accountNumber)}`
    : '';
  return apiRequest<WalletSummary>(`/wallet${query}`, { token });
}

export function getWalletTransactions(
  token: string,
  accountNumber?: string,
): Promise<WalletTransactionsResponse> {
  const query = accountNumber
    ? `?account_number=${encodeURIComponent(accountNumber)}`
    : '';
  return apiRequest<WalletTransactionsResponse>(`/wallet/transactions${query}`, { token });
}

export function payWithAst(
  token: string,
  payload: { account_number: string; amount: number; idempotencyKey: string },
): Promise<PayWalletResponse> {
  return apiRequest<PayWalletResponse>('/wallet/pay', {
    token,
    method: 'POST',
    headers: { 'Idempotency-Key': payload.idempotencyKey },
    body: JSON.stringify({
      account_number: payload.account_number,
      amount: payload.amount,
      idempotency_key: payload.idempotencyKey,
    }),
  });
}

export function getCustomerWalletBalance(token: string): Promise<WalletSummary> {
  return apiRequest<WalletSummary>('/customer/wallet/balance', { token });
}

export function getCustomerWalletTransactions(
  token: string,
  perPage = 20,
): Promise<CustomerWalletTransactionsResponse> {
  return apiRequest<CustomerWalletTransactionsResponse>(`/customer/wallet/transactions?per_page=${perPage}`, {
    token,
  });
}

export function payBillWithAst(
  token: string,
  payload: { billingId: number; amount: number; idempotencyKey: string },
): Promise<PayBillResponse> {
  const amount = Number(payload.amount).toFixed(2);
  return apiRequest<PayBillResponse>('/customer/wallet/pay-bill', {
    token,
    method: 'POST',
    headers: { 'Idempotency-Key': payload.idempotencyKey },
    body: JSON.stringify({
      billing_id: payload.billingId,
      amount,
      idempotency_key: payload.idempotencyKey,
    }),
  });
}
