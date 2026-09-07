<?php

namespace App\Services\Rag;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeDocumentVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class KnowledgeBaseService
{
    public function __construct(
        private DocumentProcessor $processor,
        private QueryProcessor $queryProcessor,
        private EmbeddingEncoder $encoder,
    ) {}

    public function dashboard(): array
    {
        $documents = KnowledgeDocument::query()->count();
        $active = KnowledgeDocument::query()->where('status', KnowledgeDocument::STATUS_ACTIVE)->count();
        $inactive = KnowledgeDocument::query()->where('status', KnowledgeDocument::STATUS_INACTIVE)->count();
        $drafts = KnowledgeDocument::query()->where('status', KnowledgeDocument::STATUS_DRAFT)->count();
        $chunks = KnowledgeChunk::query()->where('active', true)->count();
        $pending = KnowledgeDocumentVersion::query()->whereNull('indexed_at')->count();

        return [
            'documents' => $documents,
            'active' => $active,
            'inactive' => $inactive,
            'drafts' => $drafts,
            'chunks' => $chunks,
            'categories' => KnowledgeCategory::query()->count(),
            'pending_index' => $pending,
            'active_rate' => $documents > 0 ? round(($active / $documents) * 100, 1) : 0,
            'index_ready_rate' => ($chunks + $pending) > 0
                ? round(($chunks / ($chunks + $pending)) * 100, 1)
                : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 20)));
        $sortable = [
            'updated_at' => 'updated_at',
            'title' => 'title',
            'status' => 'status',
            'id' => 'id',
        ];
        $sort = (string) ($filters['sort'] ?? 'updated_at');
        $column = $sortable[$sort] ?? 'updated_at';
        $dir = strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return KnowledgeDocument::query()
            ->with(['category', 'creator:id,name', 'updater:id,name'])
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', '%'.$search.'%')
                        ->orWhere('department', 'like', '%'.$search.'%')
                        ->orWhere('source', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($column, $dir)
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload, ?UploadedFile $file = null): KnowledgeDocument
    {
        $body = $this->resolveBody($payload['body'] ?? '', $file);

        return DB::transaction(function () use ($actor, $payload, $file, $body) {
            $document = KnowledgeDocument::query()->create([
                'category_id' => $payload['category_id'],
                'title' => $payload['title'],
                'department' => $payload['department'] ?? null,
                'service_type' => $payload['service_type'] ?? null,
                'source' => $payload['source'] ?? 'staff',
                'status' => $payload['status'] ?? KnowledgeDocument::STATUS_ACTIVE,
                'current_version' => 1,
                'effective_at' => $payload['effective_at'] ?? null,
                'expires_at' => $payload['expires_at'] ?? null,
                'file_path' => $file ? $this->storeFile($file) : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->writeVersionAndIndex($document, $body, $actor, 1);

            return $document->fresh(['category', 'versions']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, KnowledgeDocument $document, array $payload, ?UploadedFile $file = null): KnowledgeDocument
    {
        $bodyInput = $payload['body'] ?? null;
        $newBody = null;
        if ($file !== null || (is_string($bodyInput) && $bodyInput !== '')) {
            $newBody = $this->resolveBody((string) $bodyInput, $file);
        }

        return DB::transaction(function () use ($actor, $document, $payload, $file, $newBody) {
            $document->fill([
                'category_id' => $payload['category_id'] ?? $document->category_id,
                'title' => $payload['title'] ?? $document->title,
                'department' => array_key_exists('department', $payload) ? $payload['department'] : $document->department,
                'service_type' => array_key_exists('service_type', $payload) ? $payload['service_type'] : $document->service_type,
                'source' => $payload['source'] ?? $document->source,
                'status' => $payload['status'] ?? $document->status,
                'effective_at' => array_key_exists('effective_at', $payload) ? $payload['effective_at'] : $document->effective_at,
                'expires_at' => array_key_exists('expires_at', $payload) ? $payload['expires_at'] : $document->expires_at,
                'updated_by' => $actor->id,
            ]);

            $document->save();

            if ($file) {
                $document->file_path = $this->storeFile($file);
                $document->save();
            }

            if ($newBody !== null) {
                $next = $document->current_version + 1;
                $this->writeVersionAndIndex($document, $newBody, $actor, $next);
                $document->forceFill(['current_version' => $next])->save();
            } else {
                $this->syncChunkVisibility($document->fresh());
            }

            return $document->fresh(['category', 'versions']);
        });
    }

    public function delete(KnowledgeDocument $document): void
    {
        KnowledgeChunk::query()->where('document_id', $document->id)->update(['active' => false]);
        $document->delete();
    }

    public function setStatus(User $actor, KnowledgeDocument $document, string $status): KnowledgeDocument
    {
        $document->forceFill([
            'status' => $status,
            'updated_by' => $actor->id,
        ])->save();

        $this->syncChunkVisibility($document->fresh());

        return $document->fresh(['category']);
    }

    public function reindex(KnowledgeDocument $document): KnowledgeDocument
    {
        $version = $document->currentVersionRecord();
        if ($version === null) {
            throw new HttpException(422, 'Document has no version to index.');
        }

        $this->indexVersion($document, $version);

        return $document->fresh(['category']);
    }

    public function reindexAll(): int
    {
        $count = 0;
        KnowledgeDocument::query()->with('versions')->each(function (KnowledgeDocument $document) use (&$count) {
            $version = $document->currentVersionRecord();
            if ($version === null) {
                return;
            }
            $this->indexVersion($document, $version);
            $count++;
        });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    public function show(KnowledgeDocument $document): array
    {
        $document->load(['category', 'creator:id,name', 'updater:id,name', 'versions']);
        $current = $document->currentVersionRecord();

        return $this->shape($document) + [
            'body' => $current?->body,
            'versions' => $document->versions->sortByDesc('version')->values()->map(fn (KnowledgeDocumentVersion $version) => [
                'id' => $version->id,
                'version' => $version->version,
                'checksum' => $version->checksum,
                'indexed_at' => $version->indexed_at?->toIso8601String(),
                'created_at' => $version->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shape(KnowledgeDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'category' => $document->category?->only(['id', 'slug', 'name']),
            'department' => $document->department,
            'service_type' => $document->service_type,
            'source' => $document->source,
            'status' => $document->status,
            'version' => $document->current_version,
            'effective_at' => $document->effective_at?->toIso8601String(),
            'expiration_date' => $document->expires_at?->toIso8601String(),
            'created_by' => $document->creator?->name,
            'updated_by' => $document->updater?->name,
            'created_at' => $document->created_at?->toIso8601String(),
            'updated_at' => $document->updated_at?->toIso8601String(),
        ];
    }

    public function findOrFail(int $id): KnowledgeDocument
    {
        $document = KnowledgeDocument::query()->find($id);
        if ($document === null) {
            throw new HttpException(404, 'Knowledge document not found.');
        }

        return $document;
    }

    private function writeVersionAndIndex(KnowledgeDocument $document, string $body, User $actor, int $versionNumber): KnowledgeDocumentVersion
    {
        $version = KnowledgeDocumentVersion::query()->create([
            'document_id' => $document->id,
            'version' => $versionNumber,
            'body' => $body,
            'checksum' => hash('sha256', $body),
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);

        $this->indexVersion($document, $version);

        return $version;
    }

    private function indexVersion(KnowledgeDocument $document, KnowledgeDocumentVersion $version): void
    {
        KnowledgeChunk::query()->where('document_id', $document->id)->update(['active' => false]);
        KnowledgeChunk::query()->where('version_id', $version->id)->delete();

        $retrievable = $document->fresh()?->isRetrievable() ?? false;
        $chunks = $this->processor->chunk($version->body);

        foreach ($chunks as $index => $content) {
            KnowledgeChunk::query()->create([
                'document_id' => $document->id,
                'version_id' => $version->id,
                'chunk_index' => $index,
                'content' => $content,
                'tokens' => $this->queryProcessor->tokenize($document->title.' '.$content),
                'embedding' => $this->encoder->embed($document->title.' '.$content),
                'embedding_model' => $this->encoder->modelName(),
                'char_count' => mb_strlen($content),
                'active' => $retrievable,
                'created_at' => now(),
            ]);
        }

        $version->forceFill(['indexed_at' => now()])->save();
    }

    private function resolveBody(string $body, ?UploadedFile $file): string
    {
        $contents = null;
        $ext = null;
        if ($file) {
            $contents = (string) file_get_contents($file->getRealPath());
            $ext = $file->getClientOriginalExtension();
        }

        $clean = $this->processor->extractFromUpload($contents, $ext, $body);
        if ($clean === '') {
            throw new HttpException(422, 'Knowledge body is empty after cleaning.');
        }

        return $clean;
    }

    private function storeFile(UploadedFile $file): string
    {
        $dir = trim((string) config('rag.directory', 'knowledge'), '/');

        return $file->store($dir.'/'.now()->format('Y/m'), (string) config('rag.disk', 'local'));
    }

    private function syncChunkVisibility(KnowledgeDocument $document): void
    {
        KnowledgeChunk::query()->where('document_id', $document->id)->update(['active' => false]);

        if (! $document->isRetrievable()) {
            return;
        }

        $version = $document->currentVersionRecord();
        if ($version === null) {
            return;
        }

        KnowledgeChunk::query()
            ->where('document_id', $document->id)
            ->where('version_id', $version->id)
            ->update(['active' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ensureCategory(array $attributes): KnowledgeCategory
    {
        return KnowledgeCategory::query()->updateOrCreate(
            ['slug' => $attributes['slug']],
            [
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'sort_order' => $attributes['sort_order'] ?? 0,
            ]
        );
    }
}
