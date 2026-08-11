import type { AccountLink, LinkedAccount, ServiceInfo } from '../api/types';

export function displayOrDash(value?: string | null): string {
  const trimmed = value?.trim();
  return trimmed ? trimmed : '—';
}

export function formatLinkStatus(status?: string | null): string {
  if (!status) {
    return 'Unknown';
  }
  const normalized = status.toLowerCase();
  if (normalized === 'pending') {
    return 'Pending';
  }
  if (normalized === 'validated') {
    return 'Validated';
  }
  if (normalized === 'linked') {
    return 'Linked';
  }
  return status;
}

export interface MemberAccountOption {
  accountNumber: string;
  ownerName: string | null;
  status: string | null;
}

/** Unique linked / submitted accounts — same source for Home and Ledger. */
export function listMemberAccounts(
  linkedAccounts: LinkedAccount[],
  links: AccountLink[],
): MemberAccountOption[] {
  const byNumber = new Map<string, MemberAccountOption>();

  const accounts = Array.isArray(linkedAccounts) ? linkedAccounts : [];
  const accountLinks = Array.isArray(links) ? links : [];

  for (const item of accounts) {
    const accountNumber = item.account_no?.trim();
    if (!accountNumber) {
      continue;
    }
    byNumber.set(accountNumber, {
      accountNumber,
      ownerName: item.customer,
      status: item.status,
    });
  }

  for (const link of accountLinks) {
    const accountNumber = link.account_number?.trim();
    if (!accountNumber) {
      continue;
    }
    const existing = byNumber.get(accountNumber);
    if (!existing) {
      byNumber.set(accountNumber, {
        accountNumber,
        ownerName: link.owner_name,
        status: link.status,
      });
      continue;
    }
    if (!existing.ownerName && link.owner_name) {
      existing.ownerName = link.owner_name;
    }
    if (!existing.status && link.status) {
      existing.status = link.status;
    }
  }

  return Array.from(byNumber.values());
}

export function resolveServiceInfo(
  linkedAccounts: LinkedAccount[],
  links: AccountLink[],
): ServiceInfo | null {

  const safeLinkedAccounts = Array.isArray(linkedAccounts) ? linkedAccounts : [];
  const safeLinks = Array.isArray(links) ? links : [];

  const linked = safeLinkedAccounts[0];
  if (linked) {
    return {
      account_number: linked.account_no,
      owner_name: linked.customer,
      status: linked.status || 'linked',
      meter_no: linked.meter_no ?? null,
      address: linked.address ?? null,
      rate_class: linked.rate_class ?? null,
      source: 'linked_account',
    };
  }

  const link = safeLinks.find((item) => item.status === 'validated') ?? safeLinks[0];
  if (!link) {
    return null;
  }

  return {
    account_number: link.account_number,
    owner_name: link.owner_name,
    status: link.status,
    meter_no: null,
    address: null,
    rate_class: null,
    source: 'account_link',
  };
}
