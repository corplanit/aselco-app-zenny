<x-app-layout>
    <x-slot name="title">Mobile Announcements</x-slot>
    <x-slot name="url_1">{"link": "/announcements", "text": "Push"}</x-slot>
    <x-slot name="url_2">{"link": "/announcements", "text": "Announcements"}</x-slot>
    <x-slot name="active">List</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('announcements.create') }}" class="ti-btn ti-btn-primary ti-btn-sm">
            <i class="bi bi-megaphone me-1"></i>New announcement
        </a>
    </x-slot>

    <div class="alert alert-info text-sm mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Send billing, service, or alert announcements to the mobile app (in-app inbox + push).
    </div>

    @if (session('success')) <div class="alert alert-success mb-4">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-danger mb-4">{{ session('error') }}</div> @endif

    <x-unified.toolbar
        :action="route('announcements.index')"
        :reset-url="route('announcements.index')"
        :search-value="$filters['search'] ?? ''"
        search-placeholder="Search title or body…"
        :active-filter-count="$activeFilterCount"
    >
        <x-slot:filters>
            <div>
                <label class="ti-form-label">Status</label>
                <select name="status" class="ti-form-select">
                    <option value="">All</option>
                    <option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Draft</option>
                    <option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option>
                </select>
            </div>
            <div>
                <label class="ti-form-label">Category</label>
                <select name="category" class="ti-form-select">
                    <option value="">All</option>
                    @foreach(['billing','service','alert'] as $cat)
                        <option value="{{ $cat }}" @selected(($filters['category'] ?? '') === $cat)>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
            </div>
        </x-slot:filters>
    </x-unified.toolbar>

    <x-unified.table
        title="Announcements"
        :paginator="$announcements"
        :has-filters="$activeFilterCount > 0"
        :reset-url="route('announcements.index')"
        empty-title="No announcements yet."
        :colspan="8"
    >
        <x-slot:head>
            <th class="ul-row-num">#</th>
            <th>Title</th>
            <th>Audience</th>
            <th>Category</th>
            <th>Status</th>
            <th>Sent</th>
            <th>Created</th>
            <th class="text-end ul-col-actions">Actions</th>
        </x-slot:head>

        @foreach ($announcements as $item)
            @php $href = route('announcements.show', $item); @endphp
            <x-unified.row :href="$href">
                <x-unified.td-num :paginator="$announcements" :iteration="$loop->iteration" />
                <x-unified.td-primary :href="$href" :text="$item->title">
                    <x-slot:meta>{{ \Illuminate\Support\Str::limit($item->body, 80) }}</x-slot:meta>
                </x-unified.td-primary>
                <td>
                    <x-unified.badge
                        tone="primary"
                        :icon="$item->audience_type === 'all' ? 'bi-people' : 'bi-person-badge'"
                    >{{ $item->audience_type }}</x-unified.badge>
                </td>
                <td>
                    @php
                        $announcementCat = match ($item->category) {
                            'billing' => ['icon' => 'bi-receipt', 'tone' => 'amber'],
                            'service' => ['icon' => 'bi-tools', 'tone' => 'lime'],
                            'alert' => ['icon' => 'bi-exclamation-triangle', 'tone' => 'rose'],
                            default => ['icon' => 'bi-megaphone', 'tone' => 'indigo'],
                        };
                    @endphp
                    <x-unified.badge :tone="$announcementCat['tone']" :icon="$announcementCat['icon']">{{ ucfirst($item->category) }}</x-unified.badge>
                </td>
                <td>
                    @if ($item->status === 'published')
                        <x-unified.badge tone="success" icon="bi-send-check">Published</x-unified.badge>
                    @else
                        <x-unified.badge tone="warning" icon="bi-pencil-square">Draft</x-unified.badge>
                    @endif
                </td>
                <td>{{ number_format((int) $item->sent_count) }}</td>
                <td class="ul-date">
                    {{ $item->created_at?->timezone('Asia/Manila')->format('M d, Y') }}
                    <div class="ul-date-meta">{{ $item->creator?->name ?? '—' }} · {{ $item->created_at?->timezone('Asia/Manila')->format('h:i A') }}</div>
                </td>
                <x-unified.actions :view-url="$href">
                    <x-slot:menu>
                        <a href="{{ $href }}" role="menuitem"><i class="bi bi-eye"></i> View</a>
                        @if ($item->status !== 'published')
                            <a href="{{ route('announcements.edit', $item) }}" role="menuitem"><i class="bi bi-pencil-square"></i> Edit</a>
                            <div class="ul-menu-sep"></div>
                            <form
                                action="{{ route('announcements.publish', $item) }}"
                                method="POST"
                                data-ul-confirm="Publish this announcement and push it to the audience."
                                data-ul-confirm-verb="Publish"
                                data-ul-confirm-icon="bi-send"
                            >
                                @csrf
                                <button type="submit" class="ul-menu-danger" role="menuitem"><i class="bi bi-send"></i> Publish</button>
                            </form>
                        @endif
                    </x-slot:menu>
                </x-unified.actions>
            </x-unified.row>
        @endforeach
    </x-unified.table>
</x-app-layout>
