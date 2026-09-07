<x-app-layout>
    <x-slot name="title">Knowledge Documents</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.index') }}", "text": "Documents"}</x-slot>
    <x-slot name="active">Document list</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.create') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-view">
            <i class="bi bi-plus-lg"></i>Upload
        </a>
        <a href="{{ route('knowledge.categories') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
            <i class="bi bi-folder2"></i>Categories
        </a>
        <a href="{{ route('knowledge.chat') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-chat-dots"></i>Chat AI Testing
        </a>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <x-unified.toolbar
        :action="route('knowledge.index')"
        :reset-url="route('knowledge.index')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search title, department, source…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Category</label>
                <select name="category_id" class="ti-form-select">
                    <option value="">All</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((int)($filters['category_id'] ?? 0) === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="status" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['active','inactive','draft'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
        <x-slot:actions>
            <x-unified.sort-select :options="$sortable" :value="$filters['sort'] ?? 'updated_at'" :dir="$filters['dir'] ?? 'desc'" />
        </x-slot:actions>
    </x-unified.toolbar>

    <x-unified.table
        title="Knowledge documents"
        :paginator="$documents"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('knowledge.index')"
        empty-title="No documents found."
        :colspan="7"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <x-unified.th label="Title" column="title" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th>Category</th>
            <x-unified.th label="Status" column="status" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th>Version</th>
            <x-unified.th label="Updated" column="updated_at" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'desc'" :query="$list['query']" />
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach($documents as $doc)
            @php
                $href = route('knowledge.show', $doc->id);
            @endphp
            <x-unified.row :href="$href">
                <x-unified.td-num :paginator="$documents" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$doc->title">
                    <x-slot:meta>
                        {{ implode(' · ', array_filter([
                            $doc->source ?: 'Internal',
                            $doc->department,
                            $doc->service_type,
                        ])) }}
                    </x-slot:meta>
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
                    {{ optional($doc->updated_at)->timezone('Asia/Manila')->format('M d, Y') }}
                    <div class="ul-date-meta">{{ optional($doc->updated_at)->timezone('Asia/Manila')->format('h:i A') }}</div>
                </td>
                <x-unified.actions :view-url="$href">
                    <x-slot:menu>
                        <a href="{{ $href }}" role="menuitem"><i class="bi bi-eye"></i> View</a>
                        <a href="{{ route('knowledge.edit', $doc->id) }}" role="menuitem"><i class="bi bi-pencil"></i> Edit</a>
                        <div class="ul-menu-sep"></div>
                        <form method="POST" action="{{ route('knowledge.toggle', $doc->id) }}">
                            @csrf
                            <button type="submit" role="menuitem">
                                <i class="bi {{ $doc->status === 'active' ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                {{ $doc->status === 'active' ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('knowledge.reindex', $doc->id) }}">
                            @csrf
                            <button type="submit" role="menuitem"><i class="bi bi-arrow-repeat"></i> Re-index</button>
                        </form>
                    </x-slot:menu>
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
