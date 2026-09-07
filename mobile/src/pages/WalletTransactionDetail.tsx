import { IonButton, IonContent, IonPage, useIonToast, useIonViewWillEnter } from '@ionic/react';
import { receiptOutline } from 'ionicons/icons';
import { useCallback, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import { getCustomerWalletTransactions } from '../api/wallet';
import type { WalletTransaction } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';

function formatTokens(amount: number): string {
  return `${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })} AST`;
}

function buildReceiptText(txn: WalletTransaction): string {
  return [
    'ASELCO AST Receipt',
    `Reference: ${txn.reference}`,
    `Date: ${txn.created_at ? new Date(txn.created_at).toLocaleString('en-PH') : 'Unknown'}`,
    `Type: ${txn.type}`,
    `Amount: ${formatTokens(txn.amount)}`,
    `Running balance: ${formatTokens(txn.balance_after)}`,
    `Account: ${txn.account_number ?? '—'}`,
    `Bill ID: ${txn.billing_upload_id ?? '—'}`,
    `CIS status: ${txn.cis_status}`,
  ].join('\n');
}

const WalletTransactionDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { token } = useAuth();
  const [txn, setTxn] = useState<WalletTransaction | null>(null);
  const [present] = useIonToast();

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const page = await getCustomerWalletTransactions(token, 50);
      const found = page.data.find((item) => String(item.id) === id) ?? null;
      setTxn(found);
    } catch {
      setTxn(null);
    }
  }, [id, token]);

  useIonViewWillEnter(() => {
    void load();
  }, [load]);

  const receiptText = useMemo(() => (txn ? buildReceiptText(txn) : ''), [txn]);

  const shareReceipt = async () => {
    if (!txn) return;
    if (navigator.share) {
      await navigator.share({
        title: `AST Receipt ${txn.reference}`,
        text: receiptText,
      });
      return;
    }
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(receiptText).catch(() => undefined);
    }
    present({ message: 'Receipt copied to clipboard.', duration: 2000, color: 'success' });
  };

  const downloadReceipt = () => {
    if (!txn) return;
    const blob = new Blob([receiptText], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `ast-receipt-${txn.reference}.txt`;
    anchor.click();
    URL.revokeObjectURL(url);
  };

  return (
    <IonPage>
      <AppHeader title="Transaction Detail" icon={receiptOutline} />
      <IonContent>
        <div className="page-pad">
          {!txn ? (
            <EmptyState
              title="Transaction not found"
              message="Pull back to your wallet history and try opening the receipt again."
            />
          ) : (
            <>
              <section className="soft-card" style={{ marginBottom: 12 }}>
                <div className="kv">
                  <span className="kv__k">Reference no.</span>
                  <span className="kv__v">{txn.reference}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Type</span>
                  <span className="kv__v">{txn.type}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Amount</span>
                  <span className="kv__v">{formatTokens(txn.amount)}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Running balance</span>
                  <span className="kv__v">{formatTokens(txn.balance_after)}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Account</span>
                  <span className="kv__v">{txn.account_number ?? '—'}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Bill ID</span>
                  <span className="kv__v">{txn.billing_upload_id ?? '—'}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">CIS status</span>
                  <span className="kv__v">{txn.cis_status}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Date</span>
                  <span className="kv__v">{txn.created_at ? new Date(txn.created_at).toLocaleString('en-PH') : '—'}</span>
                </div>
              </section>

              {txn.type === 'pay' ? (
                <>
                  <IonButton expand="block" className="soft-btn" onClick={() => void shareReceipt()}>
                    <span>Share receipt</span>
                  </IonButton>
                  <IonButton expand="block" fill="outline" className="soft-btn" onClick={downloadReceipt}>
                    <span>Download receipt</span>
                  </IonButton>
                </>
              ) : null}
            </>
          )}
        </div>
      </IonContent>
    </IonPage>
  );
};

export default WalletTransactionDetail;
