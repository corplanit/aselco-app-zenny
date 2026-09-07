import {
  IonButton,
  IonContent,
  IonFooter,
  IonIcon,
  IonInput,
  IonPage,
  IonSpinner,
  IonToolbar,
} from '@ionic/react';
import {
  chatbubbleEllipsesOutline,
  headsetOutline,
  refreshOutline,
  send,
  ticketOutline,
} from 'ionicons/icons';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { useHistory } from 'react-router-dom';
import {
  deleteAiConversation,
  escalateAiConversation,
  fetchAiBootstrap,
  listAiConversations,
  sendAiChat,
  showAiConversation,
} from '../api/ai';
import { ApiError, type AiCustomerReply } from '../api/types';
import { useAuth } from '../auth/AuthContext';
import AppHeader from '../components/AppHeader';

interface ChatMessage {
  id: string;
  role: 'bot' | 'user';
  text: string;
  nextHref?: string | null;
  nextLabel?: string | null;
  citations?: { title: string; score: number }[];
  actions?: { label: string; href: string; action: string }[];
  followUps?: string[];
}

const DEFAULT_WELCOME: ChatMessage[] = [
  { id: 'b1', role: 'bot', text: "Hello! I'm your ASELCO customer service assistant." },
  {
    id: 'b2',
    role: 'bot',
    text: 'Ask about billing, services, FAQs, or complaint guidance. I cannot change bills or tickets — I guide you into the official workflows.',
  },
];

const DEFAULT_SUGGESTIONS = [
  { label: 'Billing help', message: 'Explain how billing and AST payments work' },
  { label: 'Report outage', message: 'I have a power interruption. What should I do?' },
  { label: 'File a complaint', message: 'Help me choose a complaint category and prepare a description' },
  { label: 'Talk to CSR', message: 'I want to talk to a human customer service representative' },
];

const Assistant: React.FC = () => {
  const { token } = useAuth();
  const history = useHistory();
  const [messages, setMessages] = useState<ChatMessage[]>(DEFAULT_WELCOME);
  const [draft, setDraft] = useState('');
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [sending, setSending] = useState(false);
  const [escalating, setEscalating] = useState(false);
  const [offlineHint, setOfflineHint] = useState<string | null>(null);
  const [lastFailedMessage, setLastFailedMessage] = useState<string | null>(null);
  const [suggestions, setSuggestions] = useState(DEFAULT_SUGGESTIONS);
  const [followUps, setFollowUps] = useState<string[]>([]);
  const bottomRef = useRef<HTMLDivElement>(null);

  const scrollToEnd = () => {
    requestAnimationFrame(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }));
  };

  const resetLocalChat = useCallback((welcome?: ChatMessage[]) => {
    setMessages(welcome ?? DEFAULT_WELCOME);
    setConversationId(null);
    setOfflineHint(null);
    setLastFailedMessage(null);
    setFollowUps([]);
  }, []);

  useEffect(() => {
    if (!token) return;
    let cancelled = false;

    (async () => {
      try {
        const boot = await fetchAiBootstrap(token);
        if (cancelled) return;
        if (boot.suggested_questions?.length) {
          setSuggestions(boot.suggested_questions);
        }
        if (boot.welcome?.length) {
          setMessages(
            boot.welcome.map((text, i) => ({
              id: `w-${i}`,
              role: 'bot' as const,
              text,
            })),
          );
        }
      } catch {
        // Keep defaults.
      }

      try {
        const list = await listAiConversations(token);
        const latest = list.data?.[0];
        if (!latest || cancelled) return;
        const detail = await showAiConversation(token, latest.id);
        if (cancelled) return;
        setConversationId(detail.id);
        if (detail.messages.length === 0) return;
        setMessages(
          detail.messages.map((m) => ({
            id: String(m.id),
            role: m.role === 'user' ? 'user' : 'bot',
            text: m.content,
            nextHref:
              m.suggested_action === 'file_ticket'
                ? '/complaints'
                : m.suggested_action === 'view_tickets'
                  ? '/tabs/tickets'
                  : m.suggested_action === 'pay_bill'
                    ? '/tabs/pay'
                    : m.suggested_action === 'contact_support'
                      ? '/support'
                      : null,
            nextLabel: m.escalate
              ? m.suggested_action === 'view_tickets'
                ? 'View my tickets'
                : 'Report a concern'
              : null,
          })),
        );
      } catch {
        // Keep welcome copy.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [token]);

  const applyReply = (reply: AiCustomerReply) => {
    setConversationId(reply.conversation_id);
    const bot: ChatMessage = {
      id: `b-${reply.conversation_id}-${Date.now()}`,
      role: 'bot',
      text: reply.reply,
      nextHref: reply.next_step?.href ?? null,
      nextLabel: reply.next_step?.href ? reply.next_step.label : null,
      citations: (reply.citations ?? []).map((c) => ({ title: c.title, score: c.score })),
      actions: reply.actions,
      followUps: reply.follow_ups,
    };
    setMessages((prev) => [...prev, bot]);
    setFollowUps(reply.follow_ups ?? []);
    if (reply.suggested_questions?.length) {
      setSuggestions(reply.suggested_questions);
    }
    setOfflineHint(null);
    setLastFailedMessage(null);
    scrollToEnd();
  };

  const sendMessage = async (text: string) => {
    const trimmed = text.trim();
    if (!trimmed || sending || !token) return;

    const userMsg: ChatMessage = {
      id: `u-${Date.now()}`,
      role: 'user',
      text: trimmed,
    };
    setMessages((prev) => [...prev, userMsg]);
    setDraft('');
    setSending(true);
    scrollToEnd();

    try {
      const reply = await sendAiChat(token, trimmed, conversationId);
      applyReply(reply);
    } catch (err) {
      const message =
        err instanceof ApiError
          ? err.message
          : 'I could not reach the assistant. You can still file a concern under Tickets or open Support.';
      setOfflineHint(message);
      setLastFailedMessage(trimmed);
      setMessages((prev) => [
        ...prev,
        {
          id: `b-err-${Date.now()}`,
          role: 'bot',
          text: 'I could not reach the assistant right now. Tickets, billing, and Support still work as usual.',
          actions: [
            { label: 'Contact Customer Service', href: '/support', action: 'contact_support' },
            { label: 'Create Complaint / Ticket', href: '/complaints', action: 'file_ticket' },
          ],
        },
      ]);
      scrollToEnd();
    } finally {
      setSending(false);
    }
  };

  const startNewConversation = async () => {
    if (!token) {
      resetLocalChat();
      return;
    }
    try {
      if (conversationId) {
        await deleteAiConversation(token, conversationId);
      }
    } catch {
      // Still reset local UI.
    }
    resetLocalChat();
    try {
      const boot = await fetchAiBootstrap(token);
      if (boot.welcome?.length) {
        resetLocalChat(
          boot.welcome.map((text, i) => ({
            id: `w-${i}-${Date.now()}`,
            role: 'bot',
            text,
          })),
        );
      }
      if (boot.suggested_questions?.length) {
        setSuggestions(boot.suggested_questions);
      }
    } catch {
      // defaults already applied
    }
  };

  const escalateToHuman = async (preferTicket: boolean) => {
    if (!token || escalating) return;
    setEscalating(true);
    try {
      const result = await escalateAiConversation(token, {
        conversation_id: conversationId,
        category_hint: preferTicket ? 'FOCAL' : 'FOCAL',
        message: 'Customer requested human assistance from the AI assistant.',
      });
      if (result.escalated && result.ticket) {
        setMessages((prev) => [
          ...prev,
          {
            id: `esc-${Date.now()}`,
            role: 'bot',
            text: `${result.message ?? 'Escalated to Customer Service.'} Ticket ${result.ticket.ticket_no} is in the official routing workflow.`,
            nextHref: result.next_step?.href ?? `/tickets/${result.ticket.id}`,
            nextLabel: result.next_step?.label ?? 'View ticket',
            actions: [
              { label: 'View My Tickets', href: '/tabs/tickets', action: 'view_tickets' },
              { label: 'Contact Customer Service', href: '/support', action: 'contact_support' },
            ],
          },
        ]);
        scrollToEnd();
      } else if (!preferTicket) {
        history.push('/support');
      } else {
        history.push('/complaints');
      }
    } catch (err) {
      setOfflineHint(err instanceof ApiError ? err.message : 'Escalation failed. Open Support or Report a Concern.');
      history.push(preferTicket ? '/complaints' : '/support');
    } finally {
      setEscalating(false);
    }
  };

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    void sendMessage(draft);
  };

  return (
    <IonPage>
      <AppHeader title="AI Assistant" icon={chatbubbleEllipsesOutline} backHref="/tabs/home" />
      <IonContent className="assistant-content">
        <div className="assistant-shell page-pad">
          <div className="assistant-card">
            <div className="assistant-card__head">
              <div className="assistant-avatar" aria-hidden>
                <span className="assistant-avatar__face">🤖</span>
                <span className="assistant-avatar__dot" />
              </div>
              <div className="assistant-card__titles">
                <h1 className="assistant-card__title">AI ASSISTANT</h1>
                <p className="assistant-card__sub">Billing · FAQ · Complaints · Guided support</p>
              </div>
              <button
                type="button"
                className="assistant-reset"
                onClick={() => void startNewConversation()}
                aria-label="New conversation"
              >
                <IonIcon icon={refreshOutline} />
              </button>
            </div>

            <div className="assistant-toolbar">
              <button
                type="button"
                className="assistant-toolbar__btn"
                disabled={escalating || sending}
                onClick={() => void escalateToHuman(false)}
              >
                <IonIcon icon={headsetOutline} />
                Contact Customer Service
              </button>
              <button
                type="button"
                className="assistant-toolbar__btn"
                disabled={escalating || sending}
                onClick={() => void escalateToHuman(true)}
              >
                <IonIcon icon={ticketOutline} />
                Create Complaint / Ticket
              </button>
            </div>

            <div className="assistant-messages">
              {messages.map((m) => (
                <div key={m.id}>
                  <div className={`chat-bubble ${m.role === 'user' ? 'chat-bubble--user' : 'chat-bubble--bot'}`}>
                    {m.text}
                  </div>
                  {m.role === 'bot' && m.nextHref && m.nextLabel ? (
                    <button
                      type="button"
                      className="assistant-next"
                      onClick={() => history.push(m.nextHref!)}
                    >
                      {m.nextLabel}
                    </button>
                  ) : null}
                  {m.role === 'bot' && m.actions && m.actions.length > 0 ? (
                    <div className="assistant-action-row">
                      {m.actions.map((action) => (
                        <button
                          key={`${m.id}-${action.action}`}
                          type="button"
                          className="assistant-next"
                          onClick={() => history.push(action.href)}
                        >
                          {action.label}
                        </button>
                      ))}
                    </div>
                  ) : null}
                  {m.role === 'bot' && m.citations && m.citations.length > 0 ? (
                    <div className="assistant-citations">
                      Sources: {m.citations.map((c) => c.title).join(' · ')}
                    </div>
                  ) : null}
                </div>
              ))}
              {sending || escalating ? (
                <div className="chat-bubble chat-bubble--bot assistant-typing">
                  <IonSpinner name="dots" />
                </div>
              ) : null}
              <div ref={bottomRef} />
            </div>

            {offlineHint ? (
              <div className="assistant-error-row">
                <p className="assistant-error">{offlineHint}</p>
                {lastFailedMessage ? (
                  <button
                    type="button"
                    className="assistant-retry"
                    disabled={sending}
                    onClick={() => void sendMessage(lastFailedMessage)}
                  >
                    Retry
                  </button>
                ) : null}
              </div>
            ) : null}

            {(followUps.length > 0 ? followUps : suggestions.map((s) => s.label)).length > 0 ? (
              <div className="assistant-quick">
                {(followUps.length > 0
                  ? followUps.map((label) => ({ label, message: label }))
                  : suggestions
                ).map((item) => (
                  <button
                    key={item.label}
                    type="button"
                    className="assistant-chip"
                    disabled={sending}
                    onClick={() => void sendMessage(item.message)}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            ) : null}
          </div>
        </div>
      </IonContent>

      <IonFooter className="assistant-footer">
        <IonToolbar>
          <form className="assistant-composer" onSubmit={onSubmit}>
            <IonInput
              className="assistant-composer__input"
              value={draft}
              placeholder="Ask about billing, services, or complaints..."
              disabled={sending}
              onIonInput={(e) => setDraft(String(e.detail.value ?? ''))}
            />
            <IonButton
              type="submit"
              className="assistant-send"
              aria-label="Send"
              disabled={!draft.trim() || sending}
            >
              <IonIcon slot="icon-only" icon={send} />
            </IonButton>
          </form>
        </IonToolbar>
      </IonFooter>
    </IonPage>
  );
};

export default Assistant;
