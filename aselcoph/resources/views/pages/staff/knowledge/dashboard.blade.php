<x-app-layout>
    <x-slot name="title">Knowledge Base</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.dashboard') }}", "text": "Dashboard"}</x-slot>
    <x-slot name="active">AI Knowledge Base</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-file-earmark-text"></i>Documents
        </a>
        <a href="{{ route('knowledge.chat') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
            <i class="bi bi-chat-dots"></i>Chat AI Testing
        </a>
    </x-slot>

    @php
        $documents = (int) ($stats['documents'] ?? 0);
        $active = (int) ($stats['active'] ?? 0);
        $chunks = (int) ($stats['chunks'] ?? 0);
        $pending = (int) ($stats['pending_index'] ?? 0);
        $categoryCount = (int) ($stats['categories'] ?? 0);
        $activeRate = (float) ($stats['active_rate'] ?? 0);
        $indexReady = (float) ($stats['index_ready_rate'] ?? 0);
        $filledCategories = $categories->where('documents_count', '>', 0)->count();
        $fillRate = $categoryCount > 0 ? round(($filledCategories / $categoryCount) * 100, 1) : 0;
        $categoryTotal = max(1, (int) $categories->sum('documents_count'));
        $healthTone = static function (float $rate): string {
            return $rate >= 80 ? 'lime' : ($rate >= 50 ? 'amber' : 'rose');
        };
        $kpis = [
            ['Documents', number_format($documents), 'bi-file-earmark-text', 'is-indigo'],
            ['Active', number_format($active), 'bi-check-circle', 'is-lime'],
            ['Pending index', number_format($pending), 'bi-hourglass-split', 'is-amber'],
            ['Indexed chunks', number_format($chunks), 'bi-layers', 'is-sky'],
        ];
    @endphp

    @if(session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <div class="ai-dash kb-dash">
        <div class="box ul-card ai-layout">
            <div class="ai-layout-head">
                <div class="ai-layout-brand">
                    <span class="ai-layout-orb {{ $pending > 0 ? 'is-live' : '' }}" aria-hidden="true">
                        <i class="bi bi-book"></i>
                    </span>
                    <div>
                        <div class="ai-layout-title">
                            Knowledge Base
                            <x-unified.badge tone="slate" icon="bi-shield-lock">Internal RAG</x-unified.badge>
                            @if($pending > 0)
                                <x-unified.badge tone="amber" icon="bi-hourglass-split">{{ number_format($pending) }} pending index</x-unified.badge>
                            @else
                                <x-unified.badge tone="lime" icon="bi-check2">Index current</x-unified.badge>
                            @endif
                        </div>
                        <p class="ai-layout-kicker">
                            Approved documents the AI uses to answer customer questions.
                            Inactive and draft files stay out of retrieval until staff publish them.
                        </p>
                    </div>
                </div>
                <div class="ai-layout-head-side">
                    <div class="ai-layout-actions">
                        <a href="{{ route('knowledge.categories') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                            <i class="bi bi-folder2"></i>Categories
                        </a>
                    </div>
                </div>
            </div>

            <div class="ai-health">
                <div class="ai-meter is-{{ $healthTone($activeRate) }}">
                    <div class="ai-meter-top">
                        <span>Active coverage</span>
                        <strong>{{ $activeRate }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $activeRate) }}%"></span></div>
                    <div class="ai-meter-meta">{{ number_format($active) }} of {{ number_format($documents) }} documents live</div>
                </div>
                <div class="ai-meter is-{{ $healthTone($indexReady) }}">
                    <div class="ai-meter-top">
                        <span>Index ready</span>
                        <strong>{{ $indexReady }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $indexReady) }}%"></span></div>
                    <div class="ai-meter-meta">{{ number_format($chunks) }} chunks · {{ number_format($pending) }} waiting</div>
                </div>
                <div class="ai-meter is-{{ $healthTone($fillRate) }}">
                    <div class="ai-meter-top">
                        <span>Topics filled</span>
                        <strong>{{ $fillRate }}%</strong>
                    </div>
                    <div class="ai-meter-track" aria-hidden="true"><span style="width: {{ min(100, $fillRate) }}%"></span></div>
                    <div class="ai-meter-meta">{{ number_format($filledCategories) }} of {{ number_format($categoryCount) }} categories</div>
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

            <section class="ai-section">
                <div class="ai-pane-head">
                    <span class="ai-pane-icon"><i class="bi bi-folder2"></i></span>
                    <div>
                        <div class="ai-pane-title">Categories</div>
                        <div class="ai-pane-sub">Equal topics across the approved corpus</div>
                    </div>
                </div>
                @if($categories->isEmpty())
                    <div class="ai-empty is-compact">
                        <i class="bi bi-folder2"></i>
                        <div class="ai-empty-title">No categories yet</div>
                        <p>Add topics so staff can file approved knowledge.</p>
                    </div>
                @else
                    <div class="kb-cat-grid">
                        @foreach($categories as $category)
                            @php
                                $tone = \App\Support\KnowledgeUi::categoryTone($category);
                                $count = (int) $category->documents_count;
                                $share = min(100, round(($count / $categoryTotal) * 100, 1));
                                $href = route('knowledge.index', ['category_id' => $category->id]);
                            @endphp
                            <a class="kb-cat is-{{ $tone }}" href="{{ $href }}">
                                <span class="kb-cat-icon"><i class="bi {{ \App\Support\KnowledgeUi::categoryIcon($category) }}"></i></span>
                                <span class="kb-cat-copy">
                                    <span class="kb-cat-name">{{ $category->name }}</span>
                                    <span class="kb-cat-count">{{ number_format($count) }} {{ $count === 1 ? 'document' : 'documents' }}</span>
                                </span>
                                <span class="kb-cat-track" aria-hidden="true"><span style="width: {{ $share }}%"></span></span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ai-section">
                <div class="ai-pane-head">
                    <span class="ai-pane-icon"><i class="bi bi-lightning"></i></span>
                    <div>
                        <div class="ai-pane-title">Quick actions</div>
                        <div class="ai-pane-sub">Maintain the corpus the model can cite</div>
                    </div>
                </div>
                <div class="ul-qa-grid kb-qa-grid">
                    <a class="ul-qa is-indigo" href="{{ route('knowledge.index') }}">
                        <span class="ul-qa-icon"><i class="bi bi-file-earmark-text"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Documents</span>
                            <span class="ul-qa-hint">{{ number_format($documents) }} in the library</span>
                        </span>
                    </a>
                    <a class="ul-qa is-violet" href="{{ route('knowledge.chat') }}">
                        <span class="ul-qa-icon"><i class="bi bi-chat-dots"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Chat AI Testing</span>
                            <span class="ul-qa-hint">Live assistant with RAG citations</span>
                        </span>
                    </a>
                    <a class="ul-qa is-amber" href="{{ route('knowledge.categories') }}">
                        <span class="ul-qa-icon"><i class="bi bi-folder2"></i></span>
                        <span class="ul-qa-copy">
                            <span class="ul-qa-title">Manage categories</span>
                            <span class="ul-qa-hint">{{ number_format($categoryCount) }} topics in the catalog</span>
                        </span>
                    </a>
                </div>
            </section>
        </div>

        <x-unified.table
            class="ul-kb-table"
            title="Recently updated"
            :items="$recent"
            :colspan="7"
            empty-title="No documents yet."
            empty-text="Seed or upload approved knowledge to start answering customer questions."
        >
            <x-slot:headerActions>
                <a href="{{ route('knowledge.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                    <i class="bi bi-box-arrow-up-right"></i>All documents
                </a>
            </x-slot:headerActions>
            <x-slot:head>
                <th class="ul-row-num">#</th>
                <th>Title</th>
                <th>Category</th>
                <th>Status</th>
                <th>Version</th>
                <th>Updated</th>
                <th class="text-end ul-col-actions">Actions</th>
            </x-slot:head>
            @foreach($recent as $doc)
                @php
                    $href = route('knowledge.show', $doc->id);
                    $updatedAt = $doc->updated_at?->timezone('Asia/Manila');
                    $meta = trim(implode(' · ', array_filter([
                        $doc->source ?: 'Internal',
                        $doc->department,
                    ])));
                @endphp
                <x-unified.row :href="$href">
                    <td class="ul-row-num">{{ $loop->iteration }}</td>
                    <x-unified.td-primary :href="$href" :text="$doc->title">
                        <x-slot:meta>{{ $meta !== '' ? $meta : '—' }}</x-slot:meta>
                    </x-unified.td-primary>
                    <td>
                        @if($doc->category)
                            <x-unified.badge
                                :tone="\App\Support\KnowledgeUi::categoryTone($doc->category)"
                                :icon="\App\Support\KnowledgeUi::categoryIcon($doc->category)"
                            >{{ $doc->category->name }}</x-unified.badge>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
                    <td>
                        <x-unified.badge
                            :tone="\App\Support\KnowledgeUi::statusTone($doc->status)"
                            :icon="\App\Support\KnowledgeUi::statusIcon($doc->status)"
                        >{{ ucfirst($doc->status) }}</x-unified.badge>
                    </td>
                    <td>
                        <x-unified.chip icon="bi-layers" quiet>v{{ $doc->current_version }}</x-unified.chip>
                    </td>
                    <td class="ul-date">
                        @if($updatedAt)
                            {{ $updatedAt->diffForHumans() }}
                            <div class="ul-date-meta">{{ $updatedAt->format('M d, Y h:i A') }}</div>
                        @else
                            <span class="ul-empty">—</span>
                        @endif
                    </td>
                    <x-unified.actions :view-url="$href" />
                </x-unified.row>
            @endforeach
        </x-unified.table>
    </div>
</x-app-layout>
