import {
  IonButton,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonModal,
  IonPage,
  IonRefresher,
  IonRefresherContent,
  IonSpinner,
  IonTextarea,
  useIonToast,
  useIonViewWillEnter,
} from '@ionic/react';
import { closeOutline, documentTextOutline, thumbsDownOutline, thumbsUpOutline } from 'ionicons/icons';
import { useCallback, useMemo, useState } from 'react';
import { useParams } from 'react-router-dom';
import type { CustomerTicketDetail, TicketActionItem, TicketStatus, TicketStatusHistoryItem } from '../api/types';
import { showCustomerTicket, submitCustomerTicketFeedback } from '../api/tickets';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';
import EmptyState from '../components/EmptyState';
import SectionHeader from '../components/SectionHeader';
import { displayOrDash } from '../utils/serviceAccount';

function statusToCustomerLabel(status: TicketStatus): string {
  switch (status) {
    case 'new':
      return 'Submitted';
    case 'endorsed':
      return 'Endorsed';
    case 'assigned':
    case 'in_progress':
    case 'escalated':
      return 'In Progress';
    case 'awaiting_feedback':
      return 'Awaiting Your Feedback';
    case 'resolved':
      return 'Resolved';
    case 'closed':
      return 'Closed';
    case 'reopened':
      return 'Reopened';
    default:
      return status;
  }
}

function statusPillClass(status: TicketStatus): string {
  switch (status) {
    case 'resolved':
    case 'closed':
      return 'status-pill status-pill--resolved';
    case 'new':
    case 'endorsed':
    case 'awaiting_feedback':
    case 'reopened':
      return 'status-pill status-pill--pending';
    case 'assigned':
    case 'in_progress':
    case 'escalated':
      return 'status-pill status-pill--in-progress';
    default:
      return 'status-pill status-pill--pending';
  }
}

type TimelineItem =
  | { kind: 'status'; id: number; createdAt: string | null; label: string; note: string | null }
  | { kind: 'action'; id: number; createdAt: string | null; label: string; note: string | null };

function toSafeDate(value: string | null | undefined): number {
  if (!value) return 0;
  const dt = new Date(value);
  return Number.isNaN(dt.getTime()) ? 0 : dt.getTime();
}

const TicketDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const ticketId = Number(id);
  const { token } = useAuth();
  const [present] = useIonToast();

  const [ticket, setTicket] = useState<CustomerTicketDetail | null>(null);
  const [loading, setLoading] = useState(true);

  const [attachmentPreview, setAttachmentPreview] = useState<{
    url: string;
    file_type: string;
    file_name: string;
  } | null>(null);

  const [feedbackMode, setFeedbackMode] = useState<'none' | 'no'>('none');
  const [feedbackNotes, setFeedbackNotes] = useState('');
  const [feedbackSubmitting, setFeedbackSubmitting] = useState(false);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const next = await showCustomerTicket(token, ticketId);
      setTicket(next);
    } catch {
      setTicket(null);
    } finally {
      setLoading(false);
    }
  }, [ticketId, token]);

  useIonViewWillEnter(() => {
    void load();
  }, [load]);

  const timeline = useMemo<TimelineItem[]>(() => {
    if (!ticket) return [];

    const statusItems: TimelineItem[] = (ticket.status_history ?? []).map(
      (h: TicketStatusHistoryItem) => ({
        kind: 'status',
        id: h.id,
        createdAt: h.created_at,
        label: `Status updated → ${h.to_status ? statusToCustomerLabel(h.to_status as TicketStatus) : '—'}`,
        note: h.remarks ?? null,
      }),
    );

    const actionItems: TimelineItem[] = (ticket.actions ?? []).map((a: TicketActionItem) => {
      const flags: string[] = [];
      if (a.requires_payment) flags.push('Billing/payment check');
      if (a.requires_tsd_intervention) flags.push('TSD intervention');

      return {
        kind: 'action',
        id: a.id,
        createdAt: a.created_at,
        label: 'Staff update logged',
        note: flags.length ? flags.join(' · ') : null,
      };
    });

    return [...statusItems, ...actionItems].sort((x, y) => toSafeDate(x.createdAt) - toSafeDate(y.createdAt));
  }, [ticket]);

  const canProvideFeedback = ticket?.status === 'awaiting_feedback';

  const submitFeedback = async (customerConfirmed: boolean) => {
    if (!token || !ticket) return;
    if (feedbackSubmitting) return;
    setFeedbackSubmitting(true);
    try {
      await submitCustomerTicketFeedback(token, ticket.id, {
        method: 'message',
        customer_confirmed: customerConfirmed,
        notes: customerConfirmed ? null : feedbackNotes.trim() || null,
      });
      present({ message: 'Feedback sent.', duration: 2200, color: 'success' });
      setFeedbackMode('none');
      setFeedbackNotes('');
      await load();
    } catch (e: unknown) {
      const message =
        e instanceof Error
          ? e.message
          : typeof e === 'object' && e !== null && 'message' in e
            ? String((e as { message?: unknown }).message ?? 'Could not submit feedback. Try again.')
            : 'Could not submit feedback. Try again.';
      present({ message, duration: 2600, color: 'warning' });
    } finally {
      setFeedbackSubmitting(false);
    }
  };

  const attachments = ticket?.attachments ?? [];

  return (
    <IonPage>
      <AppHeader title="Ticket Detail" icon={documentTextOutline} backHref="/tabs/tickets" />
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
          {loading ? (
            <div className="ledger-loading">
              <IonSpinner name="crescent" />
            </div>
          ) : !ticket ? (
            <EmptyState
              title="Ticket not found"
              message="This ticket may have been removed, or the ID is incorrect."
            />
          ) : (
            <>
              <section className="soft-card" style={{ marginBottom: 12 }}>
                <div className="kv">
                  <span className="kv__k">Ticket no.</span>
                  <span className="kv__v">{ticket.ticket_no}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Category</span>
                  <span className="kv__v">{ticket.category?.name ?? '—'}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Status</span>
                  <span className="kv__v">
                    <span className={statusPillClass(ticket.status)}>{statusToCustomerLabel(ticket.status as TicketStatus)}</span>
                  </span>
                </div>
                <div className="kv">
                  <span className="kv__k">Description</span>
                  <span className="kv__v" style={{ fontWeight: 600 }}>
                    {displayOrDash(ticket.description)}
                  </span>
                </div>
              </section>

              <SectionHeader title="Timeline" />
              {timeline.length === 0 ? (
                <EmptyState title="No timeline yet" message="As staff updates your request, it will appear here." />
              ) : (
                <IonList className="soft-list">
                  {timeline.map((item) => (
                    <IonItem key={`${item.kind}-${item.id}`} lines="full" detail={false}>
                      <IonLabel>
                        <h3 style={{ margin: 0, fontSize: 14 }}>{item.label}</h3>
                        <p style={{ margin: '6px 0 0', color: 'var(--aselco-ink-500)', fontSize: 12 }}>
                          {item.createdAt ? new Date(item.createdAt).toLocaleString('en-PH') : '—'}
                        </p>
                        {item.note ? (
                          <p style={{ margin: '6px 0 0', color: 'var(--aselco-ink-900)', fontSize: 12, fontWeight: 600 }}>
                            {item.note}
                          </p>
                        ) : null}
                      </IonLabel>
                    </IonItem>
                  ))}
                </IonList>
              )}

              <SectionHeader title="Evidence / Attachments" />
              {attachments.length === 0 ? (
                <EmptyState title="No attachments" message="If you attach evidence, it will show up here." />
              ) : (
                <IonList className="soft-list">
                  {attachments.map((a) => {
                    const isImage = a.file_type?.startsWith('image/');
                    const isVideo = a.file_type?.startsWith('video/');
                    return (
                      <IonItem key={a.id} lines="full">
                        <IonLabel>
                          <h3 style={{ margin: 0, fontSize: 14 }}>
                            {isImage ? 'Image' : isVideo ? 'Video' : a.file_type ?? 'File'}
                          </h3>
                          <p style={{ margin: '6px 0 0', color: 'var(--aselco-ink-500)', fontSize: 12 }}>
                            {a.uploaded_at ? new Date(a.uploaded_at).toLocaleString('en-PH') : '—'} · {(a.file_size / 1024).toFixed(1)} KB
                          </p>
                        </IonLabel>
                        <IonButton
                          size="small"
                          fill="outline"
                          className="soft-btn"
                          onClick={() => {
                            if (isImage || isVideo) {
                              setAttachmentPreview({
                                url: a.download_url,
                                file_type: a.file_type,
                                file_name: `${a.id}`,
                              });
                            } else {
                              window.open(a.download_url, '_blank');
                            }
                          }}
                        >
                          {isImage || isVideo ? 'View' : 'Open'}
                        </IonButton>
                      </IonItem>
                    );
                  })}
                </IonList>
              )}

              <SectionHeader title="Customer feedback" />
              {canProvideFeedback ? (
                <div className="soft-card" style={{ marginBottom: 14 }}>
                  <p style={{ margin: 0, fontWeight: 800, color: 'var(--aselco-ink-900)' }}>Was this resolved?</p>
                  <p style={{ margin: '6px 0 0', fontSize: 13, color: 'var(--aselco-ink-500)' }}>
                    Yes closes the ticket. No requests further action.
                  </p>

                  <div style={{ display: 'flex', gap: 10, marginTop: 12 }}>
                    <IonButton
                      expand="block"
                      className="soft-btn"
                      disabled={feedbackSubmitting}
                      onClick={() => void submitFeedback(true)}
                    >
                      <IonIcon icon={thumbsUpOutline} slot="start" /> Yes
                    </IonButton>
                    <IonButton
                      expand="block"
                      fill="outline"
                      className="soft-btn"
                      disabled={feedbackSubmitting}
                      onClick={() => setFeedbackMode('no')}
                    >
                      <IonIcon icon={thumbsDownOutline} slot="start" /> No
                    </IonButton>
                  </div>

                  {feedbackMode === 'no' ? (
                    <>
                      <IonTextarea
                        autoGrow
                        rows={3}
                        value={feedbackNotes}
                        placeholder="Optional comment (what still needs fixing?)"
                        onIonInput={(e) => setFeedbackNotes(String(e.detail.value ?? ''))}
                        style={{ marginTop: 10 }}
                      />
                      <IonButton
                        expand="block"
                        className="soft-btn ion-margin-top"
                        disabled={feedbackSubmitting}
                        onClick={() => void submitFeedback(false)}
                      >
                        {feedbackSubmitting ? 'Sending…' : 'Submit response'}
                      </IonButton>
                    </>
                  ) : null}
                </div>
              ) : (
                <EmptyState
                  title="No feedback needed"
                  message={ticket.status === 'closed' || ticket.status === 'resolved' ? 'Ticket is already finalized.' : 'Feedback prompt appears when staff asks for confirmation.'}
                />
              )}
            </>
          )}
        </div>

        <IonModal isOpen={!!attachmentPreview} onDidDismiss={() => setAttachmentPreview(null)} breakpoints={[0, 0.66]} initialBreakpoint={0.66}>
          <IonContent>
            <div className="page-pad">
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }}>
                <h2 className="font-display" style={{ margin: 0, fontSize: 18 }}>Attachment preview</h2>
                <IonButton size="small" fill="clear" onClick={() => setAttachmentPreview(null)}>
                  <IonIcon icon={closeOutline} />
                </IonButton>
              </div>
              {attachmentPreview?.file_type?.startsWith('image/') ? (
                <img src={attachmentPreview.url} alt="attachment" style={{ width: '100%', borderRadius: 12 }} />
              ) : null}
              {attachmentPreview?.file_type?.startsWith('video/') ? (
                <video src={attachmentPreview.url} controls style={{ width: '100%', borderRadius: 12 }} />
              ) : null}
              {attachmentPreview && !attachmentPreview.file_type?.startsWith('image/') && !attachmentPreview.file_type?.startsWith('video/') ? (
                <EmptyState title="Preview not supported" message="Open the file to view it." />
              ) : null}
            </div>
          </IonContent>
        </IonModal>
      </IonContent>
    </IonPage>
  );
};

export default TicketDetail;

