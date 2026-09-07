<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\KnowledgeSearchRequest;
use App\Http\Requests\Api\V1\StoreKnowledgeDocumentRequest;
use App\Http\Requests\Api\V1\UpdateKnowledgeDocumentRequest;
use App\Models\KnowledgeCategory;
use App\Services\Rag\KnowledgeBaseService;
use App\Services\Rag\KnowledgeRetriever;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminKnowledgeController extends Controller
{
    public function __construct(
        private KnowledgeBaseService $knowledge,
        private KnowledgeRetriever $retriever,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:active,inactive,draft'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->knowledge->paginate($validated);
        $paginator->getCollection()->transform(fn ($doc) => $this->knowledge->shape($doc));

        return response()->json($paginator);
    }

    public function store(StoreKnowledgeDocumentRequest $request): JsonResponse
    {
        $document = $this->knowledge->create(
            $request->user(),
            $request->validated(),
            $request->file('file')
        );

        return response()->json($this->knowledge->show($document), 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->knowledge->show($this->knowledge->findOrFail($id)));
    }

    public function update(UpdateKnowledgeDocumentRequest $request, int $id): JsonResponse
    {
        $document = $this->knowledge->update(
            $request->user(),
            $this->knowledge->findOrFail($id),
            $request->validated(),
            $request->file('file')
        );

        return response()->json($this->knowledge->show($document));
    }

    public function destroy(int $id): JsonResponse
    {
        $this->knowledge->delete($this->knowledge->findOrFail($id));

        return response()->json([
            'message' => 'Knowledge document removed from the active catalog.',
            'code' => 'KNOWLEDGE_DELETED',
        ]);
    }

    public function indexDocument(Request $request, int $id): JsonResponse
    {
        $document = $this->knowledge->reindex($this->knowledge->findOrFail($id));

        return response()->json([
            'message' => 'Document re-indexed.',
            'document' => $this->knowledge->shape($document),
        ]);
    }

    public function search(KnowledgeSearchRequest $request): JsonResponse
    {
        if ($request->filled('limit')) {
            config(['rag.top_k' => (int) $request->integer('limit')]);
        }

        $retrieval = $this->retriever->retrieve(
            $request->validated('query'),
            'admin-search',
            $request->user(),
            $request->ip(),
        );

        return response()->json([
            'query' => $request->validated('query'),
            'sufficient' => $retrieval['sufficient'],
            'top_score' => round($retrieval['top_score'], 4),
            'hits' => array_map(fn ($hit) => $hit->toArray(), $retrieval['hits']),
        ]);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => KnowledgeCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
