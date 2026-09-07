<x-app-layout>
    <x-slot name="title">Chat AI Testing</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.chat') }}", "text": "Chat test"}</x-slot>
    <x-slot name="active">Live assistant</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.dashboard') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-book"></i>Dashboard
        </a>
        <a href="{{ route('knowledge.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-file-earmark-text"></i>Documents
        </a>
    </x-slot>

    <div
        class="kb-chat"
        id="kb-chat"
        data-send="{{ route('knowledge.chat.send') }}"
        data-show-base="{{ url('/knowledge/chat') }}"
        data-csrf="{{ csrf_token() }}"
    >
        <aside class="kb-chat-side">
            <div class="kb-chat-side-head">
                <div>
                    <div class="kb-chat-side-title">Sessions</div>
                    <div class="kb-chat-side-sub">Staff test channel</div>
                </div>
                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-kb-new>
                    <i class="bi bi-plus-lg"></i>New
                </button>
            </div>
            <div class="kb-chat-threads" data-kb-threads>
                @forelse($conversations as $thread)
                    <button type="button" class="kb-chat-thread" data-kb-thread="{{ $thread['id'] }}">
                        <span class="kb-chat-thread-title">{{ $thread['title'] ?: 'New chat' }}</span>
                        <span class="kb-chat-thread-meta">
                            {{ $thread['last_message_at'] ? \Illuminate\Support\Carbon::parse($thread['last_message_at'])->timezone('Asia/Manila')->diffForHumans() : '—' }}
                        </span>
                    </button>
                @empty
                    <div class="kb-chat-threads-empty">No test chats yet.</div>
                @endforelse
            </div>
        </aside>

        <section class="box ul-card ai-layout kb-chat-main">
            <div class="ai-layout-head">
                <div class="ai-layout-brand">
                    <span class="ai-layout-orb is-live" aria-hidden="true"><i class="bi bi-stars"></i></span>
                    <div>
                        <div class="ai-layout-title">
                            ASELCO assistant
                            <x-unified.badge tone="slate" icon="bi-shield-lock">Test only</x-unified.badge>
                            <x-unified.badge tone="indigo" icon="bi-lightning">Live RAG</x-unified.badge>
                        </div>
                        <p class="ai-layout-kicker">
                            Same customer chat path: approved knowledge, then model reply. Does not create tickets or change bills.
                        </p>
                    </div>
                </div>
                <div class="ai-layout-head-side">
                    <div class="ai-layout-actions">
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-danger" data-kb-delete hidden>
                            <i class="bi bi-trash"></i>Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="kb-chat-stream" data-kb-stream>
                <div class="kb-chat-welcome" data-kb-welcome>
                    <span class="ai-layout-orb" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
                    <div class="ai-empty-title">Ask the live assistant</div>
                    <p>Try a member question. Citations and source appear on each reply so you can verify the knowledge base.</p>
                    <div class="kb-chat-chips">
                        @foreach($suggestions as $suggestion)
                            <button type="button" class="kb-chat-chip" data-kb-suggest="{{ $suggestion['message'] }}">
                                {{ $suggestion['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <form class="kb-chat-composer" data-kb-form>
                <textarea
                    class="ti-form-input ul-composer-skip"
                    name="message"
                    rows="2"
                    maxlength="2000"
                    placeholder="Type a customer question…"
                    data-kb-input
                    required
                ></textarea>
                <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view" data-kb-send>
                    <i class="bi bi-send"></i>Send
                </button>
            </form>
        </section>
    </div>

    <script>
    (function () {
        const root = document.getElementById('kb-chat');
        if (!root) return;

        const sendUrl = root.dataset.send;
        const showBase = root.dataset.showBase.replace(/\/$/, '');
        const csrf = root.dataset.csrf;
        const stream = root.querySelector('[data-kb-stream]');
        const welcome = root.querySelector('[data-kb-welcome]');
        const threads = root.querySelector('[data-kb-threads]');
        const form = root.querySelector('[data-kb-form]');
        const input = root.querySelector('[data-kb-input]');
        const sendBtn = root.querySelector('[data-kb-send]');
        const deleteBtn = root.querySelector('[data-kb-delete]');
        let conversationId = null;
        let busy = false;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const setActiveThread = (id) => {
            threads.querySelectorAll('[data-kb-thread]').forEach((el) => {
                el.classList.toggle('is-active', String(el.dataset.kbThread) === String(id || ''));
            });
            deleteBtn.hidden = !id;
        };

        const upsertThread = (id, title) => {
            let button = threads.querySelector('[data-kb-thread="' + id + '"]');
            if (!button) {
                const empty = threads.querySelector('.kb-chat-threads-empty');
                if (empty) empty.remove();
                button = document.createElement('button');
                button.type = 'button';
                button.className = 'kb-chat-thread';
                button.dataset.kbThread = String(id);
                button.innerHTML = '<span class="kb-chat-thread-title"></span><span class="kb-chat-thread-meta">Just now</span>';
                threads.prepend(button);
            }
            button.querySelector('.kb-chat-thread-title').textContent = title || 'New chat';
            button.querySelector('.kb-chat-thread-meta').textContent = 'Just now';
            setActiveThread(id);
        };

        const appendBubble = (role, content, meta) => {
            if (welcome) welcome.hidden = true;
            const wrap = document.createElement('div');
            wrap.className = 'kb-chat-msg is-' + role;
            const body = escapeHtml(content).replace(/\n/g, '<br>');
            let extra = '';
            if (meta) {
                const badges = [];
                if (meta.source) badges.push('<span class="ul-badge is-slate">' + escapeHtml(meta.source) + '</span>');
                if (meta.knowledge_sufficient) badges.push('<span class="ul-badge is-lime">Grounded</span>');
                else if (meta.knowledge_used) badges.push('<span class="ul-badge is-amber">Weak match</span>');
                if (meta.escalate) badges.push('<span class="ul-badge is-rose">Escalate</span>');
                if (meta.intent) badges.push('<span class="ul-badge is-indigo">' + escapeHtml(meta.intent) + '</span>');
                extra += '<div class="kb-chat-meta">' + badges.join('') + '</div>';
                if (Array.isArray(meta.citations) && meta.citations.length) {
                    extra += '<div class="kb-chat-cites">' + meta.citations.map((cite) => (
                        '<span class="kb-chat-cite"><strong>' + escapeHtml(cite.title || 'Source') + '</strong>'
                        + (cite.score != null ? ' · ' + Number(cite.score).toFixed(2) : '')
                        + (cite.excerpt ? '<em>' + escapeHtml(cite.excerpt) + '</em>' : '')
                        + '</span>'
                    )).join('') + '</div>';
                }
            }
            wrap.innerHTML = '<div class="kb-chat-bubble">' + body + extra + '</div>';
            stream.appendChild(wrap);
            stream.scrollTop = stream.scrollHeight;
        };

        const setTyping = (on) => {
            let row = stream.querySelector('[data-kb-typing]');
            if (!on) {
                if (row) row.remove();
                return;
            }
            if (row) return;
            row = document.createElement('div');
            row.className = 'kb-chat-msg is-assistant';
            row.dataset.kbTyping = '1';
            row.innerHTML = '<div class="kb-chat-bubble is-typing"><span></span><span></span><span></span></div>';
            stream.appendChild(row);
            stream.scrollTop = stream.scrollHeight;
        };

        const resetChat = () => {
            conversationId = null;
            setActiveThread(null);
            stream.querySelectorAll('.kb-chat-msg').forEach((el) => el.remove());
            if (welcome) welcome.hidden = false;
            input.value = '';
            input.focus();
        };

        const send = async (text) => {
            const message = String(text || '').trim();
            if (!message || busy) return;
            busy = true;
            sendBtn.disabled = true;
            if (welcome) welcome.hidden = true;
            appendBubble('user', message);
            input.value = '';
            setTyping(true);
            try {
                const res = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ message, conversation_id: conversationId }),
                });
                const data = await res.json();
                setTyping(false);
                if (!res.ok) throw new Error(data.message || 'The assistant could not reply.');
                conversationId = data.conversation_id;
                upsertThread(conversationId, message);
                appendBubble('assistant', data.reply || '—', data);
            } catch (error) {
                setTyping(false);
                appendBubble('assistant', error.message || 'Request failed.', { source: 'error', escalate: true });
            } finally {
                busy = false;
                sendBtn.disabled = false;
                input.focus();
            }
        };

        const loadThread = async (id) => {
            conversationId = id;
            setActiveThread(id);
            stream.querySelectorAll('.kb-chat-msg').forEach((el) => el.remove());
            if (welcome) welcome.hidden = true;
            setTyping(true);
            try {
                const res = await fetch(showBase + '/' + id, { headers: { Accept: 'application/json' } });
                const data = await res.json();
                setTyping(false);
                if (!res.ok) throw new Error(data.message || 'Unable to open chat.');
                (data.messages || []).forEach((msg) => {
                    appendBubble(msg.role === 'assistant' ? 'assistant' : 'user', msg.content, msg.role === 'assistant' ? {
                        intent: msg.intent,
                        escalate: msg.escalate,
                    } : null);
                });
            } catch (error) {
                setTyping(false);
                appendBubble('assistant', error.message || 'Unable to open chat.', { source: 'error' });
            }
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            send(input.value);
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                send(input.value);
            }
        });

        root.querySelectorAll('[data-kb-suggest]').forEach((chip) => {
            chip.addEventListener('click', () => send(chip.dataset.kbSuggest));
        });

        root.querySelector('[data-kb-new]').addEventListener('click', resetChat);

        threads.addEventListener('click', (event) => {
            const button = event.target.closest('[data-kb-thread]');
            if (button) loadThread(button.dataset.kbThread);
        });

        deleteBtn.addEventListener('click', async () => {
            if (!conversationId) return;
            await fetch(showBase + '/' + conversationId, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            });
            const button = threads.querySelector('[data-kb-thread="' + conversationId + '"]');
            if (button) button.remove();
            if (!threads.querySelector('[data-kb-thread]')) {
                threads.innerHTML = '<div class="kb-chat-threads-empty">No test chats yet.</div>';
            }
            resetChat();
        });
    })();
    </script>
</x-app-layout>
