import {
  IonButton,
  IonContent,
  IonFooter,
  IonIcon,
  IonInput,
  IonPage,
  IonSpinner,
  useIonToast,
} from '@ionic/react';
import { headsetOutline, sendOutline } from 'ionicons/icons';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AppHeader from '../components/AppHeader';
import { useAuth } from '../auth/AuthContext';
import {
  ensureSupportChat,
  listSupportMessages,
  markSupportChatRead,
  sendSupportMessage,
  type SupportChatMessage,
} from '../api/chat';
import {
  leaveConversation,
  leaveUserChannel,
  subscribeConversation,
  subscribeUserChannel,
} from '../realtime/supportPusher';
import './SupportChat.css';

type ChatRow = SupportChatMessage & { pending?: boolean; failed?: boolean };

type ThreadItem =
  | { kind: 'day'; key: string; label: string }
  | { kind: 'message'; key: string; message: ChatRow };

function startOfDay(date: Date): number {
  const d = new Date(date);
  d.setHours(0, 0, 0, 0);
  return d.getTime();
}

function humanizeDateLabel(iso?: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const today = startOfDay(new Date());
  const target = startOfDay(date);
  const dayMs = 86400000;
  if (target === today) return 'Today';
  if (target === today - dayMs) return 'Yesterday';
  return date.toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric',
  });
}

function humanizeTime(iso?: string | null): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';

  const diffSec = Math.round((Date.now() - date.getTime()) / 1000);
  if (diffSec < 45) return 'just now';
  if (diffSec < 3600) {
    return `${Math.max(1, Math.round(diffSec / 60))}m ago`;
  }

  const today = startOfDay(new Date());
  const target = startOfDay(date);
  const time = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

  if (target === today) return time;
  if (target === today - 86400000) return `Yesterday ${time}`;

  const sameYear = date.getFullYear() === new Date().getFullYear();
  const day = date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    year: sameYear ? undefined : 'numeric',
  });
  return `${day}, ${time}`;
}

const SupportChat: React.FC = () => {
  const { token, user } = useAuth();
  const [present] = useIonToast();
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [messages, setMessages] = useState<ChatRow[]>([]);
  const [draft, setDraft] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [tick, setTick] = useState(0);
  const bottomRef = useRef<HTMLDivElement | null>(null);
  const pendingSeq = useRef(0);

  const scrollBottom = () => {
    requestAnimationFrame(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }));
  };

  const mergeMessages = useCallback((rows: ChatRow[]) => {
    setMessages((prev) => {
      const map = new Map<number, ChatRow>();
      [...prev, ...rows].forEach((row) => {
        if (!row?.id) return;
        map.set(row.id, { ...map.get(row.id), ...row, pending: false, failed: false });
      });
      prev.forEach((row) => {
        if (row.pending && row.id < 0 && !rows.some((r) => !r.pending && r.body === row.body)) {
          map.set(row.id, row);
        }
      });
      return Array.from(map.values()).sort((a, b) => {
        const at = a.created_at ? Date.parse(a.created_at) : a.id;
        const bt = b.created_at ? Date.parse(b.created_at) : b.id;
        return at - bt || a.id - b.id;
      });
    });
  }, []);

  const ingestRealtime = useCallback((payload: unknown) => {
    const message =
      (payload as { message?: SupportChatMessage })?.message ?? (payload as SupportChatMessage);
    if (!message?.id) return;
    setMessages((prev) => {
      const withoutPending = prev.filter(
        (row) =>
          !(row.pending && row.body === message.body && Number(row.user?.id) === Number(message.user?.id)),
      );
      const map = new Map<number, ChatRow>();
      [...withoutPending, message].forEach((row) => map.set(row.id, row));
      return Array.from(map.values()).sort((a, b) => a.id - b.id);
    });
    scrollBottom();
  }, []);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;
    let channelConvId: number | null = null;
    let channelUserId: number | null = null;
    let pollTimer: ReturnType<typeof setInterval> | null = null;

    (async () => {
      try {
        setLoading(true);
        const ensured = await ensureSupportChat(token);
        if (cancelled) return;
        const id = ensured.data.id;
        setConversationId(id);
        const history = await listSupportMessages(token, id);
        if (cancelled) return;
        setMessages(history.data ?? []);
        await markSupportChatRead(token, id);
        channelConvId = id;
        subscribeConversation(token, id, ingestRealtime);
        if (user?.id) {
          channelUserId = Number(user.id);
          subscribeUserChannel(token, channelUserId, ingestRealtime);
        }
        scrollBottom();

        pollTimer = setInterval(async () => {
          if (document.hidden || cancelled) return;
          try {
            const latest = await listSupportMessages(token, id);
            if (cancelled) return;
            mergeMessages(latest.data ?? []);
          } catch {
            /* ignore poll errors */
          }
        }, 4000);
      } catch (error) {
        present({
          message: error instanceof Error ? error.message : 'Unable to open support chat',
          duration: 2500,
          color: 'danger',
        });
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();

    return () => {
      cancelled = true;
      if (pollTimer) clearInterval(pollTimer);
      if (channelConvId) leaveConversation(channelConvId);
      if (channelUserId) leaveUserChannel(channelUserId);
    };
  }, [token, user?.id, mergeMessages, ingestRealtime, present]);

  useEffect(() => {
    scrollBottom();
  }, [messages.length]);

  useEffect(() => {
    const timer = setInterval(() => setTick((n) => n + 1), 60000);
    return () => clearInterval(timer);
  }, []);

  const threadItems = useMemo(() => {
    const items: ThreadItem[] = [];
    let lastDayKey: number | null = null;
    messages.forEach((message) => {
      if (message.created_at) {
        const dayKey = startOfDay(new Date(message.created_at));
        if (dayKey !== lastDayKey) {
          items.push({
            kind: 'day',
            key: `day-${dayKey}`,
            label: humanizeDateLabel(message.created_at),
          });
          lastDayKey = dayKey;
        }
      }
      items.push({ kind: 'message', key: `msg-${message.id}`, message });
    });
    return items;
  }, [messages, tick]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token || !conversationId || !draft.trim() || sending) return;
    const body = draft.trim();
    const tempId = -(Date.now() + ++pendingSeq.current);
    const optimistic: ChatRow = {
      id: tempId,
      conversation_id: conversationId,
      body,
      created_at: new Date().toISOString(),
      pending: true,
      user: {
        id: user?.id ?? null,
        name: user?.name ?? 'You',
      },
    };

    setDraft('');
    setSending(true);
    setMessages((prev) => [...prev, optimistic]);
    scrollBottom();

    try {
      const res = await sendSupportMessage(token, conversationId, body);
      setMessages((prev) => {
        const next = prev.filter((row) => row.id !== tempId);
        const map = new Map<number, ChatRow>();
        [...next, { ...res.data, pending: false }].forEach((row) => map.set(row.id, row));
        return Array.from(map.values()).sort((a, b) => a.id - b.id);
      });
      scrollBottom();
    } catch (error) {
      setMessages((prev) =>
        prev.map((row) => (row.id === tempId ? { ...row, pending: false, failed: true } : row)),
      );
      setDraft(body);
      present({
        message: error instanceof Error ? error.message : 'Send failed',
        duration: 2200,
        color: 'danger',
      });
    } finally {
      setSending(false);
    }
  }

  return (
    <IonPage>
      <AppHeader title="Live support" icon={headsetOutline} backHref="/support" />
      <IonContent>
        <div className="support-chat-page">
          {loading ? (
            <div className="support-chat-loading">
              <IonSpinner name="crescent" />
            </div>
          ) : (
            <div className="support-chat-messages">
              {messages.length === 0 ? (
                <p className="support-chat-empty">Say hello — an ASELCO agent will reply here.</p>
              ) : (
                threadItems.map((item) => {
                  if (item.kind === 'day') {
                    return (
                      <div key={item.key} className="support-chat-day" role="separator">
                        <span className="support-chat-day__badge">{item.label}</span>
                      </div>
                    );
                  }

                  const message = item.message;
                  const mine = Number(message.user?.id) === Number(user?.id);
                  const classes = [
                    'support-chat-bubble',
                    'is-enter',
                    mine ? 'is-mine' : '',
                    message.pending ? 'is-sending' : '',
                    message.failed ? 'is-failed' : '',
                  ]
                    .filter(Boolean)
                    .join(' ');

                  return (
                    <div key={item.key} className={classes}>
                      <div className="support-chat-bubble__meta">
                        <span>{mine ? 'You' : 'Live Support'}</span>
                        <time dateTime={message.created_at || undefined}>
                          {message.pending
                            ? 'Sending…'
                            : message.failed
                              ? 'Failed'
                              : humanizeTime(message.created_at)}
                        </time>
                      </div>
                      <div className="support-chat-bubble__body">{message.body}</div>
                    </div>
                  );
                })
              )}
              <div ref={bottomRef} />
            </div>
          )}
        </div>
      </IonContent>
      <IonFooter className="support-chat-footer ion-no-border">
        <form className="support-chat-composer" onSubmit={onSubmit}>
          <IonInput
            value={draft}
            placeholder="Type a message…"
            maxlength={5000}
            onIonInput={(e) => setDraft(String(e.detail.value ?? ''))}
          />
          <IonButton
            type="submit"
            className={sending ? 'is-send-pulse' : ''}
            disabled={sending || !draft.trim()}
          >
            <IonIcon slot="icon-only" icon={sendOutline} />
          </IonButton>
        </form>
      </IonFooter>
    </IonPage>
  );
};

export default SupportChat;
