import { Preferences } from '@capacitor/preferences';

const KEY = 'aselco.wallet.pendingAttempt';

export interface PendingWalletAttempt {
  billingId: number;
  amount: number;
  idempotencyKey: string;
  createdAt: string;
}

export async function getPendingWalletAttempt(): Promise<PendingWalletAttempt | null> {
  const result = await Preferences.get({ key: KEY });
  if (!result.value) {
    return null;
  }

  try {
    return JSON.parse(result.value) as PendingWalletAttempt;
  } catch {
    return null;
  }
}

export async function setPendingWalletAttempt(attempt: PendingWalletAttempt): Promise<void> {
  await Preferences.set({
    key: KEY,
    value: JSON.stringify(attempt),
  });
}

export async function clearPendingWalletAttempt(): Promise<void> {
  await Preferences.remove({ key: KEY });
}
