import {
  IonButton,
  IonContent,
  IonIcon,
  IonItem,
  IonLabel,
  IonList,
  IonPage,
  IonSelect,
  IonSelectOption,
  IonSpinner,
  IonTextarea,
  useIonRouter,
  useIonToast,
} from '@ionic/react';
import { createOutline } from 'ionicons/icons';
import { useMemo, useRef, useState } from 'react';
import AppHeader from '../components/AppHeader';
import SectionHeader from '../components/SectionHeader';
import EmptyState from '../components/EmptyState';
import type { CustomerTicketDetail } from '../api/types';
import { createCustomerTicket, uploadTicketAttachment } from '../api/tickets';
import { useAuth } from '../auth/AuthContext';

type EvidenceKind = 'image' | 'video' | 'file' | null;

type CategoryOption = {
  id: number;
  label: string;
};

// These IDs rely on ticket_categories seed order (6 rows). If your DB is not in sync,
// add a public categories endpoint instead of hardcoding.
const CATEGORY_OPTIONS: CategoryOption[] = [
  { id: 1, label: 'Power Interruption - Transmission/Substation' },
  { id: 2, label: 'Power Interruption - Distribution Line' },
  { id: 3, label: 'Billing/Payment Concern' },
  { id: 4, label: 'Meter/New Connection Application' },
  { id: 5, label: 'Other Institutional Request' },
  { id: 6, label: 'Other Concern' },
];

function detectEvidenceKind(file: File | null): EvidenceKind {
  if (!file) return null;
  if (file.type.startsWith('image/')) return 'image';
  if (file.type.startsWith('video/')) return 'video';
  return 'file';
}

async function compressImageIfReasonable(file: File): Promise<File> {
  // Only attempt compression for images.
  if (!file.type.startsWith('image/')) return file;

  const maxWidth = 1280;
  const quality = 0.82;

  const img = new Image();
  const srcUrl = URL.createObjectURL(file);
  try {
    await new Promise<void>((resolve, reject) => {
      img.onload = () => resolve();
      img.onerror = () => reject(new Error('Image decode failed'));
      img.src = srcUrl;
    });

    const scale = Math.min(1, maxWidth / img.width);
    const targetW = Math.max(1, Math.round(img.width * scale));
    const targetH = Math.max(1, Math.round(img.height * scale));

    const canvas = document.createElement('canvas');
    canvas.width = targetW;
    canvas.height = targetH;

    const ctx = canvas.getContext('2d');
    if (!ctx) return file;
    ctx.drawImage(img, 0, 0, targetW, targetH);

    const blob = await new Promise<Blob | null>((resolve) => {
      canvas.toBlob((b) => resolve(b), 'image/jpeg', quality);
    });

    if (!blob) return file;

    return new File([blob], `${file.name.replace(/\.[^/.]+$/, '')}.jpg`, { type: 'image/jpeg' });
  } finally {
    URL.revokeObjectURL(srcUrl);
  }
}

const Complaints: React.FC = () => {
  const router = useIonRouter();
  const [present] = useIonToast();
  const { token } = useAuth();

  const evidenceInputRef = useRef<HTMLInputElement | null>(null);

  const [categoryId, setCategoryId] = useState<number | ''>('');
  const [description, setDescription] = useState('');
  const [evidence, setEvidence] = useState<File | null>(null);
  const evidenceKind = detectEvidenceKind(evidence);

  const [submitting, setSubmitting] = useState(false);
  const [step, setStep] = useState<'form' | 'pending' | 'done'>('form');
  const [ticket, setTicket] = useState<CustomerTicketDetail | null>(null);
  const [uploadingEvidence, setUploadingEvidence] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  const selectedCategory = useMemo(
    () => CATEGORY_OPTIONS.find((c) => c.id === categoryId)?.label ?? '—',
    [categoryId],
  );

  const onPickEvidence = () => evidenceInputRef.current?.click();

  const onSelectEvidence = async (files: FileList | null) => {
    const f = files?.[0] ?? null;
    setEvidence(f);
  };

  const submit = async () => {
    if (!token) {
      present({ message: 'Please sign in again.', duration: 2200, color: 'warning' });
      return;
    }
    if (submitting) return;
    if (categoryId === '') {
      present({ message: 'Please select a category.', duration: 2200, color: 'warning' });
      return;
    }
    if (!description.trim()) {
      present({ message: 'Please describe your concern.', duration: 2200, color: 'warning' });
      return;
    }

    setSubmitting(true);
    setUploadingEvidence(false);
    setUploadError(null);
    setStep('pending');

    try {
      const created = await createCustomerTicket(token, {
        category_id: categoryId,
        description: description.trim(),
        subcategory: null,
        priority: 'normal',
      });

      setTicket(created);

      if (evidence) {
        setUploadingEvidence(true);
        // Compress images/videos where reasonable (images only for now).
        const uploadFile = evidenceKind === 'image' ? await compressImageIfReasonable(evidence) : evidence;
        try {
          // Evidence is optional; backend accepts a single attachment per request.
          await uploadTicketAttachment(token, created.id, uploadFile);
        } catch {
          setUploadError('Ticket submitted, but evidence upload failed. You can still view the ticket.');
          present({ message: 'Evidence upload failed.', duration: 2400, color: 'warning' });
        } finally {
          setUploadingEvidence(false);
        }
      }

      setStep('done');
      await present({
        message: 'Ticket submitted.',
        duration: 2000,
        color: 'success',
      });
    } catch (e: unknown) {
      setStep('form');
      setTicket(null);
      const message =
        e instanceof Error
          ? e.message
          : typeof e === 'object' && e !== null && 'message' in e
            ? String((e as { message: unknown }).message)
            : 'Could not submit your ticket. Try again.';
      present({ message, duration: 2800, color: 'warning' });
    } finally {
      setSubmitting(false);
    }
  };

  if (!token) {
    return (
      <IonPage>
        <AppHeader title="Report a Concern" icon={createOutline} backHref="/tabs/tickets" />
        <IonContent>
          <div className="page-pad">
            <EmptyState title="Please sign in" message="Your session is missing. Sign in again to submit a ticket." />
          </div>
        </IonContent>
      </IonPage>
    );
  }

  return (
    <IonPage>
      <AppHeader title="Report a Concern" icon={createOutline} backHref="/tabs/tickets" />
      <IonContent>
        <div className="page-pad">
          {step === 'form' ? (
            <>
              <SectionHeader title="Choose a category" />
              <IonList className="soft-list">
                <IonItem>
                  <IonLabel position="stacked">Category</IonLabel>
                  <IonSelect
                    placeholder="Select category"
                    value={categoryId}
                    onIonChange={(e) => setCategoryId(Number(e.detail.value))}
                  >
                    {CATEGORY_OPTIONS.map((c) => (
                      <IonSelectOption key={c.id} value={c.id}>
                        {c.label}
                      </IonSelectOption>
                    ))}
                  </IonSelect>
                </IonItem>

                <IonItem>
                  <IonLabel position="stacked">Evidence (optional)</IonLabel>
                  <IonButton expand="block" fill="outline" className="soft-btn" onClick={onPickEvidence}>
                    Attach photo/video/file
                  </IonButton>
                  <input
                    ref={evidenceInputRef}
                    type="file"
                    style={{ display: 'none' }}
                    accept="image/*,video/*,application/pdf"
                    onChange={(e) => void onSelectEvidence(e.target.files)}
                    capture="environment"
                  />
                  <p style={{ margin: '8px 0 0', fontSize: 12, color: 'var(--aselco-ink-500)' }}>
                    {evidence
                      ? `${evidence.name} (${evidenceKind ?? 'file'})`
                      : 'No evidence selected.'}
                  </p>
                </IonItem>

                <IonItem lines="none" style={{ '--background': 'transparent' } as React.CSSProperties}>
                  <IonLabel position="stacked">Description</IonLabel>
                  <IonTextarea
                    autoGrow
                    rows={5}
                    value={description}
                    placeholder="Write a short description (what happened, where, and when)."
                    onIonInput={(e) => setDescription(String(e.detail.value ?? ''))}
                  />
                </IonItem>
              </IonList>

              <div className="soft-card" style={{ marginTop: 12 }}>
                <div className="kv">
                  <span className="kv__k">Selected category</span>
                  <span className="kv__v">{selectedCategory}</span>
                </div>
                <div className="kv">
                  <span className="kv__k">Evidence</span>
                  <span className="kv__v">{evidence ? 'Attached' : 'None'}</span>
                </div>
              </div>

              <IonButton
                expand="block"
                className="soft-btn ion-margin-top"
                disabled={submitting}
                onClick={() => void submit()}
              >
                {submitting ? 'Submitting…' : 'Submit concern'}
              </IonButton>
            </>
          ) : (
            <>
              <section className="success-hero soft-card">
                {step === 'pending' || uploadingEvidence ? <IonSpinner name="crescent" /> : null}
                <IonIcon icon={step === 'pending' || uploadingEvidence ? createOutline : createOutline} style={{ display: step === 'pending' || uploadingEvidence ? 'none' : undefined }} />
                <h2 className="font-display" style={{ margin: '8px 0 4px', fontSize: 20 }}>
                  {step === 'pending' ? 'Submitting your ticket…' : 'Ticket submitted'}
                </h2>
                <p style={{ margin: 0, color: 'var(--aselco-ink-500)' }}>
                  {ticket?.ticket_no ? (
                    <>
                      Your ticket ID is <strong>{ticket.ticket_no}</strong>.
                    </>
                  ) : (
                    'Please wait…'
                  )}
                </p>
                {uploadingEvidence ? (
                  <p style={{ margin: '10px 0 0', color: 'var(--aselco-ink-500)', fontSize: 13 }}>
                    Uploading evidence…
                  </p>
                ) : null}
                {uploadError ? (
                  <p style={{ margin: '10px 0 0', color: 'var(--ion-color-warning)', fontSize: 13 }}>{uploadError}</p>
                ) : null}
              </section>

              <IonButton
                expand="block"
                className="soft-btn ion-margin-top"
                disabled={!ticket}
                onClick={() => ticket && router.push(`/tickets/${ticket.id}`)}
              >
                View ticket
              </IonButton>
              <IonButton
                expand="block"
                fill="outline"
                className="soft-btn"
                onClick={() => {
                  setCategoryId('');
                  setDescription('');
                  setEvidence(null);
                  setTicket(null);
                  setStep('form');
                  setUploadError(null);
                }}
              >
                File another
              </IonButton>
            </>
          )}
        </div>
      </IonContent>
    </IonPage>
  );
};

export default Complaints;

