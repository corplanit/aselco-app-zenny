<x-app-layout>
    @php
        $versions = $document->versions->sortByDesc('version')->values();
        $current = $versions->firstWhere('version', $document->current_version);
        $isActive = $document->status === \App\Models\KnowledgeDocument::STATUS_ACTIVE;
        $retrievable = $document->isRetrievable();
        $indexed = filled($current?->indexed_at);
        $chunkCount = (int) ($document->active_chunks_count ?? 0);
        $wordCount = str_word_count(trim(strip_tags((string) $body)));
        $charCount = mb_strlen(trim((string) $body));
        $updatedAt = $document->updated_at?->timezone('Asia/Manila');
        $createdAt = $document->created_at?->timezone('Asia/Manila');
        $effectiveAt = $document->effective_at?->timezone('Asia/Manila');
        $expiresAt = $document->expires_at?->timezone('Asia/Manila');
        $categoryTone = \App\Support\KnowledgeUi::categoryTone($document->category);
        $categoryIcon = \App\Support\KnowledgeUi::categoryIcon($document->category);
        $statusTone = \App\Support\KnowledgeUi::statusTone($document->status);
        $statusIcon = \App\Support\KnowledgeUi::statusIcon($document->status);
        $kicker = trim(implode(' · ', array_filter([
            $document->source ?: 'Internal',
            $document->department,
            $document->service_type,
        ])));
        $bodyHtml = filled($body)
            ? \Illuminate\Support\Str::markdown((string) $body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ])
            : '';
        $kpis = [
            ['Version', 'v'.$document->current_version, 'bi-layers', 'is-indigo'],
            ['Chunks', number_format($chunkCount), 'bi-puzzle', $chunkCount > 0 ? 'is-sky' : 'is-amber'],
            ['Words', number_format($wordCount), 'bi-textarea-t', 'is-teal'],
            ['Updated', $updatedAt?->diffForHumans() ?: '—', 'bi-clock-history', 'is-slate'],
        ];
    @endphp

    <x-slot name="title">{{ $document->title }}</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.index') }}", "text": "Documents"}</x-slot>
    <x-slot name="active">Detail</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Documents
        </a>
        <a href="{{ route('knowledge.chat') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-chat-dots"></i>Chat AI Testing
        </a>
        <form method="POST" action="{{ route('knowledge.reindex', $document->id) }}" class="inline">
            @csrf
            <button class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-arrow-repeat"></i>Re-index
            </button>
        </form>
        <form method="POST" action="{{ route('knowledge.toggle', $document->id) }}" class="inline">
            @csrf
            <button class="ti-btn ti-btn-sm ul-btn {{ $isActive ? 'ul-btn-cancel' : 'ul-btn-view' }}">
                <i class="bi {{ $isActive ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                {{ $isActive ? 'Deactivate' : 'Activate' }}
            </button>
        </form>
        <a href="{{ route('knowledge.edit', $document->id) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-edit">
            <i class="bi bi-pencil"></i>Edit
        </a>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <div class="ai-dash kb-dash kb-doc" id="kb-document">
        <div class="box ul-card ai-layout">
            <div class="ai-layout-head">
                <div class="ai-layout-brand">
                    <span class="ai-layout-orb {{ $retrievable ? 'is-live' : '' }}" aria-hidden="true">
                        <i class="bi {{ $categoryIcon }}"></i>
                    </span>
                    <div>
                        <div class="ai-layout-title">
                            {{ $document->title }}
                            <x-unified.badge :tone="$statusTone" :icon="$statusIcon">{{ ucfirst($document->status) }}</x-unified.badge>
                            @if($document->category)
                                <x-unified.badge :tone="$categoryTone" :icon="$categoryIcon">{{ $document->category->name }}</x-unified.badge>
                            @endif
                            <x-unified.badge tone="indigo" icon="bi-layers">v{{ $document->current_version }}</x-unified.badge>
                            @if($retrievable)
                                <x-unified.badge tone="lime" icon="bi-search">In retrieval</x-unified.badge>
                            @else
                                <x-unified.badge tone="amber" icon="bi-eye-slash">Not in retrieval</x-unified.badge>
                            @endif
                        </div>
                        <p class="ai-layout-kicker">
                            {{ $kicker !== '' ? $kicker : 'Approved knowledge used by the assistant when this document is active and indexed.' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="ai-dash-kpis">
                <div class="ul-kpi-grid is-four">
                    @foreach($kpis as $tile)
                        <div class="ul-kpi {{ $tile[3] }}">
                            <span class="ul-kpi-icon"><i class="bi {{ $tile[2] }}"></i></span>
                            <span class="ul-kpi-copy">
                                <span class="ul-kpi-value">{{ $tile[1] }}</span>
                                <span class="ul-kpi-label">{{ $tile[0] }}</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="kb-doc-notes">
                @if($retrievable && $indexed)
                    <x-unified.note tone="lime" icon="bi-check-circle" title="Ready for the assistant">
                        Active, in date, and indexed. Chat AI Testing can cite this document.
                    </x-unified.note>
                @elseif($isActive && ! $indexed)
                    <x-unified.note tone="amber" icon="bi-hourglass-split" title="Index pending">
                        This version is not indexed yet. Re-index so retrieval can use the current body.
                    </x-unified.note>
                @elseif($isActive && $effectiveAt && $effectiveAt->isFuture())
                    <x-unified.note tone="amber" icon="bi-calendar-event" title="Not yet effective">
                        Retrieval starts {{ $effectiveAt->format('M d, Y h:i A') }}.
                    </x-unified.note>
                @elseif($isActive && $expiresAt && $expiresAt->isPast())
                    <x-unified.note tone="rose" icon="bi-calendar-x" title="Expired">
                        This document expired {{ $expiresAt->format('M d, Y h:i A') }} and is out of retrieval.
                    </x-unified.note>
                @else
                    <x-unified.note tone="slate" icon="bi-pause-circle" title="Held out of retrieval">
                        Inactive and draft documents stay out of customer answers until staff activate them.
                    </x-unified.note>
                @endif
            </div>
        </div>

        <div class="kb-doc-grid">
            <section class="box ul-card kb-doc-main">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title">
                        Current body
                        <span class="ul-card-count">{{ number_format($charCount) }} characters</span>
                    </div>
                </div>
                <div class="box-body kb-doc-body-wrap">
                    @if(filled($body))
                        <div class="kb-doc-reader">
                            <div class="kb-doc-reader-bar">
                                <div class="kb-doc-switch" role="tablist" aria-label="Body view">
                                    <button type="button" class="is-active" role="tab" data-kb-view="preview" aria-selected="true">
                                        <i class="bi bi-eye"></i>Preview
                                    </button>
                                    <button type="button" role="tab" data-kb-view="source" aria-selected="false">
                                        <i class="bi bi-code-slash"></i>Source
                                    </button>
                                </div>
                                <span class="kb-doc-reader-hint" data-kb-hint>Rendered markdown</span>
                            </div>
                            <div class="kb-doc-reader-pane" data-kb-scroll>
                                <article class="kb-doc-prose" data-kb-panel="preview">{!! $bodyHtml !!}</article>
                                @php
                                    $sourceLines = preg_split("/\r\n|\n|\r/", (string) $body);
                                    $sourceLineCount = max(1, count($sourceLines));
                                @endphp
                                <div class="kb-doc-code" data-kb-panel="source" hidden>
                                    <div class="kb-doc-gutter" aria-hidden="true">@for($i = 1; $i <= $sourceLineCount; $i++){{ $i }}{{ $i < $sourceLineCount ? "\n" : '' }}@endfor</div>
                                    <pre class="kb-doc-source" tabindex="0">{{ $body }}</pre>
                                </div>
                            </div>
                        </div>
                    @else
                        <x-unified.note tone="slate" icon="bi-file-earmark" title="No body yet">
                            Edit this document or upload a file to add the text the assistant can retrieve.
                        </x-unified.note>
                    @endif
                </div>
            </section>

            <aside class="kb-doc-side">
                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">Details</div>
                    </div>
                    <div class="box-body p-0">
                        <div class="ul-table-wrap">
                            <table class="table ul-table td-kv-table mb-0">
                                <tbody>
                                    <tr>
                                        <th>Category</th>
                                        <td>
                                            @if($document->category)
                                                <a href="{{ route('knowledge.index', ['category_id' => $document->category_id]) }}" class="ul-primary-link">
                                                    {{ $document->category->name }}
                                                </a>
                                            @else
                                                <span class="ul-empty">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Department</th>
                                        <td>{{ $document->department ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Service type</th>
                                        <td>{{ $document->service_type ?: '—' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Source</th>
                                        <td>{{ $document->source ?: 'Internal' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Effective</th>
                                        <td>
                                            @if($effectiveAt)
                                                {{ $effectiveAt->format('M d, Y') }}
                                                <div class="ul-date-meta">{{ $effectiveAt->format('h:i A') }}</div>
                                            @else
                                                <span class="ul-empty">Always</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Expires</th>
                                        <td>
                                            @if($expiresAt)
                                                {{ $expiresAt->format('M d, Y') }}
                                                <div class="ul-date-meta">{{ $expiresAt->format('h:i A') }}</div>
                                            @else
                                                <span class="ul-empty">No expiry</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Created</th>
                                        <td>
                                            {{ $document->creator?->name ?: '—' }}
                                            @if($createdAt)
                                                <div class="ul-date-meta">{{ $createdAt->format('M d, Y h:i A') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Updated</th>
                                        <td>
                                            {{ $document->updater?->name ?: '—' }}
                                            @if($updatedAt)
                                                <div class="ul-date-meta">{{ $updatedAt->format('M d, Y h:i A') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($current?->checksum)
                                        <tr>
                                            <th>Checksum</th>
                                            <td class="kb-doc-checksum">{{ $current->checksum }}</td>
                                        </tr>
                                    @endif
                                    @if($document->file_path)
                                        <tr>
                                            <th>File</th>
                                            <td>{{ $document->file_path }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="box ul-card">
                    <div class="box-header ul-card-header">
                        <div class="box-title ul-card-title">
                            Versions
                            <span class="ul-card-count">{{ $versions->count() }}</span>
                        </div>
                    </div>
                    <div class="box-body">
                        @if($versions->isEmpty())
                            <x-unified.note tone="slate" icon="bi-layers" title="No versions">
                                Saving this document will create the first indexed version.
                            </x-unified.note>
                        @else
                            <div class="kb-doc-versions">
                                @foreach($versions as $version)
                                    @php
                                        $isCurrent = (int) $version->version === (int) $document->current_version;
                                        $versionAt = $version->created_at?->timezone('Asia/Manila');
                                        $indexedAt = $version->indexed_at?->timezone('Asia/Manila');
                                    @endphp
                                    <div class="kb-doc-version {{ $isCurrent ? 'is-current' : '' }}">
                                        <span class="kb-doc-version-icon">
                                            <i class="bi {{ $indexedAt ? 'bi-check2' : 'bi-hourglass-split' }}"></i>
                                        </span>
                                        <span class="kb-doc-version-copy">
                                            <span class="kb-doc-version-title">
                                                v{{ $version->version }}
                                                @if($isCurrent) · Current @endif
                                            </span>
                                            <span class="kb-doc-version-meta">
                                                {{ $versionAt?->format('M d, Y h:i A') ?: '—' }}
                                                ·
                                                {{ $indexedAt ? 'Indexed '.$indexedAt->diffForHumans() : 'Not indexed' }}
                                            </span>
                                        </span>
                                        @if($indexedAt)
                                            <x-unified.badge tone="lime" icon="bi-check2">Indexed</x-unified.badge>
                                        @else
                                            <x-unified.badge tone="amber" icon="bi-hourglass-split">Pending</x-unified.badge>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        (function () {
            const root = document.getElementById('kb-document');
            if (!root) return;
            const tabs = root.querySelectorAll('[data-kb-view]');
            const panels = root.querySelectorAll('[data-kb-panel]');
            const hint = root.querySelector('[data-kb-hint]');
            const scroller = root.querySelector('[data-kb-scroll]');
            const labels = { preview: 'Rendered markdown', source: 'Raw markdown source' };
            if (!tabs.length || !panels.length) return;
            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const key = tab.dataset.kbView;
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
        })();
    </script>
</x-app-layout>
