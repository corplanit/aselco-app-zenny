@auth
    @if(Auth::user()->canManageTickets())
        <li class="header-element ti-dropdown hs-dropdown" id="ticket-notif-wrap">
            <a href="javascript:void(0);" class="header-link relative" id="ticketNotifToggle" title="Ticket notifications">
                <i class="bi bi-bell text-xl header-link-icon"></i>
                <span id="ticketNotifBadge"
                      class="absolute -top-0.5 -end-0.5 translate-middle badge !rounded-full bg-danger text-[10px]"
                      style="display:none">0</span>
            </a>
            <div id="ticketNotifPanel"
                 class="hidden absolute end-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-bodybg shadow-lg rounded-lg border border-defaultborder z-50">
                <div class="flex items-center justify-between px-3 py-2 border-b">
                    <span class="font-semibold text-sm">Notifications</span>
                    <button type="button" class="text-xs text-primary" id="ticketNotifMarkAll">Mark all read</button>
                </div>
                <div id="ticketNotifList" class="max-h-96 overflow-y-auto text-sm">
                    <div class="px-3 py-4 text-textmuted">Loading…</div>
                </div>
            </div>
        </li>
        <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const badge = document.getElementById('ticketNotifBadge');
            const panel = document.getElementById('ticketNotifPanel');
            const list = document.getElementById('ticketNotifList');
            const toggle = document.getElementById('ticketNotifToggle');

            async function refresh() {
                try {
                    const res = await fetch('{{ url('/tickets/notifications') }}', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                    });
                    if (!res.ok) return;
                    const json = await res.json();
                    const n = json.unread_count || 0;
                    if (n > 0) {
                        badge.style.display = '';
                        badge.textContent = n > 9 ? '9+' : String(n);
                    } else {
                        badge.style.display = 'none';
                    }
                    if (!json.data?.length) {
                        list.innerHTML = '<div class="px-3 py-4 text-textmuted">No notifications.</div>';
                        return;
                    }
                    list.innerHTML = json.data.map(item => `
                        <a href="${item.ticket_url || '#'}"
                           class="block px-3 py-2 border-b hover:bg-gray-50 dark:hover:bg-white/5 ${item.unread ? 'bg-primary/5' : ''}"
                           data-id="${item.id}">
                            <div class="font-medium">${item.title}</div>
                            <div class="text-xs text-textmuted">${item.body || ''}</div>
                            <div class="text-[11px] text-textmuted mt-1">${item.created_at}</div>
                        </a>`).join('');
                } catch (e) {}
            }

            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) refresh();
            });
            document.addEventListener('click', (e) => {
                if (!document.getElementById('ticket-notif-wrap').contains(e.target)) {
                    panel.classList.add('hidden');
                }
            });
            list.addEventListener('click', async (e) => {
                const a = e.target.closest('[data-id]');
                if (!a) return;
                await fetch(`/tickets/notifications/${a.dataset.id}/read`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
                });
            });
            document.getElementById('ticketNotifMarkAll').addEventListener('click', async (e) => {
                e.preventDefault();
                await fetch('/tickets/notifications/read-all', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
                });
                refresh();
            });
            refresh();
            setInterval(refresh, 30000);
        })();
        </script>
    @endif
@endauth
