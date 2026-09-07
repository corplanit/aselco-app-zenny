<x-app-layout>
    <x-slot name="title">Knowledge categories</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.categories') }}", "text": "Categories"}</x-slot>
    <x-slot name="active">Categories</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.index') }}" class="ti-btn ti-btn-light ti-btn-sm">Documents</a>
    </x-slot>

    @if(session('status'))
        <div class="alert alert-success mb-4">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-12 gap-4">
        <div class="xl:col-span-7 col-span-12">
            <x-unified.toolbar
                :action="route('knowledge.categories')"
                :reset-url="route('knowledge.categories')"
                :search-value="$filters['search'] ?? ''"
                search-placeholder="Search categories…"
                :active-filter-count="$activeFilterCount"
                :show-filter="false"
            />

            <x-unified.table
                title="Categories"
                :paginator="$categories"
                :has-filters="$activeFilterCount > 0"
                :reset-url="route('knowledge.categories')"
                empty-title="No categories found."
                :colspan="5"
            >
                <x-slot:head>
                    <th class="ul-row-num">#</th>
                    <x-unified.th label="Name" column="name" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
                    <th>Slug</th>
                    <th>Docs</th>
                    <x-unified.th label="Order" column="sort_order" :current-sort="$filters['sort'] ?? null" :current-dir="$filters['dir'] ?? 'asc'" :query="$list['query']" />
                </x-slot:head>
                @foreach($categories as $category)
                    <x-unified.row>
                        <x-unified.td-num :paginator="$categories" :iteration="$loop->iteration" />
                        <td>
                            <x-unified.badge
                                :tone="\App\Support\KnowledgeUi::categoryTone($category)"
                                :icon="\App\Support\KnowledgeUi::categoryIcon($category)"
                            >{{ $category->name }}</x-unified.badge>
                        </td>
                        <td><x-unified.chip icon="bi-hash" quiet>{{ $category->slug }}</x-unified.chip></td>
                        <td>
                            <x-unified.badge :tone="$category->documents_count ? 'primary' : 'neutral'" icon="bi-file-earmark-text">
                                {{ number_format((int) $category->documents_count) }}
                            </x-unified.badge>
                        </td>
                        <td><x-unified.chip icon="bi-sort-numeric-down" quiet>{{ $category->sort_order }}</x-unified.chip></td>
                    </x-unified.row>
                @endforeach
            </x-unified.table>
        </div>
        <div class="xl:col-span-5 col-span-12">
            <div class="box">
                <div class="box-header"><div class="box-title">Add category</div></div>
                <div class="box-body">
                    <form method="POST" action="{{ route('knowledge.categories.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="ti-form-label">Slug</label>
                            <input type="text" name="slug" class="ti-form-input" required maxlength="64" placeholder="billing-information">
                        </div>
                        <div class="mb-3">
                            <label class="ti-form-label">Name</label>
                            <input type="text" name="name" class="ti-form-input" required maxlength="160">
                        </div>
                        <div class="mb-3">
                            <label class="ti-form-label">Description</label>
                            <textarea name="description" class="ti-form-input" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="ti-form-label">Sort order</label>
                            <input type="number" name="sort_order" class="ti-form-input" value="0" min="0">
                        </div>
                        <button class="ti-btn ti-btn-primary">Save category</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
