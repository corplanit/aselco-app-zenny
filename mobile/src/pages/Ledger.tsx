import {
  IonButton,
  IonContent,
  IonList,
  IonPage,
  useIonToast,
} from '@ionic/react';
import { cashOutline, documentTextOutline } from 'ionicons/icons';
import { useMemo, useState } from 'react';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import FilterChips from '../components/FilterChips';
import ListRow from '../components/ListRow';
import { billing, ledger, ledgerSummary } from '../data/mockData';
import { peso } from '../utils/format';

const FILTERS = ['All', 'Bills', 'Payments'];

const Ledger: React.FC = () => {
  const [filter, setFilter] = useState('All');
  const [present] = useIonToast();

  const items = useMemo(() => {
    if (filter === 'Bills') return ledger.filter((l) => l.type === 'bill');
    if (filter === 'Payments') return ledger.filter((l) => l.type === 'payment');
    return ledger;
  }, [filter]);

  return (
    <IonPage>
      <AppHeader title="My Ledger" icon={documentTextOutline} />
      <IonContent>
        <div className="page-pad">
          <div className="summary-strip">
            <div className="summary-strip__item">
              <span className="summary-strip__label">Current due</span>
              <span className="summary-strip__value">{peso(ledgerSummary.currentDue)}</span>
            </div>
            <div className="summary-strip__item">
              <span className="summary-strip__label">Paid YTD</span>
              <span className="summary-strip__value">{peso(ledgerSummary.totalPaid)}</span>
            </div>
            <div className="summary-strip__item">
              <span className="summary-strip__label">kWh used</span>
              <span className="summary-strip__value">{billing.kwhUsed}</span>
            </div>
          </div>

          <FilterChips options={FILTERS} value={filter} onChange={setFilter} />

          {items.length === 0 ? (
            <EmptyState title="No transactions" message="Try another filter." />
          ) : (
            <IonList className="soft-list">
              {items.map((item) => (
                <ListRow
                  key={item.id}
                  icon={item.type === 'payment' ? cashOutline : documentTextOutline}
                  title={item.title}
                  meta={`${item.date} · ${item.ref}`}
                  value={peso(item.amount)}
                  tone={item.type === 'payment' ? 'in' : 'due'}
                  detail={false}
                />
              ))}
            </IonList>
          )}

          <IonButton
            expand="block"
            fill="outline"
            className="soft-btn ion-margin-top"
            onClick={() =>
              present({
                message: 'Statement download is a UI demo only.',
                duration: 2000,
                color: 'primary',
              })
            }
          >
            Download statement
          </IonButton>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Ledger;
