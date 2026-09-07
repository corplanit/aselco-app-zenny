<x-app-layout>
    @php
        $authId = (int) $authUser->id;
        $initialId = $activeConversation?->id;
        $initialCustomerId = $activeConversation?->customer_id ?? ($isAgent ? null : $authId);
    @endphp

    <x-slot name="title">Customer Support</x-slot>
    <x-slot name="hidePageHeader">1</x-slot>
    <x-slot name="url_1">{"link": "{{ route('chats.support') }}", "text": "Support"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('chats.support') }}", "text": "Live chat"}</x-slot>
    <x-slot name="active">{{ $isAgent ? 'Inbox' : 'My chat' }}</x-slot>

    <div class="ul-support-chat" id="support-chat-root"
         data-auth-id="{{ $authId }}"
         data-is-agent="{{ $isAgent ? '1' : '0' }}"
         data-initial-id="{{ $initialId }}"
         data-customer-id="{{ $initialCustomerId }}">
        <aside class="ul-support-sidebar">
            <div class="ul-support-side-head">
                <div class="ul-support-side-title">{{ $isAgent ? 'Conversations' : 'Channels' }}</div>
                <span class="ul-support-live-pill">Live</span>
            </div>
            <div class="ul-support-list" id="support-conversation-list">
                @forelse($conversations as $conversation)
                    <button
                        type="button"
                        class="ul-support-item{{ (int) $conversation->id === (int) $initialId ? ' is-active' : '' }}"
                        data-conversation-id="{{ $conversation->id }}"
                        data-title="{{ $isAgent ? $conversation->display_title : 'Live Support' }}"
                        data-customer-id="{{ $conversation->customer_id }}"
                        data-last-at="{{ $conversation->last_message_at }}"
                    >
                        <span class="ul-support-item-avatar" aria-hidden="true">
                            {{ $isAgent ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($conversation->display_title ?? 'C', 0, 1)) : 'LS' }}
                        </span>
                        <span class="ul-support-item-copy">
                            <span class="ul-support-item-top">
                                <span class="ul-support-item-title">{{ $isAgent ? $conversation->display_title : 'Live Support' }}</span>
                                <span class="ul-support-item-time" data-last-at>{{ $conversation->last_message_at ? \Illuminate\Support\Carbon::parse($conversation->last_message_at)->diffForHumans(null, false, true) : '' }}</span>
                            </span>
                            <span class="ul-support-item-meta" data-last-message>{{ \Illuminate\Support\Str::limit($conversation->last_message_body ?: 'No messages yet', 42) }}</span>
                        </span>
                        <span class="ul-support-unread{{ ($conversation->unread_count ?? 0) > 0 ? '' : ' is-empty' }}" data-unread>
                            {{ ($conversation->unread_count ?? 0) > 0 ? $conversation->unread_count : '' }}
                        </span>
                    </button>
                @empty
                    <div class="ul-support-empty-list">No conversations yet.</div>
                @endforelse
            </div>
        </aside>

        <section class="ul-support-thread">
            <header class="ul-support-thread-head">
                <div>
                    <h1 class="ul-support-thread-title" id="support-thread-title">
                        {{ $isAgent ? ($activeConversation?->display_title ?? 'Select a conversation') : 'Live Support' }}
                    </h1>
                    <p class="ul-support-thread-sub" id="support-thread-sub">
                        {{ $isAgent ? 'Customer support conversation' : 'Chat is only visible to you and ASELCO support.' }}
                    </p>
                </div>
            </header>

            <div class="ul-support-messages" id="support-messages" aria-live="polite"></div>

            <form class="ul-support-composer" id="support-composer" autocomplete="off">
                <div class="ul-support-composer-box">
                    <input
                        type="text"
                        id="support-input"
                        class="ul-support-composer-input"
                        maxlength="5000"
                        placeholder="{{ $isAgent ? 'Message customer…' : 'Message Live Support…' }}"
                        {{ $isAgent && ! $initialId ? 'disabled' : '' }}
                    >
                    <button type="submit" class="ul-support-send" id="support-send" {{ $isAgent && ! $initialId ? 'disabled' : '' }}>
                        Send
                    </button>
                </div>
            </form>
        </section>
    </div>

    <style>
        .ul-support-chat {
            --ls-accent: #2563eb;
            --ls-accent-soft: #eff6ff;
            --ls-text: #0f172a;
            --ls-muted: #94a3b8;
            --ls-border: #e2e8f0;
            --ls-side: #f8fafc;
            display: grid;
            grid-template-columns: minmax(15rem, 17.5rem) minmax(0, 1fr);
            gap: 0;
            min-height: min(74vh, 42rem);
            margin-top: 0.35rem;
            margin-bottom: 16px;
            border: 1px solid var(--ls-border);
            border-radius: 0.75rem;
            overflow: hidden;
            background: #fff;
        }
        @media (max-width: 991px) {
            .ul-support-chat { grid-template-columns: 1fr; min-height: auto; }
        }

        .ul-support-sidebar {
            display: flex;
            flex-direction: column;
            background: var(--ls-side);
            border-right: 1px solid var(--ls-border);
            min-height: 0;
        }
        .ul-support-side-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding: 0.7rem 0.85rem;
            border-bottom: 1px solid var(--ls-border);
            background: #fff;
        }
        .ul-support-side-title {
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #64748b;
        }
        .ul-support-live-pill {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.15rem 0.45rem;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
        }
        .ul-support-list {
            flex: 1;
            overflow: auto;
            max-height: min(62vh, 34rem);
        }
        .ul-support-item {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            width: 100%;
            padding: 0.55rem 0.75rem;
            border: 0;
            border-bottom: 1px solid transparent;
            background: transparent;
            text-align: left;
            cursor: pointer;
            color: inherit;
        }
        .ul-support-item:hover { background: #fff; }
        .ul-support-item.is-active {
            background: var(--ls-accent-soft);
            box-shadow: inset 3px 0 0 var(--ls-accent);
        }
        .ul-support-item-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #e2e8f0;
            color: #334155;
            font-size: 0.72rem;
            font-weight: 700;
        }
        .ul-support-item.is-active .ul-support-item-avatar {
            background: var(--ls-accent);
            color: #fff;
        }
        .ul-support-item-copy { display: grid; gap: 0.05rem; min-width: 0; flex: 1; }
        .ul-support-item-top {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.4rem;
            min-width: 0;
        }
        .ul-support-item-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--ls-text);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ul-support-item-time {
            flex-shrink: 0;
            font-size: 0.62rem;
            color: var(--ls-muted);
            font-weight: 600;
        }
        .ul-support-item-meta {
            font-size: 0.7rem;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ul-support-unread {
            min-width: 1.1rem;
            height: 1.1rem;
            padding: 0 0.3rem;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 0.62rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .ul-support-unread.is-empty { display: none; }
        .ul-support-empty-list {
            padding: 1.25rem 0.85rem;
            color: var(--ls-muted);
            font-size: 0.78rem;
            text-align: center;
        }

        .ul-support-thread {
            display: flex;
            flex-direction: column;
            min-width: 0;
            min-height: 28rem;
            background:
                radial-gradient(ellipse 70% 40% at 0% 0%, rgba(37, 99, 235, 0.08), transparent 55%),
                radial-gradient(ellipse 55% 35% at 100% 0%, rgba(14, 124, 58, 0.07), transparent 50%),
                linear-gradient(180deg, #f8fafc 0%, #ffffff 42%, #f1f5f9 100%);
            position: relative;
        }
        .ul-support-thread-head {
            position: relative;
            z-index: 1;
            padding: 0.75rem 1rem 0.55rem;
            border-bottom: 1px solid var(--ls-border);
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(8px);
        }
        .ul-support-thread-title {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--ls-text);
            line-height: 1.2;
        }
        .ul-support-thread-sub {
            margin: 0.2rem 0 0;
            font-size: 0.72rem;
            color: #64748b;
            font-style: italic;
        }

        .ul-support-messages {
            position: relative;
            z-index: 1;
            flex: 1;
            min-height: 16rem;
            max-height: min(58vh, 30rem);
            overflow: auto;
            padding: 0.65rem 1rem 0.85rem;
            display: flex;
            flex-direction: column;
            gap: 0;
            background-image: radial-gradient(rgba(15, 23, 42, 0.035) 1px, transparent 1px);
            background-size: 16px 16px;
        }
        .ul-support-day {
            display: flex;
            align-items: center;
            align-self: stretch;
            gap: 0.65rem;
            width: 100%;
            margin: 0.75rem 0 0.55rem;
        }
        .ul-support-day::before,
        .ul-support-day::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(15, 23, 42, 0.14),
                rgba(15, 23, 42, 0.14),
                transparent
            );
        }
        .ul-support-day__badge {
            flex-shrink: 0;
            padding: 0.18rem 0.65rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            color: #64748b;
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .ul-support-msg {
            display: grid;
            grid-template-columns: 2.25rem minmax(0, 1fr);
            gap: 0.55rem;
            padding: 0.45rem 0.35rem;
            border-radius: 0.4rem;
            align-items: start;
        }
        .ul-support-msg:hover { background: rgba(248, 250, 252, 0.85); }
        .ul-support-msg.is-continue {
            grid-template-columns: 2.25rem minmax(0, 1fr);
            padding-top: 0.08rem;
            padding-bottom: 0.08rem;
        }
        .ul-support-msg.is-continue .ul-support-msg-avatar { visibility: hidden; }
        .ul-support-msg.is-continue .ul-support-msg-head { display: none; }
        .ul-support-msg-avatar {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.72rem;
            font-weight: 700;
            color: #fff;
            background: #64748b;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }
        .ul-support-msg.is-support .ul-support-msg-avatar {
            background: var(--ls-accent);
        }
        .ul-support-msg.is-mine .ul-support-msg-avatar {
            background: #0e7c3a;
        }
        .ul-support-msg-main { min-width: 0; }
        .ul-support-msg-head {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.4rem 0.55rem;
            margin-bottom: 0.1rem;
            line-height: 1.2;
        }
        .ul-support-msg-name {
            font-size: 0.84rem;
            font-weight: 700;
            color: var(--ls-accent);
        }
        .ul-support-msg.is-mine .ul-support-msg-name {
            color: #0e7c3a;
        }
        .ul-support-msg-time {
            font-size: 0.72rem;
            color: var(--ls-muted);
            font-weight: 500;
        }
        .ul-support-msg-body {
            font-size: 0.88rem;
            line-height: 1.45;
            color: var(--ls-text);
            white-space: pre-wrap;
            word-break: break-word;
        }

        .ul-support-composer {
            position: relative;
            z-index: 1;
            padding: 0.65rem 0.85rem 0.85rem;
            border-top: 1px solid var(--ls-border);
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(8px);
        }
        .ul-support-composer-box {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.35rem 0.4rem 0.35rem 0.75rem;
            border: 1px solid var(--ls-border);
            border-radius: 0.55rem;
            background: #fff;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }
        .ul-support-composer-input {
            flex: 1;
            border: 0;
            outline: none;
            background: transparent;
            min-height: 2.2rem;
            font-size: 0.88rem;
            color: var(--ls-text);
        }
        .ul-support-composer-input:disabled { opacity: 0.55; }
        .ul-support-send {
            border: 0;
            border-radius: 0.4rem;
            background: var(--ls-accent-soft);
            color: var(--ls-accent);
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.45rem 0.9rem;
            cursor: pointer;
        }
        .ul-support-send:hover:not(:disabled) {
            background: #dbeafe;
        }
        .ul-support-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .ul-support-empty {
            margin: auto;
            color: var(--ls-muted);
            font-size: 0.84rem;
            text-align: center;
            padding: 2rem 1rem;
        }
    </style>

    <script>
        (function () {
            const root = document.getElementById('support-chat-root');
            if (!root) return;

            const authId = Number(root.dataset.authId);
            const isAgent = root.dataset.isAgent === '1';
            const list = document.getElementById('support-conversation-list');
            const messagesEl = document.getElementById('support-messages');
            const titleEl = document.getElementById('support-thread-title');
            const form = document.getElementById('support-composer');
            const input = document.getElementById('support-input');
            const sendBtn = document.getElementById('support-send');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            let activeId = root.dataset.initialId ? Number(root.dataset.initialId) : null;
            let customerId = root.dataset.customerId ? Number(root.dataset.customerId) : null;
            let lastId = 0;
            let echoChannel = null;
            let lastAuthorKey = null;
            let lastDayKey = null;

            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));

            function startOfDay(date) {
                const d = new Date(date);
                d.setHours(0, 0, 0, 0);
                return d.getTime();
            }

            function humanizeDateLabel(iso) {
                if (!iso) return '';
                const date = new Date(iso);
                if (Number.isNaN(date.getTime())) return '';
                const today = startOfDay(new Date());
                const target = startOfDay(date);
                if (target === today) return 'Today';
                if (target === today - 86400000) return 'Yesterday';
                return date.toLocaleDateString(undefined, {
                    weekday: 'short',
                    month: 'short',
                    day: 'numeric',
                    year: date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric',
                });
            }

            // Teams-style: "Thu Sep 6 at 1:48pm"
            function humanizeStamp(iso) {
                if (!iso) return '';
                const date = new Date(iso);
                if (Number.isNaN(date.getTime())) return '';

                const diffSec = Math.round((Date.now() - date.getTime()) / 1000);
                if (diffSec >= 0 && diffSec < 45) return 'just now';
                if (diffSec >= 0 && diffSec < 3600) {
                    return Math.max(1, Math.round(diffSec / 60)) + 'm ago';
                }

                const weekday = date.toLocaleDateString(undefined, { weekday: 'short' });
                const month = date.toLocaleDateString(undefined, { month: 'short' });
                const day = date.getDate();
                let time = date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                time = time.replace(/\s/g, '').toLowerCase();
                return weekday + ' ' + month + ' ' + day + ' at ' + time;
            }

            function isSupportSender(message) {
                const uid = Number(message.user?.id);
                if (!uid) return false;
                if (customerId) return uid !== Number(customerId);
                return isAgent ? uid === authId : uid !== authId;
            }

            function displayName(message) {
                const uid = Number(message.user?.id);
                if (isSupportSender(message)) {
                    // Customers always see a single brand label.
                    if (!isAgent) return 'Live Support';
                    // Support / admin see the real staff name who replied.
                    if (uid === authId) return 'You';
                    return message.user?.name || 'Support';
                }
                if (uid === authId) return 'You';
                return message.user?.name || 'Customer';
            }

            function authorKey(message) {
                // Agents/admins group by actual person so consecutive staff don't collapse as one.
                if (isAgent) {
                    return 'user:' + (message.user?.id || 'unknown');
                }
                return isSupportSender(message) ? 'support' : ('user:' + (message.user?.id || 'unknown'));
            }

            function initials(message) {
                if (isSupportSender(message) && !isAgent) return 'LS';
                const name = message.user?.name || 'U';
                return String(name).trim().charAt(0).toUpperCase() || 'U';
            }

            function messageHtml(message, continueFromPrev) {
                const support = isSupportSender(message);
                const mine = Number(message.user?.id) === authId;
                const when = message.created_at ? humanizeStamp(message.created_at) : '';
                const classes = [
                    'ul-support-msg',
                    support ? 'is-support' : '',
                    mine ? 'is-mine' : '',
                    continueFromPrev ? 'is-continue' : '',
                ].filter(Boolean).join(' ');

                return `<article class="${classes}" data-message-id="${esc(message.id)}" data-created-at="${esc(message.created_at || '')}" data-author-key="${esc(authorKey(message))}">
                    <div class="ul-support-msg-avatar" aria-hidden="true">${esc(initials(message))}</div>
                    <div class="ul-support-msg-main">
                        <div class="ul-support-msg-head">
                            <span class="ul-support-msg-name">${esc(displayName(message))}</span>
                            <time class="ul-support-msg-time" datetime="${esc(message.created_at || '')}">${esc(when)}</time>
                        </div>
                        <div class="ul-support-msg-body">${esc(message.body)}</div>
                    </div>
                </article>`;
            }

            function appendMessages(rows, replace = false) {
                if (replace) {
                    messagesEl.innerHTML = '';
                    lastAuthorKey = null;
                    lastDayKey = null;
                }
                if (!rows.length && replace) {
                    messagesEl.innerHTML = '<div class="ul-support-empty">No messages yet. Say hello to start the conversation.</div>';
                    return;
                }
                const empty = messagesEl.querySelector('.ul-support-empty');
                if (empty) empty.remove();

                rows.forEach((row) => {
                    if (messagesEl.querySelector(`[data-message-id="${row.id}"]`)) return;

                    if (row.created_at) {
                        const dayKey = startOfDay(new Date(row.created_at));
                        if (dayKey !== lastDayKey) {
                            messagesEl.insertAdjacentHTML(
                                'beforeend',
                                `<div class="ul-support-day" role="separator"><span class="ul-support-day__badge">${esc(humanizeDateLabel(row.created_at))}</span></div>`
                            );
                            lastDayKey = dayKey;
                            lastAuthorKey = null;
                        }
                    }

                    const key = authorKey(row);
                    const cont = key === lastAuthorKey;
                    messagesEl.insertAdjacentHTML('beforeend', messageHtml(row, cont));
                    lastAuthorKey = key;
                    lastId = Math.max(lastId, Number(row.id) || 0);
                });
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }

            async function loadMessages(conversationId) {
                messagesEl.innerHTML = '<div class="ul-support-empty">Loading…</div>';
                lastId = 0;
                lastAuthorKey = null;
                lastDayKey = null;
                const res = await fetch(`/chats/support/${conversationId}/messages`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const json = await res.json();
                appendMessages(json.data || [], true);
                await fetch(`/chats/support/${conversationId}/read`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const item = list.querySelector(`[data-conversation-id="${conversationId}"]`);
                const unread = item?.querySelector('[data-unread]');
                if (unread) {
                    unread.textContent = '';
                    unread.classList.add('is-empty');
                }
            }

            function bumpConversationMeta(convId, body, createdAt) {
                const item = list.querySelector(`[data-conversation-id="${convId}"]`);
                if (!item) return;
                const meta = item.querySelector('[data-last-message]');
                if (meta && body) meta.textContent = body;
                const timeEl = item.querySelector('[data-last-at]');
                if (timeEl && createdAt) {
                    item.dataset.lastAt = createdAt;
                    timeEl.textContent = humanizeStamp(createdAt);
                }
                list.prepend(item);
            }

            function handleIncomingMessage(payload) {
                const message = payload?.message || payload;
                if (!message?.id) return;
                const convId = Number(message.conversation_id);
                if (!convId) return;

                const item = list.querySelector(`[data-conversation-id="${convId}"]`);
                if (item) {
                    bumpConversationMeta(convId, message.body, message.created_at);
                } else if (isAgent) {
                    location.reload();
                    return;
                }

                if (Number(activeId) === convId) {
                    appendMessages([{
                        id: message.id,
                        body: message.body,
                        created_at: message.created_at,
                        user: message.user,
                    }]);
                    fetch(`/chats/support/${convId}/read`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).catch(() => {});
                } else if (item) {
                    const unread = item.querySelector('[data-unread]');
                    if (unread) {
                        const next = (parseInt(unread.textContent || '0', 10) || 0) + 1;
                        unread.textContent = String(next);
                        unread.classList.remove('is-empty');
                    }
                }
            }

            function subscribe(conversationId) {
                if (!window.Echo) return;
                if (echoChannel) {
                    window.Echo.leave(`conversations.${echoChannel}`);
                }
                echoChannel = conversationId;
                window.Echo.private(`conversations.${conversationId}`)
                    .listen('.message.sent', handleIncomingMessage);
            }

            async function selectConversation(conversationId, title, nextCustomerId) {
                activeId = Number(conversationId);
                if (nextCustomerId) customerId = Number(nextCustomerId);
                titleEl.textContent = isAgent ? (title || 'Support') : 'Live Support';
                list.querySelectorAll('.ul-support-item').forEach((el) => {
                    el.classList.toggle('is-active', Number(el.dataset.conversationId) === activeId);
                });
                input.disabled = false;
                sendBtn.disabled = false;
                await loadMessages(activeId);
                subscribe(activeId);
                input.focus();
            }

            list.addEventListener('click', (event) => {
                const item = event.target.closest('.ul-support-item');
                if (!item) return;
                selectConversation(
                    item.dataset.conversationId,
                    item.dataset.title,
                    item.dataset.customerId
                );
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const body = (input.value || '').trim();
                if (!body || !activeId) return;
                input.value = '';
                sendBtn.disabled = true;
                try {
                    const res = await fetch(`/chats/support/${activeId}/messages`, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ body }),
                    });
                    const json = await res.json();
                    if (json.data) {
                        appendMessages([json.data]);
                        bumpConversationMeta(activeId, body, json.data.created_at || new Date().toISOString());
                    }
                } finally {
                    sendBtn.disabled = false;
                    input.focus();
                }
            });

            if (window.Echo) {
                window.Echo.private(`users.${authId}`)
                    .listen('.message.sent', handleIncomingMessage)
                    .listen('.conversation.updated', (payload) => {
                        const item = list.querySelector(`[data-conversation-id="${payload.conversation_id}"]`);
                        if (!item) {
                            if (isAgent && payload.conversation_id) {
                                location.reload();
                            }
                            return;
                        }
                        bumpConversationMeta(
                            payload.conversation_id,
                            payload.last_message,
                            payload.last_at
                        );
                        const unread = item.querySelector('[data-unread]');
                        if (unread && Number(payload.conversation_id) !== Number(activeId)) {
                            const count = Number(payload.unread_count || 0);
                            unread.textContent = count > 0 ? String(count) : '';
                            unread.classList.toggle('is-empty', count <= 0);
                        }
                    });
            }

            setInterval(async () => {
                if (!activeId || document.hidden) return;
                try {
                    const res = await fetch(`/chats/support/${activeId}/messages`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const json = await res.json();
                    const rows = (json.data || []).filter((row) => Number(row.id) > lastId);
                    if (rows.length) appendMessages(rows);
                } catch (e) {}
            }, 4000);

            setInterval(() => {
                messagesEl.querySelectorAll('.ul-support-msg[data-created-at]').forEach((el) => {
                    const iso = el.dataset.createdAt;
                    const timeEl = el.querySelector('time');
                    if (iso && timeEl) timeEl.textContent = humanizeStamp(iso);
                });
            }, 60000);

            if (activeId) {
                selectConversation(activeId, titleEl.textContent, customerId);
            } else if (!isAgent) {
                messagesEl.innerHTML = '<div class="ul-support-empty">Loading your support chat…</div>';
            } else {
                messagesEl.innerHTML = '<div class="ul-support-empty">Select a customer conversation from the left.</div>';
            }
        })();
    </script>
</x-app-layout>
