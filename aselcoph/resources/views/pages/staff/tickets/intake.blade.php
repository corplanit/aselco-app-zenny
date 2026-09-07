<x-app-layout>
    @php
        $oldCategory = (int) old('category_id');
        $oldChannel = old('channel', 'call');
        $oldPriority = old('priority', 'normal');
        $defaultAvatarUrl = asset('user.png');
        $presetPhotoUrl = $presetCustomer?->profile_photo_url ?: $defaultAvatarUrl;
        $channelOptions = [
            'call' => ['Call', 'bi-telephone', 'sky', 'Phone concern logged by CSR'],
            'text' => ['Text', 'bi-chat-text', 'teal', 'SMS or messenger text'],
            'social/sms' => ['Social / SMS', 'bi-share', 'violet', 'Facebook, social, or SMS'],
            'app' => ['App / Walk-in', 'bi-phone', 'lime', 'Filed in the app or at the desk'],
        ];
        $priorityOptions = [
            'low' => ['Low', 'bi-chevron-down', 'slate', 'Can wait in the queue'],
            'normal' => ['Normal', 'bi-dash', 'sky', 'Standard handling'],
            'high' => ['High', 'bi-chevron-up', 'orange', 'Needs faster attention'],
            'urgent' => ['Urgent', 'bi-exclamation-lg', 'rose', 'Route immediately'],
        ];
        $presetCustomerJson = $presetCustomer ? [
            'id' => $presetCustomer->id,
            'name' => $presetCustomer->name,
            'email' => $presetCustomer->email,
            'contact_no' => $presetCustomer->contact_no,
            'account_number' => null,
            'photo_url' => $presetPhotoUrl,
        ] : null;
    @endphp

    <x-slot name="title">CSR Intake</x-slot>
    <x-slot name="url_1">{"link": "/tickets", "text": "Tickets"}</x-slot>
    <x-slot name="url_2">{"link": "/tickets/intake", "text": "Intake"}</x-slot>
    <x-slot name="active">Log call / text / social</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('workspace.department') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-speedometer2"></i>Dashboard
        </a>
        <a href="{{ route('tickets.queue') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-ticket-detailed"></i>Ticket Queue
        </a>
    </x-slot>

    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}" enctype="multipart/form-data" id="intakeForm" class="ul-intake">
        @csrf
        <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}" required>

        <div class="grid grid-cols-12 gap-6">
            <div class="xl:col-span-8 col-span-12 space-y-6">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">Customer</div>
                    </div>
                    <div class="box-body">
                        <div class="ul-intake-search-row">
                            <div class="ul-ast-form-search ul-intake-search">
                                <i class="bi bi-search" aria-hidden="true"></i>
                                <input
                                    type="text"
                                    id="customerSearch"
                                    class="ti-form-input"
                                    placeholder="Search name, email, account no., or contact…"
                                    autocomplete="off"
                                >
                                <div id="customerDropdown" class="ul-intake-hits hidden"></div>
                            </div>
                        </div>

                        <div id="customerPicked" class="ul-ast-form-pick ul-intake-pick{{ $presetCustomer ? '' : ' is-empty' }}">
                            <img
                                id="pickPhoto"
                                class="ul-ast-form-photo"
                                src="{{ $presetPhotoUrl }}"
                                alt=""
                                onerror="this.onerror=null; this.src='{{ $defaultAvatarUrl }}';"
                            >
                            <div class="ul-ast-form-pick-copy">
                                <span id="pickName" class="ul-pick-name">{{ $presetCustomer?->name ?: 'Select a customer' }}</span>
                                <span id="pickEmail" class="ul-pick-meta">{{ $presetCustomer?->email ?: 'Search by name, email, account, or contact' }}</span>
                                <span id="pickContact" class="ul-pick-acct">{{ $presetCustomer?->contact_no ?: 'No contact selected' }}</span>
                            </div>
                            <span class="ul-ast-form-pick-amt">
                                <span class="ul-ast-form-pick-amt-label">Account</span>
                                <span id="pickAccount">—</span>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">Category</div>
                    </div>
                    <div class="box-body">
                        <p class="ul-intake-hint">Same six categories as the mobile app. The ticket auto-routes to that department.</p>
                        <div class="ul-intake-cats">
                            @foreach($categories as $category)
                                @php $dept = $category->department_code; @endphp
                                <label class="ul-intake-cat">
                                    <input type="radio" name="category_id" value="{{ $category->id }}" required @checked($oldCategory === (int) $category->id)>
                                    <span class="ul-intake-cat-card">
                                        <span class="ul-choice-mark ul-badge is-{{ \App\Support\TicketUi::departmentTone($dept) }}">
                                            <i class="bi {{ \App\Support\TicketUi::departmentIcon($dept) }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="ul-choice-copy">
                                            <span class="ul-choice-title">{{ \App\Support\TicketUi::categoryLabel($category) }}</span>
                                            <span class="ul-choice-hint">{{ $dept }} · SLA {{ $category->sla_minutes }} min</span>
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">Concern details</div>
                    </div>
                    <div class="box-body space-y-4">
                        <div>
                            <label class="ti-form-label required">Channel</label>
                            <div class="ul-intake-options">
                                @foreach($channelOptions as $channel => $meta)
                                    <label class="ul-ast-mode">
                                        <input type="radio" name="channel" value="{{ $channel }}" required @checked($oldChannel === $channel)>
                                        <span class="ul-choice-mark ul-badge is-{{ $meta[2] }}">
                                            <i class="bi {{ $meta[1] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="ul-ast-mode-copy">
                                            <span class="ul-ast-mode-title">{{ $meta[0] }}</span>
                                            <span class="ul-ast-mode-hint">{{ $meta[3] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="ti-form-label">Priority</label>
                            <div class="ul-intake-options">
                                @foreach($priorityOptions as $priority => $meta)
                                    <label class="ul-ast-mode">
                                        <input type="radio" name="priority" value="{{ $priority }}" @checked($oldPriority === $priority)>
                                        <span class="ul-choice-mark ul-badge is-{{ $meta[2] }}">
                                            <i class="bi {{ $meta[1] }}" aria-hidden="true"></i>
                                        </span>
                                        <span class="ul-ast-mode-copy">
                                            <span class="ul-ast-mode-title">{{ $meta[0] }}</span>
                                            <span class="ul-ast-mode-hint">{{ $meta[3] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="ti-form-label" for="subcategory">Subcategory <span class="ul-intake-optional">optional</span></label>
                            <input type="text" id="subcategory" name="subcategory" value="{{ old('subcategory') }}" class="ti-form-input" maxlength="160" placeholder="Feeder, barangay, bill month…">
                        </div>

                        <div>
                            <label class="ti-form-label required" for="description">Description</label>
                            <textarea id="description" name="description" class="ti-form-input" rows="5" required maxlength="5000" placeholder="What did the customer report? Include location, account details, and what they already tried.">{{ old('description') }}</textarea>
                        </div>

                        <div>
                            <label class="ti-form-label">Evidence <span class="ul-intake-optional">optional</span></label>
                            <x-unified.dropzone name="attachment" />
                        </div>
                    </div>
                    <div class="ul-card-footer ul-intake-submit">
                        <p>Creates the ticket and auto-endorses it to the owning department.</p>
                        <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-primary" id="intakeSubmit">
                            <i class="bi bi-send"></i>Create &amp; route ticket
                        </button>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 col-span-12">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">How it routes</div>
                    </div>
                    <div class="box-body">
                        <div class="ul-intake-routes">
                            @foreach($categories as $category)
                                @php
                                    $dept = $category->department_code;
                                    $guide = \App\Support\TicketUi::departmentRouteGuide($dept, $category->sla_minutes);
                                    $flowOpen = in_array($dept, ['TSD', 'COMD', 'FOCAL'], true);
                                    $flowId = 'intake-flow-'.$category->id;
                                @endphp
                                <article class="ul-intake-route{{ $flowOpen ? ' is-open' : '' }}" data-category="{{ $category->id }}">
                                    <button
                                        type="button"
                                        class="ul-intake-route-toggle"
                                        aria-expanded="{{ $flowOpen ? 'true' : 'false' }}"
                                        aria-controls="{{ $flowId }}"
                                    >
                                        <span class="ul-intake-route-head">
                                            <span class="ul-choice-mark ul-badge is-{{ \App\Support\TicketUi::departmentTone($dept) }}">
                                                <i class="bi {{ \App\Support\TicketUi::departmentIcon($dept) }}" aria-hidden="true"></i>
                                            </span>
                                            <span class="ul-choice-copy">
                                                <span class="ul-choice-title">{{ $dept }}</span>
                                                <span class="ul-choice-hint">{{ \App\Support\TicketUi::categoryLabel($category) }}</span>
                                            </span>
                                            <i class="bi bi-chevron-down ul-intake-route-chevron" aria-hidden="true"></i>
                                        </span>
                                    </button>
                                    <p class="ul-intake-route-explain">{{ $guide['explain'] }}</p>
                                    <ol class="ul-intake-flow" id="{{ $flowId }}" @unless($flowOpen) hidden @endunless>
                                        @foreach($guide['flow'] as $node)
                                            <li class="ul-intake-flow-step">
                                                @if(($node['kind'] ?? '') === 'split')
                                                    <div class="ul-intake-flow-split">
                                                        @foreach($node['branches'] as $branch)
                                                            <span class="ul-intake-flow-node is-{{ $branch['kind'] }}">
                                                                {{ $branch['label'] }}
                                                                @if(! empty($branch['hint']))
                                                                    <small>{{ $branch['hint'] }}</small>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <span class="ul-intake-flow-node is-{{ $node['kind'] }}">
                                                        @if(($node['kind'] ?? '') === 'decision')
                                                            <i class="bi bi-diamond" aria-hidden="true"></i>
                                                        @endif
                                                        {{ $node['label'] }}
                                                    </span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </article>
                            @endforeach
                        </div>
                        <p class="ul-intake-foot">If the customer still needs help after verification, the ticket reopens and routes again. Overdue tickets can escalate to AREA-ADMIN.</p>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    (function () {
        const search = document.getElementById('customerSearch');
        const dropdown = document.getElementById('customerDropdown');
        const customerId = document.getElementById('customer_id');
        const picked = document.getElementById('customerPicked');
        const pickPhoto = document.getElementById('pickPhoto');
        const pickName = document.getElementById('pickName');
        const pickEmail = document.getElementById('pickEmail');
        const pickContact = document.getElementById('pickContact');
        const pickAccount = document.getElementById('pickAccount');
        const searchUrl = @json(route('tickets.customer-search'));
        const defaultAvatar = @json($defaultAvatarUrl);
        const presetCustomer = @json($presetCustomerJson);
        let timer;

        function customerMeta(c) {
            return [c.email, c.account_number, c.contact_no].filter(Boolean).join(' · ') || 'No contact on file';
        }

        function selectCustomer(c) {
            customerId.value = c.id;
            pickName.textContent = c.name || 'Selected customer';
            pickEmail.textContent = c.email || 'No email on file';
            pickContact.textContent = c.contact_no || 'No contact on file';
            pickAccount.textContent = c.account_number || '—';
            pickPhoto.src = c.photo_url || defaultAvatar;
            picked.classList.remove('is-empty');
            search.value = c.name || '';
            dropdown.classList.add('hidden');
        }

        if (presetCustomer) {
            selectCustomer(presetCustomer);
        }

        function syncRouteGuide() {
            const selected = document.querySelector('input[name="category_id"]:checked');
            document.querySelectorAll('.ul-intake-route').forEach((card) => {
                card.classList.toggle('is-active', Boolean(selected && card.dataset.category === selected.value));
            });
        }
        document.querySelectorAll('input[name="category_id"]').forEach((input) => {
            input.addEventListener('change', syncRouteGuide);
        });
        syncRouteGuide();

        document.querySelectorAll('.ul-intake-route-toggle').forEach((btn) => {
            btn.addEventListener('click', () => {
                const card = btn.closest('.ul-intake-route');
                const flow = card?.querySelector('.ul-intake-flow');
                if (!card || !flow) {
                    return;
                }
                const open = !card.classList.contains('is-open');
                card.classList.toggle('is-open', open);
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                flow.hidden = !open;
            });
        });

        search.addEventListener('input', function () {
            clearTimeout(timer);
            const q = this.value.trim();
            if (q.length < 2) {
                dropdown.classList.add('hidden');
                return;
            }
            timer = setTimeout(async () => {
                const res = await fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const json = await res.json();
                if (!json.data?.length) {
                    dropdown.innerHTML = '<div class="ul-intake-hit is-empty">No customers found.</div>';
                } else {
                    dropdown.innerHTML = json.data.map((c) => {
                        const payload = JSON.stringify(c).replace(/'/g, '&#39;');
                        return '<button type="button" class="ul-intake-hit" data-json=\'' + payload + '\'>' +
                            '<img class="ul-intake-hit-photo" src="" alt="">' +
                            '<span class="ul-choice-copy"><span class="ul-choice-title"></span><span class="ul-choice-hint"></span></span>' +
                            '</button>';
                    }).join('');
                    dropdown.querySelectorAll('.ul-intake-hit[data-json]').forEach((row, index) => {
                        const c = json.data[index];
                        const photo = row.querySelector('.ul-intake-hit-photo');
                        photo.src = c.photo_url || defaultAvatar;
                        photo.onerror = function () { this.onerror = null; this.src = defaultAvatar; };
                        row.querySelector('.ul-choice-title').textContent = c.name;
                        row.querySelector('.ul-choice-hint').textContent = customerMeta(c);
                    });
                }
                dropdown.classList.remove('hidden');
            }, 280);
        });

        dropdown.addEventListener('click', (e) => {
            const row = e.target.closest('[data-json]');
            if (!row) {
                return;
            }
            selectCustomer(JSON.parse(row.dataset.json.replace(/&#39;/g, "'")));
        });

        document.getElementById('intakeForm').addEventListener('submit', function (e) {
            if (!customerId.value) {
                e.preventDefault();
                window.ulConfirm?.({
                    title: 'Select a customer',
                    text: 'Search an existing account first.',
                    verb: 'OK',
                    icon: 'bi-person',
                });
                return;
            }
            const btn = document.getElementById('intakeSubmit');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i>Routing…';
        });
    })();
    </script>
    @endpush
</x-app-layout>
