import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonPage,
  IonRefresher,
  IonRefresherContent,
  IonSearchbar,
  IonSpinner,
  useIonRouter,
  useIonViewWillEnter,
} from '@ionic/react';
import { ticketOutline } from 'ionicons/icons';
import { useCallback, useMemo, useState } from 'react';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import FilterChips from '../components/FilterChips';
import { useAuth } from '../auth/AuthContext';
import type { CustomerTicketListItem, TicketStatus } from '../api/types';
import { listCustomerTickets } from '../api/tickets';
import { displayOrDash } from '../utils/serviceAccount';

const FILTERS = [
  'All',
  'Submitted',
  'Endorsed',
  'In Progress',
  'Awaiting Your Feedback',
  'Resolved',
  'Closed',
  'Reopened',
] as const;

type FilterLabel = (typeof FILTERS)[number];

function statusGroupFilter(status: TicketStatus | undefined, filter: FilterLabel): boolean {
  if (filter === 'All') return true;
  if (!status) return false;

  switch (filter) {
    case 'Submitted':
      return status === 'new';
    case 'Endorsed':
      return status === 'endorsed';
    case 'In Progress':
      return status === 'assigned' || status === 'in_progress' || status === 'escalated';
    case 'Awaiting Your Feedback':
      return status === 'awaiting_feedback';
    case 'Resolved':
      return status === 'resolved';
    case 'Closed':
      return status === 'closed';
    case 'Reopened':
      return status === 'reopened';
    default:
      return true;
  }
}

function statusBadgeClass(status: TicketStatus | undefined): { label: string; className: string } {
  switch (status) {
    case 'new':
      return { label: 'Submitted', className: 'status-pill status-pill--pending' };
    case 'endorsed':
      return { label: 'Endorsed', className: 'status-pill status-pill--pending' };
    case 'assigned':
    case 'in_progress':
    case 'escalated':
      return { label: 'In Progress', className: 'status-pill status-pill--in-progress' };
    case 'awaiting_feedback':
      return { label: 'Awaiting Your Feedback', className: 'status-pill status-pill--pending' };
    case 'resolved':
      return { label: 'Resolved', className: 'status-pill status-pill--resolved' };
    case 'closed':
      return { label: 'Closed', className: 'status-pill status-pill--resolved' };
    case 'reopened':
      return { label: 'Reopened', className: 'status-pill status-pill--pending' };
    default:
      return { label: status ?? 'Unknown', className: 'status-pill status-pill--pending' };
  }
}

function parseDateOrNull(value: string): Date | null {
  if (!value) return null;
  // value is YYYY-MM-DD from <input type="date" />
  const dt = new Date(`${value}T00:00:00`);
  return Number.isNaN(dt.getTime()) ? null : dt;
}

const Tickets: React.FC = () => {
  const router = useIonRouter();
  const { token } = useAuth();

  const [filter, setFilter] = useState<FilterLabel>('All');
  const [query, setQuery] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');

  const [items, setItems] = useState<CustomerTicketListItem[]>([]);
  const [loading, setLoading] = useState(false);

  const loadTickets = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const page = await listCustomerTickets(token, { perPage: 100 });
      setItems(page.data as CustomerTicketListItem[]);
    } catch {
      setItems([]);
    } finally {
      setLoading(false);
    }
  }, [token]);

  useIonViewWillEnter(() => {
    void loadTickets();
  }, [loadTickets]);

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    const from = parseDateOrNull(fromDate);
    const to = parseDateOrNull(toDate);
    const toExclusive = to ? new Date(to.getTime() + 24 * 60 * 60 * 1000) : null;

    return items.filter((t) => {
      const status = t.status;
      if (!statusGroupFilter(status as TicketStatus | undefined, filter)) return false;

      if (q) {
        const hay = `${t.ticket_no ?? ''} ${t.description ?? ''}`.toLowerCase();
        if (!hay.includes(q)) return false;
      }

      const createdAt = t.created_at ? new Date(t.created_at) : null;
      if (createdAt && Number.isNaN(createdAt.getTime())) {
        return false;
      }

      if (from && createdAt && createdAt < from) return false;
      if (toExclusive && createdAt && createdAt >= toExclusive) return false;

      return true;
    });
  }, [filter, fromDate, items, query, toDate]);

  return (
    <IonPage>
      <AppHeader title="My Tickets" icon={ticketOutline} />
      <IonContent>
        <IonRefresher
          slot="fixed"
          onIonRefresh={async (event) => {
            await loadTickets();
            event.detail.complete();
          }}
        >
          <IonRefresherContent />
        </IonRefresher>

        <div className="page-pad">
          <FilterChips options={[...FILTERS]} value={filter} onChange={(v) => setFilter(v as FilterLabel)} />

          <IonSearchbar
            value={query}
            placeholder="Search by ticket ID or description"
            onIonInput={(e) => setQuery(e.detail.value ?? '')}
          />

          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginTop: 10, marginBottom: 12 }}>
            <label style={{ fontSize: 12, color: 'var(--aselco-ink-500)', fontWeight: 700 }}>
              From
              <input
                style={{
                  width: '100%',
                  marginTop: 6,
                  padding: '10px 12px',
                  borderRadius: 10,
                  border: '1px solid var(--aselco-line)',
                  background: 'var(--aselco-surface)',
                  color: 'var(--aselco-ink-900)',
                }}
                type="date"
                value={fromDate}
                onChange={(e) => setFromDate(e.target.value)}
              />
            </label>
            <label style={{ fontSize: 12, color: 'var(--aselco-ink-500)', fontWeight: 700 }}>
              To
              <input
                style={{
                  width: '100%',
                  marginTop: 6,
                  padding: '10px 12px',
                  borderRadius: 10,
                  border: '1px solid var(--aselco-line)',
                  background: 'var(--aselco-surface)',
                  color: 'var(--aselco-ink-900)',
                }}
                type="date"
                value={toDate}
                onChange={(e) => setToDate(e.target.value)}
              />
            </label>
          </div>

          {loading ? (
            <div className="ledger-loading">
              <IonSpinner name="crescent" />
            </div>
          ) : filtered.length === 0 ? (
            <EmptyState
              title={items.length === 0 ? 'No tickets yet' : 'No matching tickets'}
              message={items.length === 0 ? 'Report a concern to create your first ticket.' : 'Try changing the filters.'}
            />
          ) : (
            filtered.map((t) => {
              const badge = statusBadgeClass(t.status as TicketStatus | undefined);
              const title = `${t.ticket_no ?? 'TKT'}${t.category?.name ? ` · ${t.category.name}` : ''}`;
              const when = t.created_at ? new Date(t.created_at).toLocaleDateString('en-PH') : '—';

              return (
                <IonCard key={t.id} button onClick={() => router.push(`/tickets/${t.id}`)}>
                  <IonCardHeader>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, alignItems: 'flex-start' }}>
                      <IonCardSubtitle>{title}</IonCardSubtitle>
                      <span className={badge.className}>{badge.label}</span>
                    </div>
                    <IonCardTitle style={{ fontSize: 16 }}>{displayOrDash(t.description ?? '')}</IonCardTitle>
                  </IonCardHeader>
                  <IonCardContent style={{ paddingTop: 0 }}>
                    <p style={{ margin: 0, color: 'var(--aselco-ink-500)', fontSize: 13 }}>Submitted {when}</p>
                  </IonCardContent>
                </IonCard>
              );
            })
          )}

          <IonButton expand="block" className="soft-btn ion-margin-top" onClick={() => router.push('/complaints')}>
            Report a Concern
          </IonButton>
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Tickets;

