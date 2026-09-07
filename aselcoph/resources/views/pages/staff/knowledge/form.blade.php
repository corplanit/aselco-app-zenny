<x-app-layout>
    @php
        $isEdit = (bool) $document;
        $status = old('status', $document?->status ?? 'active');
        $categoryId = (int) old('category_id', $document?->category_id);
        $selectedCategory = $categories->firstWhere('id', $categoryId) ?? $document?->category;
        $categoryTone = \App\Support\KnowledgeUi::categoryTone($selectedCategory);
        $categoryIcon = \App\Support\KnowledgeUi::categoryIcon($selectedCategory);
        $statusTone = \App\Support\KnowledgeUi::statusTone($status);
        $statusIcon = \App\Support\KnowledgeUi::statusIcon($status);
        $backUrl = $isEdit ? route('knowledge.show', $document->id) : route('knowledge.index');
        $backLabel = $isEdit ? 'Document' : 'Documents';
        $charCount = mb_strlen(trim((string) $body));
        $departments = \App\Support\TicketUi::departmentMeanings();
        $departments['CSR'] = 'Customer Service — member assistance and FAQ';
        $currentDept = (string) old('department', $document?->department);
        if ($currentDept !== '' && ! array_key_exists($currentDept, $departments)) {
            $departments[$currentDept] = $currentDept;
        }
        $serviceTypes = \App\Support\KnowledgeUi::serviceTypes();
        $currentService = (string) old('service_type', $document?->service_type);
        if ($currentService !== '' && ! array_key_exists($currentService, $serviceTypes)) {
            $serviceTypes[$currentService] = [
                'label' => ucfirst($currentService),
                'tone' => \App\Support\KnowledgeUi::toneFromKey($currentService),
                'icon' => 'bi-tag',
            ];
        }
        $sources = \App\Support\KnowledgeUi::sources();
        $currentSource = (string) old('source', $document?->source);
        if ($currentSource !== '' && ! array_key_exists($currentSource, $sources)) {
            $sources[$currentSource] = [
                'tone' => 'indigo',
                'icon' => \App\Support\KnowledgeUi::sourceIcon($currentSource),
            ];
        }
        $effectiveRaw = old('effective_at', optional($document?->effective_at)->format('Y-m-d\TH:i'));
        $expiresRaw = old('expires_at', optional($document?->expires_at)->format('Y-m-d\TH:i'));
        $effectiveDate = $effectiveRaw ? substr((string) $effectiveRaw, 0, 10) : '';
        $expiresDate = $expiresRaw ? substr((string) $expiresRaw, 0, 10) : '';
        $dateType = match (true) {
            $effectiveDate !== '' && $expiresDate !== '' => 'range',
            $effectiveDate !== '' => 'start',
            $expiresDate !== '' => 'end',
            default => 'always',
        };
    @endphp

    <x-slot name="title">{{ $isEdit ? $document->title : 'Upload knowledge' }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.index') }}", "text": "Documents"}</x-slot>
    <x-slot name="active">{{ $isEdit ? 'Edit' : 'Create' }}</x-slot>
    <x-slot name="buttons">
        <a href="{{ $backUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>{{ $backLabel }}
        </a>
        @if($isEdit)
            <a href="{{ route('knowledge.show', $document->id) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-eye"></i>View
            </a>
        @endif
        <a href="{{ route('knowledge.chat') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-chat-dots"></i>Chat AI Testing
        </a>
    </x-slot>

    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form
        class="ai-dash kb-dash kb-doc kb-doc-form"
        id="kb-document-form"
        method="POST"
        action="{{ $isEdit ? route('knowledge.update', $document->id) : route('knowledge.store') }}"
        enctype="multipart/form-data"
    >
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="box ul-card ai-layout">
            <div class="ai-layout-head">
                <div class="ai-layout-brand">
                    <span class="ai-layout-orb" aria-hidden="true">
                        <i class="bi {{ $isEdit ? 'bi-pencil' : 'bi-cloud-upload' }}"></i>
                    </span>
                    <div>
                        <div class="ai-layout-title">
                            {{ $isEdit ? 'Edit document' : 'Upload knowledge' }}
                            @if($isEdit)
                                <x-unified.badge tone="indigo" icon="bi-layers">v{{ $document->current_version }}</x-unified.badge>
                                <x-unified.badge :tone="$statusTone" :icon="$statusIcon">{{ ucfirst($status) }}</x-unified.badge>
                                @if($selectedCategory)
                                    <x-unified.badge :tone="$categoryTone" :icon="$categoryIcon">{{ $selectedCategory->name }}</x-unified.badge>
                                @endif
                            @else
                                <x-unified.badge tone="slate" icon="bi-shield-lock">Staff approved only</x-unified.badge>
                            @endif
                        </div>
                        <p class="ai-layout-kicker">
                            Only staff-approved content belongs here. Saving creates a new version and re-indexes chunks for retrieval.
                            Customer chat is never imported as knowledge.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="kb-doc-grid">
            <section class="box ul-card kb-doc-main">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title">
                        Approved body
                        <span class="ul-card-count" data-kb-chars>{{ number_format($charCount) }} characters</span>
                    </div>
                </div>
                <div class="box-body kb-doc-body-wrap">
                    <div class="kb-doc-reader">
                        <div class="kb-doc-reader-bar">
                            <div class="kb-doc-switch" role="tablist" aria-label="Editor view">
                                <button type="button" class="is-active" role="tab" data-kb-view="write" aria-selected="true">
                                    <i class="bi bi-pencil"></i>Write
                                </button>
                                <button type="button" role="tab" data-kb-view="preview" aria-selected="false">
                                    <i class="bi bi-eye"></i>Preview
                                </button>
                            </div>
                            <span class="kb-doc-reader-hint" data-kb-hint>Markdown source</span>
                        </div>
                        <div class="kb-doc-reader-pane is-editor" data-kb-scroll>
                            <div class="kb-doc-code" data-kb-panel="write">
                                <div class="kb-doc-gutter" data-kb-gutter aria-hidden="true"></div>
                                <textarea
                                    name="body"
                                    id="kb-body"
                                    class="kb-doc-editor ul-composer-skip"
                                    maxlength="100000"
                                    wrap="off"
                                    spellcheck="false"
                                    placeholder="Paste approved policy or FAQ text…"
                                >{{ $body }}</textarea>
                            </div>
                            <article class="kb-doc-prose" data-kb-panel="preview" hidden></article>
                        </div>
                    </div>
                    <label class="kb-doc-file">
                        <input type="file" name="file" accept=".txt,.md,.html,.htm,.csv">
                        <span class="kb-doc-file-icon"><i class="bi bi-paperclip"></i></span>
                        <span class="kb-doc-file-copy">
                            <strong>Or upload a file</strong>
                            <span>.txt, .md, .html, or .csv — replaces the body when you save</span>
                        </span>
                        <span class="kb-doc-file-name" data-kb-file>No file chosen</span>
                    </label>
                </div>
            </section>

            <aside class="kb-doc-side">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">Details</div>
                    </div>
                    <div class="box-body kb-doc-fields">
                        <div class="ul-field">
                            <label class="ti-form-label required" for="kb-title">Title</label>
                            <input id="kb-title" type="text" name="title" class="ti-form-input" required maxlength="200"
                                   value="{{ old('title', $document?->title) }}">
                        </div>

                        <div class="kb-doc-group">
                            <div class="kb-doc-group-head">
                                <span>Classification</span>
                                <a href="{{ route('knowledge.categories') }}" class="kb-doc-group-link">
                                    <i class="bi bi-folder2"></i>Categories
                                </a>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label required" for="kb-category">Category</label>
                                <select id="kb-category" name="category_id" class="ti-form-select" required>
                                    @foreach($categories as $category)
                                        <option
                                            value="{{ $category->id }}"
                                            @selected($categoryId === $category->id)
                                            data-tone="{{ \App\Support\KnowledgeUi::categoryTone($category) }}"
                                            data-icon="{{ \App\Support\KnowledgeUi::categoryIcon($category) }}"
                                            data-department="{{ \App\Support\KnowledgeUi::suggestedDepartment($category) }}"
                                        >{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label required" for="kb-status">Status</label>
                                <select id="kb-status" name="status" class="ti-form-select" required>
                                    @foreach(['active','inactive','draft'] as $option)
                                        <option
                                            value="{{ $option }}"
                                            @selected($status === $option)
                                            data-tone="{{ \App\Support\KnowledgeUi::statusTone($option) }}"
                                            data-icon="{{ \App\Support\KnowledgeUi::statusIcon($option) }}"
                                        >{{ ucfirst($option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="kb-doc-group">
                            <div class="kb-doc-group-head">
                                <span>Routing</span>
                                <span class="kb-doc-group-note">Linked to category</span>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label" for="kb-department">Department</label>
                                <select id="kb-department" name="department" class="ti-form-select">
                                    <option value="" data-tone="slate" data-icon="bi-dash-circle">Any department</option>
                                    @foreach($departments as $code => $meaning)
                                        <option
                                            value="{{ $code }}"
                                            @selected($currentDept === $code)
                                            data-tone="{{ $code === 'CSR' ? 'sky' : \App\Support\TicketUi::departmentTone($code) }}"
                                            data-icon="{{ $code === 'CSR' ? 'bi-headset' : \App\Support\TicketUi::departmentIcon($code) }}"
                                        >{{ $code }} ({{ $meaning }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label" for="kb-service">Service type</label>
                                <select id="kb-service" name="service_type" class="ti-form-select">
                                    <option value="" data-tone="slate" data-icon="bi-dash-circle">Any service</option>
                                    @foreach($serviceTypes as $value => $meta)
                                        <option
                                            value="{{ $value }}"
                                            @selected($currentService === $value)
                                            data-tone="{{ $meta['tone'] }}"
                                            data-icon="{{ $meta['icon'] }}"
                                        >{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ul-field">
                                <label class="ti-form-label" for="kb-source">Source</label>
                                <select id="kb-source" name="source" class="ti-form-select">
                                    <option value="" data-tone="slate" data-icon="bi-dash-circle">Not specified</option>
                                    @foreach($sources as $value => $meta)
                                        <option
                                            value="{{ $value }}"
                                            @selected($currentSource === $value)
                                            data-tone="{{ $meta['tone'] }}"
                                            data-icon="{{ $meta['icon'] }}"
                                        >{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="kb-doc-group kb-doc-dates">
                            <div class="kb-doc-group-head">
                                <span>Availability</span>
                            </div>
                            <div class="ul-field">
                                <span class="ti-form-label">Date type</span>
                                <div class="kb-doc-date-types" role="radiogroup" aria-label="Date type">
                                    @foreach([
                                        'always' => ['Always', 'bi-infinity'],
                                        'start' => ['Starts', 'bi-calendar-event'],
                                        'end' => ['Ends', 'bi-calendar-x'],
                                        'range' => ['Range', 'bi-calendar-week'],
                                    ] as $type => $meta)
                                        <label class="kb-doc-date-type">
                                            <input type="radio" name="availability" value="{{ $type }}" @checked($dateType === $type)>
                                            <span><i class="bi {{ $meta[1] }}"></i>{{ $meta[0] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="kb-doc-date-row">
                                <div class="ul-field kb-doc-date-start">
                                    <label class="ti-form-label" for="kb-effective-date">Effective</label>
                                    <input id="kb-effective-date" type="date" name="effective_date" class="ti-form-input" value="{{ $effectiveDate }}">
                                </div>
                                <div class="ul-field kb-doc-date-end">
                                    <label class="ti-form-label" for="kb-expires-date">Expires</label>
                                    <input id="kb-expires-date" type="date" name="expires_date" class="ti-form-input" value="{{ $expiresDate }}">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="ul-card-footer pf-actions">
                        <a href="{{ $backUrl }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">Cancel</a>
                        <button type="submit" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
                            <i class="bi {{ $isEdit ? 'bi-check2' : 'bi-cloud-upload' }}"></i>
                            {{ $isEdit ? 'Save new version' : 'Create & index' }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <script>
        (function () {
            const root = document.getElementById('kb-document-form');
            if (!root) return;
            const textarea = root.querySelector('#kb-body');
            const preview = root.querySelector('[data-kb-panel="preview"]');
            const tabs = root.querySelectorAll('[data-kb-view]');
            const panels = root.querySelectorAll('[data-kb-panel]');
            const hint = root.querySelector('[data-kb-hint]');
            const scroller = root.querySelector('[data-kb-scroll]');
            const chars = root.querySelector('[data-kb-chars]');
            const fileInput = root.querySelector('input[name="file"]');
            const fileName = root.querySelector('[data-kb-file]');
            const gutter = root.querySelector('[data-kb-gutter]');
            const labels = { write: 'Markdown source', preview: 'Rendered markdown' };

            const escapeHtml = (value) => String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            const renderMarkdown = (raw) => {
                const text = String(raw || '').replace(/\r\n/g, '\n');
                if (!text.trim()) {
                    return '<p class="kb-doc-empty">Nothing to preview yet.</p>';
                }
                const lines = text.split('\n');
                const out = [];
                let list = null;
                const closeList = () => {
                    if (list) {
                        out.push(list === 'ul' ? '</ul>' : '</ol>');
                        list = null;
                    }
                };
                const inline = (value) => escapeHtml(value)
                    .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                    .replace(/__(.+?)__/g, '<strong>$1</strong>')
                    .replace(/(^|[^*])\*(?!\s)(.+?)\*(?!\*)/g, '$1<em>$2</em>')
                    .replace(/`([^`]+)`/g, '<code>$1</code>');

                lines.forEach((line) => {
                    const heading = line.match(/^(#{1,6})\s+(.*)$/);
                    const ul = line.match(/^\s*[-*+]\s+(.*)$/);
                    const ol = line.match(/^\s*\d+\.\s+(.*)$/);
                    if (heading) {
                        closeList();
                        const level = heading[1].length;
                        out.push('<h' + level + '>' + inline(heading[2]) + '</h' + level + '>');
                        return;
                    }
                    if (line.trim() === '---' || line.trim() === '***') {
                        closeList();
                        out.push('<hr>');
                        return;
                    }
                    if (ul) {
                        if (list !== 'ul') { closeList(); out.push('<ul>'); list = 'ul'; }
                        out.push('<li>' + inline(ul[1]) + '</li>');
                        return;
                    }
                    if (ol) {
                        if (list !== 'ol') { closeList(); out.push('<ol>'); list = 'ol'; }
                        out.push('<li>' + inline(ol[1]) + '</li>');
                        return;
                    }
                    closeList();
                    if (line.trim() === '') {
                        return;
                    }
                    out.push('<p>' + inline(line) + '</p>');
                });
                closeList();
                return out.join('');
            };

            const updateMeta = () => {
                const value = textarea ? textarea.value : '';
                const count = value.trim().length;
                const lines = Math.max(1, value.split(/\r\n|\n|\r/).length);
                if (chars) chars.textContent = count.toLocaleString() + ' characters';
                if (gutter) gutter.textContent = Array.from({ length: lines }, (_, index) => index + 1).join('\n');
                if (textarea) {
                    textarea.style.height = '0px';
                    textarea.style.height = Math.max(textarea.scrollHeight, 32) + 'px';
                }
                if (preview) preview.innerHTML = renderMarkdown(value);
            };

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const key = tab.dataset.kbView;
                    if (key === 'preview') updateMeta();
                    tabs.forEach((item) => {
                        const on = item === tab;
                        item.classList.toggle('is-active', on);
                        item.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    panels.forEach((panel) => {
                        panel.hidden = panel.dataset.kbPanel !== key;
                    });
                    if (hint) hint.textContent = labels[key] || '';
                    if (scroller) scroller.scrollTop = 0;
                });
            });

            if (textarea) {
                textarea.addEventListener('input', updateMeta);
                updateMeta();
            }

            if (fileInput && fileName) {
                fileInput.addEventListener('change', () => {
                    fileName.textContent = fileInput.files[0] ? fileInput.files[0].name : 'No file chosen';
                });
            }

            const category = root.querySelector('#kb-category');
            const department = root.querySelector('#kb-department');
            const refreshChoice = (select) => select?.closest('.ul-choice')?.ulRefresh?.();
            if (category && department) {
                category.addEventListener('change', () => {
                    const suggested = category.selectedOptions[0]?.dataset.department || '';
                    if (!suggested) return;
                    const exists = Array.from(department.options).some((opt) => opt.value === suggested);
                    if (!exists) return;
                    department.value = suggested;
                    department.dispatchEvent(new Event('change', { bubbles: true }));
                    refreshChoice(department);
                });
            }

        })();
    </script>
</x-app-layout>
