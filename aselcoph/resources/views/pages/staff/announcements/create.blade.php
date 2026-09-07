<x-app-layout>
    @php
        $announcement = $announcement ?? null;
        $isEdit = $announcement !== null;
        $oldCategory = old('category', $announcement?->category ?? 'alert');
        $oldAudience = old('audience_type', $announcement?->audience_type ?? 'all');
        $categories = [
            'alert' => ['Alert', 'bi-exclamation-triangle', 'rose', 'General notice. Respects the member’s Alert preference.'],
            'service' => ['Service', 'bi-tools', 'lime', 'Outage, interruption, or field work. Uses the Service preference.'],
            'billing' => ['Billing', 'bi-receipt', 'amber', 'Bills, due dates, or payment reminders. Uses the Billing preference.'],
        ];
        $audiences = [
            'all' => ['All members', 'bi-people', 'sky', 'Verified mobile members. Common staff roles are excluded.'],
            'users' => ['Specific users', 'bi-person-check', 'indigo', 'Search and pick one or more portal accounts.'],
            'meter' => ['Meter numbers', 'bi-speedometer2', 'orange', 'For testing a consumer base by meter or account number.'],
        ];
        $meterSeed = old(
            'meter_numbers',
            $isEdit ? implode(',', $announcement->meter_numbers ?? []) : ''
        );
        $oldMeters = collect(preg_split('/[\s,;]+/', (string) $meterSeed) ?: [])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->values();
        $formAction = $isEdit
            ? route('announcements.update', $announcement)
            : route('announcements.store');
    @endphp

    <x-slot name="title">{{ $isEdit ? 'Edit Announcement' : 'New Announcement' }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('announcements.index') }}", "text": "Announcements"}</x-slot>
    <x-slot name="url_2">{"link": "{{ $isEdit ? route('announcements.edit', $announcement) : route('announcements.create') }}", "text": "{{ $isEdit ? 'Edit' : 'Create' }}"}</x-slot>
    <x-slot name="active">{{ $isEdit ? '#'.$announcement->id : 'Compose' }}</x-slot>
    <x-slot name="buttons">
        @if($isEdit)
            <a href="{{ route('announcements.show', $announcement) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
                <i class="bi bi-eye"></i>View
            </a>
        @endif
        <a href="{{ route('announcements.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Back to list
        </a>
    </x-slot>

    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ $formAction }}" id="announcement-form">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <div class="grid grid-cols-12 gap-6">
            <div class="xl:col-span-8 col-span-12 space-y-6">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="td-hero-title">
                            <div class="box-title ul-card-title mb-0">Message</div>
                            <div class="td-hero-badges">
                                <x-unified.badge tone="indigo" icon="bi-megaphone">Mobile inbox + push</x-unified.badge>
                            </div>
                        </div>
                    </div>
                    <div class="box-body space-y-4">
                        <p class="ul-intake-hint">Keep the title short. The message is what members see in the mobile notification inbox. Use a template from the right if you need a ready draft.</p>
                        <div class="ul-field">
                            <label class="ti-form-label" for="announcement-title">Title</label>
                            <input
                                id="announcement-title"
                                type="text"
                                name="title"
                                value="{{ old('title', $announcement?->title) }}"
                                maxlength="180"
                                class="ti-form-input"
                                required
                                placeholder="e.g. Scheduled interruption — Brgy. Sample"
                            >
                        </div>
                        <div class="ul-field">
                            <div class="flex items-center justify-between gap-2">
                                <label class="ti-form-label mb-0" for="announcement-body">Message</label>
                                <span class="ul-composer-count" id="announcement-body-count" aria-live="polite">0 / 5,000</span>
                            </div>
                            <textarea
                                id="announcement-body"
                                name="body"
                                rows="5"
                                class="ti-form-input ul-composer-skip"
                                required
                                maxlength="5000"
                                data-ul-max="5000"
                                placeholder="Short message shown in the mobile notification inbox"
                            >{{ old('body', $announcement?->body) }}</textarea>
                            <div class="text-xs text-textmuted">Maximum 5,000 characters.</div>
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title mb-0">Category</div>
                    </div>
                    <div class="box-body">
                        <p class="ul-intake-hint">This color and icon follow the member into the inbox. It also respects their mobile notification preference for that category.</p>
                        <div class="ul-intake-options ul-announce-audience">
                            @foreach($categories as $value => $meta)
                                <label class="ul-ast-mode">
                                    <input type="radio" name="category" value="{{ $value }}" required @checked($oldCategory === $value)>
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
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title mb-0">Audience</div>
                    </div>
                    <div class="box-body space-y-4">
                        <p class="ul-intake-hint">Choose who should receive this announcement, then confirm the count with Audience preview before you publish.</p>
                        <div class="ul-intake-options ul-announce-audience">
                            @foreach($audiences as $value => $meta)
                                <label class="ul-ast-mode">
                                    <input type="radio" name="audience_type" value="{{ $value }}" required @checked($oldAudience === $value)>
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

                        <div id="audience-users" class="ul-announce-panel" @if($oldAudience !== 'users') hidden @endif>
                            <div class="ul-field mb-3">
                                <label class="ti-form-label" for="audience-user-search">Search users</label>
                                <div class="ul-ast-form-search ul-intake-search ul-announce-user-search">
                                    <i class="bi bi-search" aria-hidden="true"></i>
                                    <input
                                        type="search"
                                        id="audience-user-search"
                                        class="ti-form-input"
                                        placeholder="Type at least 2 characters — name, email, or contact…"
                                        autocomplete="off"
                                        aria-label="Search users"
                                        aria-autocomplete="list"
                                        aria-controls="audience-user-hits"
                                    >
                                    <div id="audience-user-hits" class="ul-intake-hits hidden" role="listbox"></div>
                                </div>
                                <div class="text-xs text-textmuted mt-1">Results search the full member list. Pick people to add them below.</div>
                            </div>

                            <div class="flex items-center justify-between gap-2 mb-2">
                                <x-unified.chip icon="bi-check2" quiet>
                                    <span id="audience-user-selected-hint">0 selected</span>
                                </x-unified.chip>
                                <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel" id="audience-user-clear" hidden>
                                    <i class="bi bi-x-lg"></i>Clear all
                                </button>
                            </div>

                            <div class="ul-announce-selected" id="audience-user-selected">
                                @forelse($selectedUsers as $user)
                                    <div class="ul-announce-selected-item" data-user-id="{{ $user->id }}">
                                        <input type="hidden" name="audience_user_ids[]" value="{{ $user->id }}">
                                        <span class="ul-choice-mark ul-badge is-sky">
                                            <i class="bi bi-person" aria-hidden="true"></i>
                                        </span>
                                        <span class="ul-announce-user-copy">
                                            <span class="ul-announce-user-name">{{ $user->name }}</span>
                                            <span class="ul-announce-user-meta">{{ $user->email }}{{ $user->contact_no ? ' · '.$user->contact_no : '' }}</span>
                                        </span>
                                        <button type="button" class="ul-announce-selected-remove" aria-label="Remove {{ $user->name }}">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @empty
                                    <div class="ul-announce-user-empty" id="audience-user-empty">No users selected yet. Search above to add members.</div>
                                @endforelse
                            </div>
                        </div>

                        <div id="audience-meter" class="ul-announce-panel" @if($oldAudience !== 'meter') hidden @endif>
                            <div class="ul-field">
                                <label class="ti-form-label" for="meter_numbers_input">Meter or account numbers</label>
                                <input type="hidden" name="meter_numbers" id="meter_numbers" value="{{ $oldMeters->implode(',') }}">
                                <div class="ul-announce-tags" id="meter-tags" data-ul-meter-tags>
                                    <div class="ul-announce-tags-list" id="meter-tags-list">
                                        @foreach($oldMeters as $meter)
                                            <span class="ul-announce-tag" data-value="{{ $meter }}">
                                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                                <span>{{ $meter }}</span>
                                                <button type="button" class="ul-announce-tag-remove" aria-label="Remove {{ $meter }}">
                                                    <i class="bi bi-x" aria-hidden="true"></i>
                                                </button>
                                            </span>
                                        @endforeach
                                    </div>
                                    <input
                                        type="text"
                                        id="meter_numbers_input"
                                        class="ul-announce-tags-input"
                                        placeholder="{{ $oldMeters->isEmpty() ? 'Type a number, then press Space or Comma' : 'Add another…' }}"
                                        autocomplete="off"
                                        inputmode="text"
                                        aria-label="Add meter or account number"
                                    >
                                </div>
                                <div class="flex items-center justify-between gap-2 mt-2">
                                    <div class="text-xs text-textmuted">Press Space, Comma, or Enter to add. Backspace removes the last tag.</div>
                                    <x-unified.chip icon="bi-hash" quiet>
                                        <span id="meter-tags-count">{{ $oldMeters->count() }} tagged</span>
                                    </x-unified.chip>
                                </div>
                            </div>
                            <x-unified.note tone="amber" icon="bi-speedometer2" title="Meter targeting">
                                Use this for a test send. The lookup uses the CIS meter number first, then the account number, and only members already linked to a portal user are counted.
                            </x-unified.note>
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header flex flex-wrap items-center justify-between gap-2">
                        <div class="box-title ul-card-title mb-0">Audience preview</div>
                        <button type="button" class="ti-btn ti-btn-sm ul-btn ul-btn-more" id="btn-preview">
                            <i class="bi bi-arrow-repeat"></i>Refresh preview
                        </button>
                    </div>
                    <div class="box-body">
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <x-unified.badge tone="sky" icon="bi-people">
                                <span id="preview-count">—</span> member(s)
                            </x-unified.badge>
                            <span class="text-xs text-textmuted">Confirm this count before you publish.</span>
                        </div>
                        <ul id="preview-list" class="ul-announce-preview"></ul>
                    </div>
                </div>

                <div class="box ul-card ul-announce-actions-card">
                    <div class="box-body">
                        <div class="ul-announce-actions">
                            <button type="submit" name="action" value="draft" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                                <i class="bi bi-floppy"></i>{{ $isEdit ? 'Save changes' : 'Save draft' }}
                            </button>
                            <button
                                type="submit"
                                name="action"
                                value="publish"
                                class="ti-btn ti-btn-sm ul-btn ul-btn-view"
                                data-ul-confirm="Publish now and send push notifications to the audience."
                                data-ul-confirm-verb="Publish"
                                data-ul-confirm-icon="bi-send"
                            >
                                <i class="bi bi-send"></i>Publish &amp; push
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-4 col-span-12 space-y-6">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title mb-0">Templates</div>
                    </div>
                    <div class="box-body space-y-3">
                        <p class="ul-intake-hint mb-0">Select a template to fill the title, message, and category. Replace the [BRACKETS] before you send.</p>
                        <div class="ul-announce-templates" id="announcement-templates">
                            <button type="button" class="ul-announce-template is-active" data-template="">
                                <span class="ul-choice-mark ul-badge is-slate">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </span>
                                <span class="ul-announce-template-copy">
                                    <span class="ul-announce-template-title">Write your own</span>
                                    <span class="ul-announce-template-hint">Start with a blank title and message</span>
                                </span>
                            </button>
                            @foreach($templates as $template)
                                <button type="button" class="ul-announce-template" data-template="{{ $template['id'] }}">
                                    <span class="ul-choice-mark ul-badge is-{{ $template['tone'] }}">
                                        <i class="bi {{ $template['icon'] }}" aria-hidden="true"></i>
                                    </span>
                                    <span class="ul-announce-template-copy">
                                        <span class="ul-announce-template-title">{{ $template['label'] }}</span>
                                        <span class="ul-announce-template-hint">{{ $template['hint'] }}</span>
                                    </span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title mb-0">How to use</div>
                    </div>
                    <div class="box-body">
                        <ol class="ul-announce-steps">
                            <li>
                                <span class="ul-choice-mark ul-badge is-indigo">1</span>
                                <span>
                                    <strong>Pick a template or write the notice</strong>
                                    <span class="ul-announce-step-hint">Use a Templates pick, or write your own. Replace any [BRACKETS] before you send.</span>
                                </span>
                            </li>
                            <li>
                                <span class="ul-choice-mark ul-badge is-rose">2</span>
                                <span>
                                    <strong>Pick a category</strong>
                                    <span class="ul-announce-step-hint">Alert, Service, or Billing. This sets the color and whether the member’s preference allows it.</span>
                                </span>
                            </li>
                            <li>
                                <span class="ul-choice-mark ul-badge is-sky">3</span>
                                <span>
                                    <strong>Choose the audience</strong>
                                    <span class="ul-announce-step-hint">All members for a broadcast. Specific users to search and pick accounts. Meter numbers for a test group.</span>
                                </span>
                            </li>
                            <li>
                                <span class="ul-choice-mark ul-badge is-lime">4</span>
                                <span>
                                    <strong>Preview, then send</strong>
                                    <span class="ul-announce-step-hint">Refresh the audience count. Save draft to review later, or Publish &amp; push to send now.</span>
                                </span>
                            </li>
                        </ol>
                    </div>
                </div>

                <x-unified.note tone="sky" icon="bi-bell" title="What members receive">
                    A published announcement is stored in the in-app inbox and pushed to devices that have notifications enabled for that category.
                </x-unified.note>
                <x-unified.note tone="amber" icon="bi-shield-check" title="Draft vs publish">
                    Save draft does not notify anyone. Publish &amp; push cannot be undone, so confirm the preview count first.
                </x-unified.note>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const bodyField = document.getElementById('announcement-body');
            const bodyCount = document.getElementById('announcement-body-count');
            const bodyMax = Number(bodyField?.getAttribute('maxlength') || bodyField?.dataset.ulMax || 5000);

            const refreshBodyCount = () => {
                if (!bodyField || !bodyCount) {
                    return;
                }
                const used = Array.from(bodyField.value || '').length;
                bodyCount.textContent = `${used.toLocaleString()} / ${bodyMax.toLocaleString()}`;
                bodyCount.classList.toggle('is-warn', used >= bodyMax * 0.9 && used < bodyMax);
                bodyCount.classList.toggle('is-max', used >= bodyMax);
            };

            bodyField?.addEventListener('input', refreshBodyCount);
            refreshBodyCount();

            const titleField = document.getElementById('announcement-title');
            const templateButtons = document.querySelectorAll('#announcement-templates [data-template]');
            const templates = @json($templates);
            const templatesById = Object.fromEntries(templates.map((item) => [item.id, item]));

            const applyTemplate = (id) => {
                const template = id ? templatesById[id] : null;
                templateButtons.forEach((button) => {
                    button.classList.toggle('is-active', button.dataset.template === id);
                });
                if (!template) {
                    if (titleField) {
                        titleField.value = '';
                    }
                    if (bodyField) {
                        bodyField.value = '';
                        refreshBodyCount();
                    }
                    return;
                }
                if (titleField) {
                    titleField.value = template.title || '';
                }
                if (bodyField) {
                    bodyField.value = template.body || '';
                    refreshBodyCount();
                }
                const category = document.querySelector(`input[name="category"][value="${template.category}"]`);
                if (category) {
                    category.checked = true;
                    category.dispatchEvent(new Event('change', { bubbles: true }));
                }
            };

            templateButtons.forEach((button) => {
                button.addEventListener('click', () => applyTemplate(button.dataset.template || ''));
            });

            const usersBox = document.getElementById('audience-users');
            const meterBox = document.getElementById('audience-meter');
            const radios = document.querySelectorAll('input[name="audience_type"]');
            const previewCount = document.getElementById('preview-count');
            const previewList = document.getElementById('preview-list');
            const userSearch = document.getElementById('audience-user-search');
            const userHits = document.getElementById('audience-user-hits');
            const selectedBox = document.getElementById('audience-user-selected');
            const selectedHint = document.getElementById('audience-user-selected-hint');
            const clearSelected = document.getElementById('audience-user-clear');
            const searchUrl = @json(route('announcements.search-users'));
            let searchTimer;

            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[char]));

            function selectedIds() {
                return Array.from(selectedBox.querySelectorAll('input[name="audience_user_ids[]"]')).map((input) => String(input.value));
            }

            function refreshSelectedUi() {
                const ids = selectedIds();
                const empty = selectedBox.querySelector('#audience-user-empty');
                if (ids.length === 0) {
                    if (!empty) {
                        selectedBox.innerHTML = '<div class="ul-announce-user-empty" id="audience-user-empty">No users selected yet. Search above to add members.</div>';
                    }
                } else if (empty) {
                    empty.remove();
                }
                selectedHint.textContent = `${ids.length} selected`;
                clearSelected.hidden = ids.length === 0;
            }

            function addSelectedUser(user) {
                const id = String(user.id);
                if (selectedIds().includes(id)) {
                    return;
                }
                selectedBox.querySelector('#audience-user-empty')?.remove();
                const row = document.createElement('div');
                row.className = 'ul-announce-selected-item';
                row.dataset.userId = id;
                const meta = [user.email, user.contact_no].filter(Boolean).join(' · ');
                row.innerHTML =
                    `<input type="hidden" name="audience_user_ids[]" value="${esc(id)}">` +
                    `<span class="ul-choice-mark ul-badge is-sky"><i class="bi bi-person" aria-hidden="true"></i></span>` +
                    `<span class="ul-announce-user-copy"><span class="ul-announce-user-name">${esc(user.name)}</span><span class="ul-announce-user-meta">${esc(meta)}</span></span>` +
                    `<button type="button" class="ul-announce-selected-remove" aria-label="Remove ${esc(user.name)}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>`;
                selectedBox.appendChild(row);
                refreshSelectedUi();
                refreshPreview();
            }

            function hideHits() {
                userHits.classList.add('hidden');
                userHits.innerHTML = '';
            }

            userSearch.addEventListener('input', () => {
                clearTimeout(searchTimer);
                const q = (userSearch.value || '').trim();
                if (q.length < 2) {
                    hideHits();
                    return;
                }
                searchTimer = setTimeout(async () => {
                    try {
                        const res = await fetch(`${searchUrl}?q=${encodeURIComponent(q)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const json = await res.json();
                        const picked = new Set(selectedIds());
                        const rows = (json.data || []).filter((user) => !picked.has(String(user.id)));
                        if (!rows.length) {
                            userHits.innerHTML = '<div class="ul-intake-hit is-empty">No users found.</div>';
                        } else {
                            userHits.innerHTML = rows.map((user) => {
                                const meta = [user.email, user.contact_no].filter(Boolean).join(' · ');
                                return `<button type="button" class="ul-intake-hit" data-user-id="${esc(user.id)}">` +
                                    `<span class="ul-choice-mark ul-badge is-sky"><i class="bi bi-person" aria-hidden="true"></i></span>` +
                                    `<span class="ul-choice-copy"><span class="ul-choice-title">${esc(user.name)}</span><span class="ul-choice-hint">${esc(meta)}</span></span>` +
                                    `</button>`;
                            }).join('');
                            userHits.querySelectorAll('.ul-intake-hit[data-user-id]').forEach((btn, index) => {
                                btn.addEventListener('click', () => {
                                    addSelectedUser(rows[index]);
                                    userSearch.value = '';
                                    hideHits();
                                    userSearch.focus();
                                });
                            });
                        }
                        userHits.classList.remove('hidden');
                    } catch (error) {
                        userHits.innerHTML = '<div class="ul-intake-hit is-empty">Search failed. Try again.</div>';
                        userHits.classList.remove('hidden');
                    }
                }, 250);
            });

            userSearch.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideHits();
                }
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.ul-announce-user-search')) {
                    hideHits();
                }
            });

            selectedBox.addEventListener('click', (event) => {
                const remove = event.target.closest('.ul-announce-selected-remove');
                if (!remove) {
                    return;
                }
                remove.closest('.ul-announce-selected-item')?.remove();
                refreshSelectedUi();
                refreshPreview();
            });

            clearSelected.addEventListener('click', () => {
                selectedBox.innerHTML = '';
                refreshSelectedUi();
                refreshPreview();
            });

            refreshSelectedUi();

            const meterHidden = document.getElementById('meter_numbers');
            const meterInput = document.getElementById('meter_numbers_input');
            const meterTags = document.getElementById('meter-tags');
            const meterTagsList = document.getElementById('meter-tags-list');
            const meterTagsCount = document.getElementById('meter-tags-count');

            function syncAudiencePanels() {
                const selected = document.querySelector('input[name="audience_type"]:checked')?.value || 'all';
                usersBox.hidden = selected !== 'users';
                meterBox.hidden = selected !== 'meter';
                if (selected === 'users') {
                    userSearch.focus();
                }
                if (selected === 'meter') {
                    meterInput?.focus();
                }
            }

            radios.forEach((radio) => radio.addEventListener('change', syncAudiencePanels));

            function meterValues() {
                return Array.from(meterTagsList.querySelectorAll('.ul-announce-tag')).map((tag) => tag.dataset.value);
            }

            function syncMeterHidden() {
                const values = meterValues();
                meterHidden.value = values.join(',');
                meterTagsCount.textContent = `${values.length} tagged`;
                meterInput.placeholder = values.length ? 'Add another…' : 'Type a number, then press Space or Comma';
            }

            function addMeterTag(raw) {
                const parts = String(raw || '').split(/[\s,;]+/).map((part) => part.trim()).filter(Boolean);
                let added = false;
                const existing = new Set(meterValues().map((value) => value.toLowerCase()));
                parts.forEach((part) => {
                    if (existing.has(part.toLowerCase())) {
                        return;
                    }
                    existing.add(part.toLowerCase());
                    const tag = document.createElement('span');
                    tag.className = 'ul-announce-tag';
                    tag.dataset.value = part;
                    tag.innerHTML =
                        `<i class="bi bi-speedometer2" aria-hidden="true"></i>` +
                        `<span>${esc(part)}</span>` +
                        `<button type="button" class="ul-announce-tag-remove" aria-label="Remove ${esc(part)}"><i class="bi bi-x" aria-hidden="true"></i></button>`;
                    meterTagsList.appendChild(tag);
                    added = true;
                });
                if (added) {
                    syncMeterHidden();
                    refreshPreview();
                }
                return added;
            }

            function commitMeterInput() {
                const value = (meterInput.value || '').trim();
                if (!value) {
                    return;
                }
                addMeterTag(value);
                meterInput.value = '';
            }

            meterTags?.addEventListener('click', (event) => {
                const remove = event.target.closest('.ul-announce-tag-remove');
                if (remove) {
                    remove.closest('.ul-announce-tag')?.remove();
                    syncMeterHidden();
                    refreshPreview();
                    meterInput.focus();
                    return;
                }
                meterInput.focus();
            });

            meterInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ',' || event.key === ' ') {
                    event.preventDefault();
                    commitMeterInput();
                    return;
                }
                if (event.key === 'Backspace' && !(meterInput.value || '').length) {
                    const last = meterTagsList.querySelector('.ul-announce-tag:last-child');
                    if (last) {
                        last.remove();
                        syncMeterHidden();
                        refreshPreview();
                    }
                }
            });

            meterInput?.addEventListener('input', () => {
                const value = meterInput.value || '';
                if (/[\s,;]/.test(value)) {
                    addMeterTag(value);
                    meterInput.value = '';
                }
            });

            meterInput?.addEventListener('blur', commitMeterInput);

            meterInput?.addEventListener('paste', (event) => {
                const text = event.clipboardData?.getData('text') || '';
                if (!/[\s,;]/.test(text)) {
                    return;
                }
                event.preventDefault();
                addMeterTag(`${meterInput.value}${text}`);
                meterInput.value = '';
            });

            syncMeterHidden();
            syncAudiencePanels();

            async function refreshPreview() {
                const selected = document.querySelector('input[name="audience_type"]:checked')?.value || 'all';
                const formData = new FormData();
                formData.append('audience_type', selected);
                formData.append('_token', '{{ csrf_token() }}');

                if (selected === 'users') {
                    selectedIds().forEach((id) => formData.append('audience_user_ids[]', id));
                }
                if (selected === 'meter') {
                    formData.append('meter_numbers', meterHidden?.value || '');
                }

                previewCount.textContent = '…';
                previewList.innerHTML = '';

                try {
                    const res = await fetch('{{ route('announcements.preview') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData,
                    });
                    const data = await res.json();
                    previewCount.textContent = data.count ?? 0;
                    (data.preview || []).forEach((row) => {
                        const li = document.createElement('li');
                        li.className = 'ul-announce-preview-item';
                        li.innerHTML = `<span class="ul-choice-mark ul-badge is-sky"><i class="bi bi-person"></i></span><span class="ul-announce-user-copy"><span class="ul-announce-user-name">${esc(row.name)}</span><span class="ul-announce-user-meta">${esc(row.email)}</span></span>`;
                        previewList.appendChild(li);
                    });
                    if ((data.count || 0) > (data.preview || []).length) {
                        const more = document.createElement('li');
                        more.className = 'ul-announce-preview-more';
                        const rest = (data.count || 0) - (data.preview || []).length;
                        more.textContent = `+${rest.toLocaleString()} more member${rest === 1 ? '' : 's'}`;
                        previewList.appendChild(more);
                    }
                    if ((data.count || 0) === 0) {
                        previewList.innerHTML = '<li class="ul-announce-preview-more">No members matched this audience yet.</li>';
                    }
                } catch (e) {
                    previewCount.textContent = 'error';
                }
            }

            document.getElementById('btn-preview').addEventListener('click', refreshPreview);
            radios.forEach((radio) => radio.addEventListener('change', refreshPreview));
            refreshPreview();
        })();
    </script>
</x-app-layout>
