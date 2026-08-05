import { IonIcon } from '@ionic/react';
import { documentTextOutline, walletOutline } from 'ionicons/icons';
import { Billing, TokenWallet } from '../data/mockData';
import { peso } from '../utils/format';

interface BalanceCardProps {
  wallet: TokenWallet;
  billing: Billing;
}

function tokens(amount: number): string {
  return `${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })} AST`;
}

/**
 * Home dashboard hero cards:
 * 1) Highlighted remaining AST (primary)
 * 2) Total billing amount due (secondary)
 * Display only — no CTAs on the cards.
 */
const BalanceCard: React.FC<BalanceCardProps> = ({ wallet, billing }) => (
  <div className="dash-cards">
    <section className="dash-card dash-card--ast" aria-label="Remaining ASELCO Tokens">
      <div className="dash-card__top">
        <span className="dash-card__badge">
          <IonIcon icon={walletOutline} />
          Wallet
        </span>
      </div>
      <p className="dash-card__label">Remaining AST</p>
      <p className="dash-card__amount">{tokens(wallet.balance)}</p>
      <p className="dash-card__meta">
        ASELCO Token · 1 AST ≈ ₱1 · only payment method
      </p>
    </section>

    <section className="dash-card dash-card--bill" aria-label="Total billing amount">
      <div className="dash-card__top">
        <span className="dash-card__badge dash-card__badge--muted">
          <IonIcon icon={documentTextOutline} />
          Billing
        </span>
      </div>
      <p className="dash-card__label dash-card__label--dark">Total billing amount</p>
      <p className="dash-card__amount dash-card__amount--dark">{peso(billing.amountDue)}</p>
      <p className="dash-card__meta dash-card__meta--dark">
        Period {billing.billingPeriod} · Due <strong>{billing.dueDate}</strong>
      </p>
    </section>
  </div>
);

export default BalanceCard;
