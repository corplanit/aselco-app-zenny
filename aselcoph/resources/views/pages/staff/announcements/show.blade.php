<x-app-layout>
    @php
        $categoryMeta = match ($announcement->category) {
            'billing' => ['Billing', 'bi-receipt', 'amber'],
            'service' => ['Service', 'bi-tools', 'lime'],
            default => ['Alert', 'bi-exclamation-triangle', 'rose'],
        };
        $audienceMeta = match ($announcement->audience_type) {
            'users' => ['Specific users', 'bi-person-check', 'indigo'],
            'meter' => ['Meter numbers', 'bi-speedometer2', 'orange'],
            default => ['All members', 'bi-people', 'sky'],
        };
        $rest = max(0, $audienceCount - $preview->count());
    @endphp

    <x-slot name="title">Announcement</x-slot>
    <x-slot name="url_1">{"link": "{{ route('announcements.index') }}", "text": "Announcements"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('announcements.show', $announcement) }}", "text": "Detail"}</x-slot>
    <x-slot name="active">#{{ $announcement->id }}</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('announcements.index') }}" class="ti-btn ti-btn-sm ul-btn ul-btn-cancel">
            <i class="bi bi-arrow-left"></i>Back to list
        </a>
        @if (! $announcement->isPublished())
            <a href="{{ route('announcements.edit', $announcement) }}" class="ti-btn ti-btn-sm ul-btn ul-btn-more">
                <i class="bi bi-pencil-square"></i>Edit draft
            </a>
            <form action="{{ route('announcements.publish', $announcement) }}" method="POST" class="inline">
                @csrf
                <button
                    type="submit"
                    class="ti-btn ti-btn-sm ul-btn ul-btn-view"
                    data-ul-confirm="Publish this announcement and push it to the audience."
                    data-ul-confirm-verb="Publish"
                    data-ul-confirm-icon="bi-send"
                >
                    <i class="bi bi-send"></i>Publish &amp; push
                </button>
            </form>
        @endif
    </x-slot>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-8 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">Message</div>
                        <div class="td-hero-badges">
                            @if ($announcement->isPublished())
                                <x-unified.badge tone="success" icon="bi-send-check">Published</x-unified.badge>
                            @else
                                <x-unified.badge tone="warning" icon="bi-pencil-square">Draft</x-unified.badge>
                            @endif
                            <x-unified.badge :tone="$categoryMeta[2]" :icon="$categoryMeta[1]">{{ $categoryMeta[0] }}</x-unified.badge>
                        </div>
                    </div>
                </div>
                <div class="box-body space-y-3">
                    <div>
                        <div class="text-xs text-textmuted mb-1">Title</div>
                        <div class="font-semibold text-base">{{ $announcement->title }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-textmuted mb-1">Message</div>
                        <p class="whitespace-pre-wrap mb-0">{{ $announcement->body }}</p>
                    </div>
                </div>
            </div>

            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">Audience</div>
                </div>
                <div class="box-body space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-unified.badge :tone="$audienceMeta[2]" :icon="$audienceMeta[1]">{{ $audienceMeta[0] }}</x-unified.badge>
                        <x-unified.badge tone="sky" icon="bi-people">{{ number_format($audienceCount) }} member(s)</x-unified.badge>
                        <x-unified.badge tone="indigo" icon="bi-send">{{ number_format((int) $announcement->sent_count) }} sent</x-unified.badge>
                    </div>

                    @if ($announcement->audience_type === 'meter')
                        <div class="ul-announce-tags" style="cursor: default;">
                            @forelse ($announcement->meter_numbers ?? [] as $meter)
                                <span class="ul-announce-tag">
                                    <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                    <span>{{ $meter }}</span>
                                </span>
                            @empty
                                <span class="text-xs text-textmuted">No meter numbers saved.</span>
                            @endforelse
                        </div>
                    @endif

                    @if ($announcement->audience_type === 'users')
                        <div class="ul-announce-selected">
                            @forelse ($selectedUsers as $user)
                                <div class="ul-announce-selected-item">
                                    <span class="ul-choice-mark ul-badge is-sky">
                                        <i class="bi bi-person" aria-hidden="true"></i>
                                    </span>
                                    <span class="ul-announce-user-copy">
                                        <span class="ul-announce-user-name">{{ $user->name }}</span>
                                        <span class="ul-announce-user-meta">{{ $user->email }}{{ $user->contact_no ? ' · '.$user->contact_no : '' }}</span>
                                    </span>
                                </div>
                            @empty
                                <div class="ul-announce-user-empty">No selected users found.</div>
                            @endforelse
                        </div>
                    @endif
                </div>
            </div>

            <div class="box ul-card ul-announce-actions-card">
                <div class="box-header ul-card-header">
                    <div class="box-title ul-card-title mb-0">Audience sample</div>
                </div>
                <div class="box-body">
                    @if ($preview->isEmpty())
                        <div class="ul-announce-preview-more">No members matched this audience.</div>
                    @else
                        <ul class="ul-announce-preview">
                            @foreach ($preview as $user)
                                <li class="ul-announce-preview-item">
                                    <span class="ul-choice-mark ul-badge is-sky"><i class="bi bi-person"></i></span>
                                    <span class="ul-announce-user-copy">
                                        <span class="ul-announce-user-name">{{ $user->name }}</span>
                                        <span class="ul-announce-user-meta">{{ $user->email }}</span>
                                    </span>
                                </li>
                            @endforeach
                            @if ($rest > 0)
                                <li class="ul-announce-preview-more">+{{ number_format($rest) }} more member{{ $rest === 1 ? '' : 's' }}</li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>
        </div>

        <div class="xl:col-span-4 col-span-12 space-y-6">
            <div class="box ul-card">
                <div class="box-header ul-card-header">
                    <div class="td-hero-title">
                        <div class="box-title ul-card-title mb-0">Details</div>
                        <div class="td-hero-badges">
                            <x-unified.badge tone="slate" icon="bi-hash">#{{ $announcement->id }}</x-unified.badge>
                        </div>
                    </div>
                </div>
                <div class="box-body p-0">
                    <div class="ul-table-wrap">
                        <table class="table ul-table td-kv-table mb-0">
                            <tbody>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        @if ($announcement->isPublished())
                                            <x-unified.badge tone="success" icon="bi-send-check">Published</x-unified.badge>
                                        @else
                                            <x-unified.badge tone="warning" icon="bi-pencil-square">Draft</x-unified.badge>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Category</th>
                                    <td>
                                        <x-unified.badge :tone="$categoryMeta[2]" :icon="$categoryMeta[1]">{{ $categoryMeta[0] }}</x-unified.badge>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Audience</th>
                                    <td>
                                        <x-unified.badge :tone="$audienceMeta[2]" :icon="$audienceMeta[1]">{{ $audienceMeta[0] }}</x-unified.badge>
                                        <div class="ul-date-meta">{{ number_format($audienceCount) }} member(s) · {{ number_format((int) $announcement->sent_count) }} sent</div>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created by</th>
                                    <td>
                                        <div class="font-semibold">{{ $announcement->creator?->name ?? '—' }}</div>
                                        @if ($announcement->creator?->email)
                                            <div class="ul-date-meta">{{ $announcement->creator->email }}</div>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created</th>
                                    <td>
                                        @if ($announcement->created_at)
                                            {{ $announcement->created_at->timezone('Asia/Manila')->format('M d, Y') }}
                                            <div class="ul-date-meta">{{ $announcement->created_at->timezone('Asia/Manila')->format('h:i A') }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th>Published</th>
                                    <td>
                                        @if ($announcement->published_at)
                                            {{ $announcement->published_at->timezone('Asia/Manila')->format('M d, Y') }}
                                            <div class="ul-date-meta">{{ $announcement->published_at->timezone('Asia/Manila')->format('h:i A') }}</div>
                                        @else
                                            <span class="ul-empty">Not published yet</span>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if (! $announcement->isPublished())
                <x-unified.note tone="amber" icon="bi-pencil-square" title="Draft">
                    This announcement has not been sent. Edit the draft, then publish when the audience looks right.
                </x-unified.note>
            @else
                <x-unified.note tone="sky" icon="bi-bell" title="Already published">
                    Members who matched the audience received this in their inbox and push, subject to their notification preferences.
                </x-unified.note>
            @endif
        </div>
    </div>
</x-app-layout>
