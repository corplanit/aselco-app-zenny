import {
  IonButton,
  IonContent,
  IonIcon,
  IonInput,
  IonItem,
  IonLabel,
  IonPage,
  useIonRouter,
  useIonToast,
  useIonViewWillEnter,
} from '@ionic/react';
import { checkmarkCircle, lockClosedOutline, receiptOutline, walletOutline } from 'ionicons/icons';
import { type CSSProperties, useCallback, useMemo, useState } from 'react';
import { getDashboardSummary } from '../api/dashboard';
import type { ApiErrorBody, DashboardSummary, PayBillResponse, WalletSummary } from '../api/types';
import { clearPendingWalletAttempt, getPendingWalletAttempt, setPendingWalletAttempt } from '../api/walletAttemptStorage';
import { getCustomerWalletBalance, payBillWithAst } from '../api/wallet';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import SectionHeader from '../components/SectionHeader';
import { lightHaptic } from '../utils/haptics';
import { peso } from '../utils/format';

type Step = 'form' | 'confirm' | 'auth' | 'uncertain' | 'receipt';

interface BillState {
  billingId: number | null;
  amountDue: number;
  dueDate: string;
  statementNo: string;
  accountNumber: string;
}

function tokens(amount: number): string {
  return `${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })} AST`;
}

function newIdempotencyKey(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `pay-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function resolveFriendlyError(error: ApiErrorBody): string {
  switch (error.code) {
    case 'AST_INSUFFICIENT_FUNDS':
      return 'Your AST balance is not enough for this payment.';
    case 'AST_BILL_ALREADY_PAID':
      return 'This bill has already been fully paid.';
    case 'AST_BILL_NOT_FOUND':
      return 'We could not find that bill in your account.';
    case 'AST_AMOUNT_MISMATCH':
      return 'The amount is higher than the bill balance due.';
    case 'AST_IDEMPOTENCY_REQUIRED':
      return 'Your payment key expired. Please try again.';
    default:
      return error.message ?? 'Could not complete the AST payment.';
  }
}

/** Prefer the bill account's AST; fall back to total / primary wallet balance. */
function balanceForBill(wallet: WalletSummary, accountNumber: string): number {
  const match = wallet.accounts?.find((row) => row.account_number === accountNumber);
  if (match) {
    return match.balance;
  }
  if (typeof wallet.total_balance === 'number') {
    return wallet.total_balance;
  }
  return wallet.balance;
}

const emptyBill: BillState = {
  billingId: null,
  amountDue: 0,
  dueDate: '—',
  statementNo: 'Current bill',
  accountNumber: '—',
};

const Pay: React.FC = () => {
  const router = useIonRouter();
  const [present] = useIonToast();
  const { token } = useAuth();
  const [step, setStep] = useState<Step>('form');
  const [walletBalance, setWalletBalance] = useState(0);
  const [totalWalletBalance, setTotalWalletBalance] = useState(0);
  const [bill, setBill] = useState<BillState>(emptyBill);
  const [amount, setAmount] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [securityPin, setSecurityPin] = useState('');
  const [pendingKey, setPendingKey] = useState<string | null>(null);
  const [pendingRetry, setPendingRetry] = useState(false);
  const [errorText, setErrorText] = useState('');
  const [result, setResult] = useState<PayBillResponse | null>(null);

  const canAdvance = amount > 0 && walletBalance >= amount && !!bill.billingId && !submitting;
  const shortfall = Math.max(0, amount - walletBalance);
  const otherAccountAst = Math.max(0, totalWalletBalance - walletBalance);

  const loadLive = useCallback(async () => {
    if (!token) {
      return;
    }
    try {
      const [summary, wallet, pending] = await Promise.all([
        getDashboardSummary(token),
        getCustomerWalletBalance(token),
        getPendingWalletAttempt(),
      ]);

      const nextBill = resolveBill(summary);
      const billBalance = balanceForBill(wallet, nextBill.accountNumber);
      setBill(nextBill);
      setWalletBalance(billBalance);
      setTotalWalletBalance(
        typeof wallet.total_balance === 'number' ? wallet.total_balance : wallet.balance,
      );
      setPendingKey(pending?.billingId === nextBill.billingId ? pending.idempotencyKey : null);
      setPendingRetry(!!pending && pending.billingId === nextBill.billingId);
      if (step === 'form') {
        setAmount(nextBill.amountDue);
      }
    } catch (error) {
      setBill(emptyBill);
      setWalletBalance(0);
      setTotalWalletBalance(0);
      present({
        message: isApiError(error)
          ? resolveFriendlyError(error.data)
          : 'Could not load bill or AST balance. Pull to refresh and try again.',
        duration: 2800,
        color: 'warning',
      });
    }
  }, [present, step, token]);

  useIonViewWillEnter(() => {
    void loadLive();
  }, [loadLive]);

  const submitPayment = async (retryExisting = false) => {
    if (!token || !bill.billingId) {
      return;
    }

    const idempotencyKey = retryExisting && pendingKey ? pendingKey : pendingKey ?? newIdempotencyKey();
    setPendingKey(idempotencyKey);
    setSubmitting(true);
    setErrorText('');

    await setPendingWalletAttempt({
      billingId: bill.billingId,
      amount,
      idempotencyKey,
      createdAt: new Date().toISOString(),
    });

    try {
      const payment = await payBillWithAst(token, {
        billingId: bill.billingId,
        amount,
        idempotencyKey,
      });
      await clearPendingWalletAttempt();
      setResult(payment);
      setWalletBalance(payment.balance_after);
      setPendingRetry(false);
      setStep('receipt');
      setSecurityPin('');
      await lightHaptic();
      await loadLive();
    } catch (error) {
      if (isApiError(error)) {
        await clearPendingWalletAttempt();
        setPendingKey(null);
        setPendingRetry(false);
        const message = resolveFriendlyError(error.data);
        if (error.data.code === 'AST_INSUFFICIENT_FUNDS') {
          setErrorText(message);
          setWalletBalance(error.data.balance ?? walletBalance);
          setStep('form');
        } else {
          present({ message, duration: 2600, color: 'warning' });
          setStep('form');
        }
      } else {
        setPendingRetry(true);
        setStep('uncertain');
      }
    } finally {
      setSubmitting(false);
    }
  };

  const paymentDetails = useMemo(() => {
    if (!result) return null;
    return [
      ['Reference no.', result.reference],
      ['Receipt no.', result.receipt_no],
      ['Paid with', 'ASELCO Tokens (AST)'],
      ['Account', bill.accountNumber],
      ['Wallet left', tokens(walletBalance)],
      ['CIS status', result.cis_status],
      ['Bill remaining', peso(Math.max(0, bill.amountDue - result.amount))],
    ] as const;
  }, [bill.accountNumber, bill.amountDue, result, walletBalance]);

  const reset = async () => {
    await clearPendingWalletAttempt();
    setPendingKey(null);
    setPendingRetry(false);
    setSecurityPin('');
    setResult(null);
    setStep('form');
    await loadLive();
  };

  return (
    <IonPage>
      <AppHeader title="Pay with AST" icon={walletOutline} />
      <IonContent>
        <div className="page-pad">
          <div className="token-balance">
            <p className="token-balance__label">ASELCO Token on this bill account</p>
            <p className="token-balance__amount">{tokens(walletBalance)}</p>
            <p className="token-balance__hint">
              {otherAccountAst > 0
                ? `AST for account ${bill.accountNumber}. You also have ${tokens(otherAccountAst)} on other linked accounts (not usable for this bill).`
                : 'AST is loaded per electric account. Admin loads must match the bill account number.'}
            </p>
          </div>

          <section className="balance-card" style={{ marginBottom: 10 }}>
            <p className="balance-card__label">Bill amount due</p>
            <p className="balance-card__amount">{peso(bill.amountDue)}</p>
            <p className="balance-card__due" style={{ marginBottom: 0 }}>
              {bill.statementNo} · Account <strong>{bill.accountNumber}</strong> · Due <strong>{bill.dueDate}</strong>
            </p>
          </section>

          {step === 'form' ? (
            <>
              {bill.billingId ? (
                <>
                  <SectionHeader title="Pay with ASELCO Tokens" />
                  <div className="soft-card" style={{ marginBottom: 8 }}>
                    <div className="soft-card-btn is-selected" style={{ cursor: 'default', marginBottom: 12 }}>
                      <span className="soft-card-btn__icon">
                        <IonIcon icon={walletOutline} />
                      </span>
                      <span className="soft-card-btn__body">
                        <span className="soft-card-btn__title">ASELCO Token (AST)</span>
                        <span className="soft-card-btn__meta">Duplicate-safe with a saved payment key on retries</span>
                      </span>
                      <span className="soft-card-btn__check" aria-hidden />
                    </div>

                    <IonItem lines="none" style={{ '--background': 'transparent' } as CSSProperties}>
                      <IonLabel position="stacked">Tokens to spend (AST)</IonLabel>
                      <IonInput
                        type="number"
                        value={amount}
                        onIonInput={(e) => {
                          setAmount(Math.max(0, Number(e.detail.value) || 0));
                          setErrorText('');
                        }}
                      />
                    </IonItem>
                    <IonButton expand="block" fill="clear" className="soft-btn" onClick={() => setAmount(bill.amountDue)}>
                      Use full balance due
                    </IonButton>
                    <p style={{ fontSize: 12, color: 'var(--aselco-ink-500)', margin: '0 4px' }}>
                      {walletBalance >= amount && amount > 0
                        ? `After pay, wallet ≈ ${tokens(walletBalance - amount)}`
                        : `Insufficient AST on this account. Shortfall: ${tokens(shortfall)}.`}
                    </p>
                  </div>

                  {errorText ? (
                    <div className="soft-card" style={{ marginBottom: 10, border: '1px solid rgba(219, 86, 72, 0.22)' }}>
                      <p style={{ margin: '0 0 6px', color: 'var(--ion-color-danger)' }}>{errorText}</p>
                      <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                        Ask the office to load AST on account {bill.accountNumber}, or use another billing payment path.
                      </p>
                    </div>
                  ) : null}

                  {pendingRetry ? (
                    <div className="soft-card" style={{ marginBottom: 10 }}>
                      <p style={{ margin: '0 0 6px', color: 'var(--aselco-ink-900)', fontWeight: 600 }}>
                        Previous payment status is still being verified.
                      </p>
                      <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                        Retry uses the same idempotency key so the backend will not double-charge your wallet.
                      </p>
                    </div>
                  ) : null}

                  <IonButton
                    expand="block"
                    className="soft-btn ion-margin-top"
                    disabled={!canAdvance}
                    onClick={() => setStep('confirm')}
                  >
                    Continue to confirmation
                  </IonButton>
                </>
              ) : (
                <EmptyState
                  title="No payable bill found"
                  message="When a current bill is available, you can pay part or all of it with AST here."
                />
              )}
            </>
          ) : null}

          {step === 'confirm' ? (
            <>
              <SectionHeader title="Confirm payment" />
              <div className="soft-card">
                <div className="kv">
                  <span className="kv__k">Amount to deduct</span>
                  <span className="kv__v">{tokens(amount)}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Current AST balance</span>
                  <span className="kv__v">{tokens(walletBalance)}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Bill balance after pay</span>
                  <span className="kv__v">{peso(Math.max(0, bill.amountDue - amount))}</span>
                </div>
              </div>
              <IonButton expand="block" className="soft-btn ion-margin-top" onClick={() => setStep('auth')}>
                Proceed to security check
              </IonButton>
              <IonButton expand="block" fill="outline" className="soft-btn" onClick={() => setStep('form')}>
                Back
              </IonButton>
            </>
          ) : null}

          {step === 'auth' ? (
            <>
              <SectionHeader title="PIN / biometric confirmation" />
              <div className="soft-card" style={{ marginBottom: 8 }}>
                <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginBottom: 10 }}>
                  <IonIcon icon={lockClosedOutline} style={{ fontSize: 20, color: 'var(--ion-color-primary)' }} />
                  <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                    Enter a 4-digit confirmation PIN to complete this AST payment. This screen is ready for device
                    biometric confirmation once a native plugin is added.
                  </p>
                </div>
                <IonItem lines="none" style={{ '--background': 'transparent' } as CSSProperties}>
                  <IonLabel position="stacked">Confirmation PIN</IonLabel>
                  <IonInput
                    type="password"
                    inputmode="numeric"
                    maxlength={4}
                    value={securityPin}
                    onIonInput={(e) => setSecurityPin(String(e.detail.value ?? '').replace(/\D/g, '').slice(0, 4))}
                  />
                </IonItem>
              </div>
              <IonButton
                expand="block"
                className="soft-btn ion-margin-top"
                disabled={securityPin.length !== 4 || submitting}
                onClick={() => void submitPayment(false)}
              >
                {submitting ? 'Processing payment…' : `Pay ${tokens(amount)}`}
              </IonButton>
              <IonButton expand="block" fill="outline" className="soft-btn" onClick={() => setStep('confirm')}>
                Back
              </IonButton>
            </>
          ) : null}

          {step === 'uncertain' ? (
            <>
              <SectionHeader title="Retry safely" />
              <div className="soft-card" style={{ marginBottom: 10 }}>
                <p style={{ margin: '0 0 6px', color: 'var(--aselco-ink-900)', fontWeight: 600 }}>
                  The network dropped before we could confirm the result.
                </p>
                <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                  Retry uses the same saved idempotency key, so the backend either returns the original payment or
                  records it once.
                </p>
              </div>
              <IonButton expand="block" className="soft-btn ion-margin-top" onClick={() => void submitPayment(true)}>
                Retry same payment safely
              </IonButton>
              <IonButton expand="block" fill="outline" className="soft-btn" onClick={() => setStep('form')}>
                Review payment
              </IonButton>
            </>
          ) : null}

          {step === 'receipt' && result ? (
            <>
              <section className="soft-card" style={{ textAlign: 'center', marginBottom: 12 }}>
                <IonIcon
                  icon={checkmarkCircle}
                  style={{ fontSize: 42, color: 'var(--ion-color-success)', marginTop: 4 }}
                />
                <h2 className="font-display" style={{ margin: '8px 0 4px', fontSize: 20 }}>
                  AST payment recorded
                </h2>
                <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                  Your wallet was debited and a receipt is ready. If the server had already recorded this payment,
                  the same receipt is shown again instead of charging twice.
                </p>
                <p className="balance-card__amount" style={{ color: 'var(--aselco-ink-900)', marginTop: 14 }}>
                  {tokens(result.amount)}
                </p>
              </section>

              <SectionHeader title="Details" />
              <div className="soft-card">
                {paymentDetails.map(([label, value]) => (
                  <div className="kv" key={label}>
                    <span className="kv__k">{label}</span>
                    <span className="kv__v">{value}</span>
                  </div>
                ))}
              </div>

              <IonButton
                expand="block"
                className="soft-btn ion-margin-top"
                onClick={() => router.push(`/wallet/transactions/${result.id}`)}
              >
                <span>Open transaction receipt</span>
                <IonIcon icon={receiptOutline} slot="end" />
              </IonButton>
              <IonButton expand="block" fill="outline" className="soft-btn" onClick={reset}>
                Make another payment
              </IonButton>
            </>
          ) : null}
        </div>
      </IonContent>
    </IonPage>
  );
};

function resolveBill(summary: DashboardSummary): BillState {
  const outstanding =
    summary.billing.outstanding_bills.find((item) => item.id === summary.billing.current_bill_id) ??
    summary.billing.outstanding_bills[0];

  return {
    billingId: outstanding?.id ?? summary.billing.current_bill_id,
    amountDue: outstanding?.balance_due ?? summary.billing.amount_due ?? 0,
    dueDate: summary.billing.due_date ?? '—',
    statementNo: summary.billing.billing_period ?? 'Current bill',
    accountNumber:
      outstanding?.account_number ??
      summary.wallet?.account_number ??
      summary.service?.account_number ??
      summary.linked_accounts[0]?.account_no ??
      '—',
  };
}

function isApiError(error: unknown): error is { data: ApiErrorBody } {
  return typeof error === 'object' && error !== null && 'data' in error;
}

export default Pay;
