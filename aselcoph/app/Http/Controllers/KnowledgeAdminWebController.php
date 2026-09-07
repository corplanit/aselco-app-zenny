<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeDocument;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\ConversationCoach;
use App\Services\Rag\KnowledgeBaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KnowledgeAdminWebController extends Controller
{
    public function __construct(
        private KnowledgeBaseService $knowledge,
        private AiAssistantService $assistant,
        private ConversationCoach $coach,
    ) {}

    public function dashboard(Request $request): View
    {
        $this->assertStaff($request);

        return view('pages.staff.knowledge.dashboard', [
            'stats' => $this->knowledge->dashboard(),
            'categories' => KnowledgeCategory::query()->withCount('documents')->orderBy('sort_order')->orderBy('name')->get(),
            'recent' => KnowledgeDocument::query()->with('category')->orderByDesc('updated_at')->limit(8)->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $this->assertStaff($request);

        $sortable = [
            'updated_at' => 'updated_at',
            'title' => 'title',
            'status' => 'status',
            'id' => 'id',
        ];
        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: ['status', 'category_id'],
            sortable: $sortable,
            defaultSort: 'updated_at',
            defaultDir: 'desc',
            defaultPerPage: 25,
        );

        $documents = $this->knowledge->paginate([
            'search' => $list['search'],
            'status' => $list['filters']['status'] ?? null,
            'category_id' => $list['filters']['category_id'] ?? null,
            'per_page' => $list['per_page'],
            'sort' => $list['sort'],
            'dir' => $list['dir'],
        ]);

        return view('pages.staff.knowledge.index', [
            'documents' => $documents,
            'categories' => KnowledgeCategory::query()->orderBy('sort_order')->get(),
            'filters' => array_merge($list['filters'], [
                'search' => $list['search'],
                'sort' => $list['sort'],
                'dir' => $list['dir'],
            ]),
            'list' => $list,
            'activeFilterCount' => $list['active_filter_count'],
            'sortable' => [
                'updated_at' => 'Updated',
                'title' => 'Title',
                'status' => 'Status',
                'id' => 'ID',
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->assertStaff($request);

        return view('pages.staff.knowledge.form', [
            'document' => null,
            'categories' => KnowledgeCategory::query()->orderBy('sort_order')->get(),
            'body' => old('body'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertStaff($request);
        $validated = $this->validateDocument($request, true);

        try {
            $document = $this->knowledge->create($request->user(), $validated, $request->file('file'));
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('knowledge.show', $document->id)->with('status', 'Knowledge document created and indexed.');
    }

    public function show(Request $request, int $id): View
    {
        $this->assertStaff($request);
        $document = $this->knowledge->findOrFail($id);
        $document->load(['category', 'creator', 'updater', 'versions']);
        $document->loadCount([
            'chunks as active_chunks_count' => fn ($query) => $query->where('active', true),
        ]);

        return view('pages.staff.knowledge.show', [
            'document' => $document,
            'body' => $document->currentVersionRecord()?->body,
            'payload' => $this->knowledge->show($document),
        ]);
    }

    public function edit(Request $request, int $id): View
    {
        $this->assertStaff($request);
        $document = $this->knowledge->findOrFail($id);

        return view('pages.staff.knowledge.form', [
            'document' => $document,
            'categories' => KnowledgeCategory::query()->orderBy('sort_order')->get(),
            'body' => old('body', $document->currentVersionRecord()?->body),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->assertStaff($request);
        $document = $this->knowledge->findOrFail($id);
        $validated = $this->validateDocument($request, false);

        try {
            $this->knowledge->update($request->user(), $document, $validated, $request->file('file'));
        } catch (HttpException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('knowledge.show', $id)->with('status', 'Knowledge document updated.');
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $this->assertStaff($request);
        $document = $this->knowledge->findOrFail($id);
        $next = $document->status === KnowledgeDocument::STATUS_ACTIVE
            ? KnowledgeDocument::STATUS_INACTIVE
            : KnowledgeDocument::STATUS_ACTIVE;
        $this->knowledge->setStatus($request->user(), $document, $next);

        return back()->with('status', 'Document marked '.$next.'.');
    }

    public function reindex(Request $request, int $id): RedirectResponse
    {
        $this->assertStaff($request);
        $this->knowledge->reindex($this->knowledge->findOrFail($id));

        return back()->with('status', 'Document re-indexed.');
    }

    public function categories(Request $request): View
    {
        $this->assertStaff($request);

        $list = \App\Support\ListQuery::from(
            $request,
            filterKeys: [],
            sortable: ['sort_order' => 'sort_order', 'name' => 'name'],
            defaultSort: 'sort_order',
            defaultDir: 'asc',
            defaultPerPage: 50,
        );

        $query = KnowledgeCategory::query()->withCount('documents');
        if ($list['search']) {
            $term = $list['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }
        $sortCol = in_array($list['sort'], ['name', 'sort_order'], true) ? $list['sort'] : 'sort_order';
        $categories = $query->orderBy($sortCol, $list['dir'])->paginate($list['per_page'])->withQueryString();

        return view('pages.staff.knowledge.categories', [
            'categories' => $categories,
            'list' => $list,
            'filters' => ['search' => $list['search'], 'sort' => $list['sort'], 'dir' => $list['dir']],
            'activeFilterCount' => $list['active_filter_count'],
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->assertStaff($request);
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:knowledge_categories,slug'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $this->knowledge->ensureCategory($validated);

        return back()->with('status', 'Category saved.');
    }

    public function test(): RedirectResponse
    {
        return redirect()->route('knowledge.chat');
    }

    public function chat(Request $request): View
    {
        $this->assertStaff($request);

        return view('pages.staff.knowledge.chat', [
            'conversations' => $this->assistant->listConversations($request->user(), 30, 'staff-test'),
            'suggestions' => $this->coach->suggestedQuestions(),
        ]);
    }

    public function chatSend(Request $request): JsonResponse
    {
        $this->assertStaff($request);
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        return response()->json($this->assistant->chat(
            $request->user(),
            $validated['message'],
            isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
            $request->ip(),
            'staff-test',
        ));
    }

    public function chatShow(Request $request, int $id): JsonResponse
    {
        $this->assertStaff($request);
        $conversation = $this->assistant->showConversation($request->user(), $id, 'staff-test');

        if ($conversation === null) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        return response()->json($conversation);
    }

    public function chatDestroy(Request $request, int $id): JsonResponse
    {
        $this->assertStaff($request);

        if (! $this->assistant->deleteConversation($request->user(), $id, 'staff-test')) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDocument(Request $request, bool $creating): array
    {
        $availability = $request->input('availability', 'always');
        if (! in_array($availability, ['always', 'start', 'end', 'range'], true)) {
            $availability = 'always';
        }
        $start = $request->input('effective_date');
        $end = $request->input('expires_date');
        $request->merge([
            'effective_at' => in_array($availability, ['start', 'range'], true) && filled($start)
                ? $start.' 00:00:00'
                : null,
            'expires_at' => in_array($availability, ['end', 'range'], true) && filled($end)
                ? $end.' 23:59:59'
                : null,
        ]);

        $maxKb = (int) config('rag.max_upload_kb', 2048);
        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:knowledge_categories,id'],
            'department' => ['nullable', 'string', 'max:40'],
            'service_type' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'in:active,inactive,draft'],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'body' => [$creating ? 'nullable' : 'nullable', 'string', 'max:100000'],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:txt,md,html,htm,csv'],
        ];

        $validated = $request->validate($rules);

        if ($creating && blank($validated['body'] ?? null) && ! $request->hasFile('file')) {
            throw new HttpException(422, 'Provide body text or an uploaded document.');
        }

        return $validated;
    }

    private function assertStaff(Request $request): void
    {
        if ($request->user() === null || ! $request->user()->canManageTickets()) {
            abort(403, 'You are not allowed to manage the knowledge base.');
        }
    }
}
