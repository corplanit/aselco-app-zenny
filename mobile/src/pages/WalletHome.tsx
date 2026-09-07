import {
  IonButton,
  IonContent,
  IonIcon,
  IonList,
  IonPage,
  IonRefresher,
  IonRefresherContent,
  IonSpinner,
  useIonRouter,
  useIonViewWillEnter,
} from '@ionic/react';
import { arrowForwardOutline, checkmarkCircleOutline, refreshOutline, walletOutline } from 'ionicons/icons';
import { useCallback, useState } from 'react';
import { getCustomerWalletBalance, getCustomerWalletTransactions } from '../api/wallet';
import type { WalletSummary, WalletTransaction } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import ListRow from '../components/ListRow';
import SectionHeader from '../components/SectionHeader';

function formatTokens(amount: number): string {
  return `${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })} AST`;
}

function formatTransactionLabel(type: string): string {
  if (type === 'load') return 'Wallet load';
  if (type === 'pay') return 'Bill payment';
  if (type === 'reversal') return 'Reversal';
  return 'Wallet activity';
}

const WalletHome: React.FC = () => {
  const { token } = useAuth();
  const router = useIonRouter();
  const [wallet, setWallet] = useState<WalletSummary | null>(null);
  const [transactions, setTransactions] = useState<WalletTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    setRefreshing(true);
    try {
      const [balance, txPage] = await Promise.all([
        getCustomerWalletBalance(token),
        getCustomerWalletTransactions(token, 10),
      ]);
      setWallet(balance);
      setTransactions(txPage.data);
    } catch {
      setWallet(null);
      setTransactions([]);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [token]);

  useIonViewWillEnter(() => {
    void load();
  }, [load]);

  return (
    <IonPage>
      <AppHeader title="AST Wallet" icon={walletOutline} />
      <IonContent>
        <IonRefresher
          slot="fixed"
          onIonRefresh={async (event) => {
            await load();
            event.detail.complete();
          }}
        >
          <IonRefresherContent />
        </IonRefresher>

        <div className="page-pad">
          <section className="dash-card dash-card--ast" style={{ marginBottom: 14 }}>
            <div className="dash-card__top">
              <span className="dash-card__badge">
                <IonIcon icon={walletOutline} />
                Wallet
              </span>
              <IonButton
                fill="clear"
                size="small"
                className="ledger-icon-btn"
                onClick={() => void load()}
                aria-label="Refresh wallet"
              >
                {refreshing ? <IonSpinner name="crescent" /> : <IonIcon icon={refreshOutline} />}
              </IonButton>
            </div>
            <p className="dash-card__label">Current AST balance</p>
            <p className="dash-card__amount">
              {formatTokens(
                typeof wallet?.total_balance === 'number' ? wallet.total_balance : (wallet?.balance ?? 0),
              )}
            </p>
            <p className="dash-card__meta">
              {(typeof wallet?.total_balance === 'number' ? wallet.total_balance : wallet?.balance)
                ? wallet?.account_number
                  ? `Primary funded account ${wallet.account_number}. Pull down any time to pick up admin loads or payments.`
                  : 'Refreshed from your wallet. Pull down any time to pick up admin loads or payments.'
                : 'AST is loaded by an authorized admin only. It is not self-purchasable in the app.'}
            </p>
            {wallet?.accounts && wallet.accounts.length > 1 ? (
              <ul className="dash-account-totals" style={{ marginTop: 10 }}>
                {wallet.accounts.map((row) => (
                  <li key={row.account_number} className="dash-account-totals__row">
                    <span className="dash-account-totals__acct">{row.account_number}</span>
                    <span className="dash-account-totals__amt">{formatTokens(row.balance)}</span>
                  </li>
                ))}
              </ul>
            ) : null}
          </section>

          <SectionHeader title="Recent Transactions" />
          {loading ? (
            <div className="ledger-loading">
              <IonSpinner name="crescent" />
            </div>
          ) : transactions.length === 0 ? (
            <EmptyState
              title="No wallet activity yet"
              message="Once AST is loaded by an authorized admin, your wallet history will appear here."
            />
          ) : (
            <IonList className="soft-list">
              {transactions.map((item) => {
                const tone = item.type === 'load' ? 'in' : item.type === 'reversal' ? 'pending' : 'due';
                const sign = item.type === 'load' || item.type === 'reversal' ? '+' : '-';
                return (
                  <ListRow
                    key={item.id}
                    icon={item.type === 'load' ? checkmarkCircleOutline : walletOutline}
                    title={formatTransactionLabel(item.type)}
                    meta={[
                      item.created_at ? new Date(item.created_at).toLocaleString('en-PH') : 'Unknown date',
                      item.reference,
                      `Balance ${formatTokens(item.balance_after)}`,
                    ].join(' · ')}
                    value={`${sign}${formatTokens(item.amount)}`}
                    tone={tone}
                    onClick={() => router.push(`/wallet/transactions/${item.id}`)}
                  />
                );
              })}
            </IonList>
          )}

          <IonButton expand="block" fill="outline" className="soft-btn ion-margin-top" onClick={() => router.push('/tabs/pay')}>
            Pay bill with AST
            <IonIcon icon={arrowForwardOutline} slot="end" />
          </IonButton>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default WalletHome;
