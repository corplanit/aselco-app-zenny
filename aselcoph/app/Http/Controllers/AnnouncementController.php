<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Services\AnnouncementAudienceResolver;
use App\Services\AnnouncementPublisher;
use App\Support\AnnouncementTemplates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: ['status', 'category'],
            sortable: ['created_at' => 'created_at', 'title' => 'title', 'id' => 'id'],
            defaultSort: 'created_at',
            defaultDir: 'desc',
            defaultPerPage: 20,
        );

        $query = Announcement::query()->with('creator:id,name,email');
        if ($list['search']) {
            $term = $list['search'];
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('body', 'like', "%{$term}%");
            });
        }
        if (! empty($list['filters']['status'])) {
            $query->where('status', $list['filters']['status']);
        }
        if (! empty($list['filters']['category'])) {
            $query->where('category', $list['filters']['category']);
        }

        $sortCol = in_array($list['sort'], ['created_at', 'title', 'id'], true) ? $list['sort'] : 'created_at';
        $announcements = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.announcements.index', [
            'announcements' => $announcements,
            'list' => $list,
            'filters' => array_merge($list['filters'], [
                'search' => $list['search'],
                'sort' => $list['sort'],
                'dir' => $list['dir'],
            ]),
            'activeFilterCount' => $list['active_filter_count'],
        ]);
    }

    public function create(): View
    {
        return $this->composeForm();
    }

    public function edit(Announcement $announcement): View|RedirectResponse
    {
        if ($announcement->isPublished()) {
            return redirect()
                ->route('announcements.show', $announcement)
                ->with('error', 'Published announcements cannot be edited.');
        }

        return $this->composeForm($announcement);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $users = User::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('contact_no', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'email', 'contact_no']);

        return response()->json([
            'data' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'contact_no' => $user->contact_no,
            ])->values(),
        ]);
    }

    public function store(Request $request, AnnouncementPublisher $publisher): RedirectResponse
    {
        $validated = $this->validateAnnouncement($request);
        $action = $request->input('action', 'draft');

        $announcement = Announcement::query()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'audience_type' => $validated['audience_type'],
            'audience_user_ids' => $validated['audience_type'] === Announcement::AUDIENCE_USERS
                ? array_values($validated['audience_user_ids'] ?? [])
                : null,
            'meter_numbers' => $validated['audience_type'] === Announcement::AUDIENCE_METER
                ? $this->parseMeterList($validated['meter_numbers'] ?? '')
                : null,
            'status' => Announcement::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        return $this->finishSave($announcement, $action, $publisher, created: true);
    }

    public function update(Request $request, Announcement $announcement, AnnouncementPublisher $publisher): RedirectResponse
    {
        if ($announcement->isPublished()) {
            return redirect()
                ->route('announcements.show', $announcement)
                ->with('error', 'Published announcements cannot be edited.');
        }

        $validated = $this->validateAnnouncement($request);
        $action = $request->input('action', 'draft');

        $announcement->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['category'],
            'audience_type' => $validated['audience_type'],
            'audience_user_ids' => $validated['audience_type'] === Announcement::AUDIENCE_USERS
                ? array_values($validated['audience_user_ids'] ?? [])
                : null,
            'meter_numbers' => $validated['audience_type'] === Announcement::AUDIENCE_METER
                ? $this->parseMeterList($validated['meter_numbers'] ?? '')
                : null,
        ]);

        return $this->finishSave($announcement->fresh(), $action, $publisher, created: false);
    }

    public function show(Announcement $announcement, AnnouncementAudienceResolver $resolver): View
    {
        $announcement->loadMissing('creator:id,name,email');
        $preview = $resolver->previewUsers($announcement, 6);
        $audienceCount = count($resolver->resolveUserIds($announcement));
        $selectedUsers = collect();
        if ($announcement->audience_type === Announcement::AUDIENCE_USERS) {
            $selectedUsers = User::query()
                ->whereIn('id', $announcement->audience_user_ids ?? [])
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'contact_no']);
        }

        return view('pages.staff.announcements.show', compact('announcement', 'preview', 'audienceCount', 'selectedUsers'));
    }

    public function publish(Announcement $announcement, AnnouncementPublisher $publisher): RedirectResponse
    {
        if ($announcement->isPublished()) {
            return redirect()
                ->route('announcements.show', $announcement)
                ->with('success', 'Already published.');
        }

        $result = $publisher->publish($announcement);

        return redirect()
            ->route('announcements.show', $announcement)
            ->with(
                'success',
                "Published. Sent to {$result['sent']} of {$result['audience']} audience member(s)."
            );
    }

    /**
     * Preview how many users a draft audience would hit (AJAX).
     */
    public function previewAudience(Request $request, AnnouncementAudienceResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'audience_type' => ['required', 'in:all,users,meter'],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer'],
            'meter_numbers' => ['nullable', 'string'],
        ]);

        $temp = new Announcement([
            'audience_type' => $validated['audience_type'],
            'audience_user_ids' => $validated['audience_user_ids'] ?? [],
            'meter_numbers' => $this->parseMeterList($validated['meter_numbers'] ?? ''),
        ]);

        $ids = $resolver->resolveUserIds($temp);
        $preview = $resolver->previewUsers($temp, 6);

        return response()->json([
            'count' => count($ids),
            'preview' => $preview->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ]);
    }

    private function composeForm(?Announcement $announcement = null): View
    {
        $selectedIds = collect(old(
            'audience_user_ids',
            $announcement?->audience_user_ids ?? []
        ))->map(fn ($id) => (int) $id)->filter()->values();

        $selectedUsers = $selectedIds->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('id', $selectedIds)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'contact_no']);

        return view('pages.staff.announcements.create', [
            'announcement' => $announcement,
            'selectedUsers' => $selectedUsers,
            'templates' => AnnouncementTemplates::all(),
        ]);
    }

    private function finishSave(
        Announcement $announcement,
        string $action,
        AnnouncementPublisher $publisher,
        bool $created
    ): RedirectResponse {
        if ($action === 'publish') {
            $result = $publisher->publish($announcement);

            return redirect()
                ->route('announcements.show', $announcement)
                ->with(
                    'success',
                    "Announcement published. Sent to {$result['sent']} of {$result['audience']} audience member(s)."
                );
        }

        return redirect()
            ->route('announcements.edit', $announcement)
            ->with('success', $created ? 'Announcement saved as draft.' : 'Draft updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAnnouncement(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', 'in:billing,service,alert'],
            'audience_type' => ['required', 'in:all,users,meter'],
            'audience_user_ids' => ['nullable', 'array'],
            'audience_user_ids.*' => ['integer', 'exists:users,id'],
            'meter_numbers' => ['nullable', 'string', 'max:2000'],
        ], [
            'audience_type.in' => 'Choose All members, Specific users, or Meter number(s).',
        ]);

        if ($validated['audience_type'] === Announcement::AUDIENCE_USERS
            && empty($validated['audience_user_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'audience_user_ids' => 'Select at least one user.',
            ]);
        }

        if ($validated['audience_type'] === Announcement::AUDIENCE_METER
            && trim((string) ($validated['meter_numbers'] ?? '')) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'meter_numbers' => 'Enter at least one meter number.',
            ]);
        }

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function parseMeterList(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
