<?php

return [
    'enabled' => (bool) env('RAG_ENABLED', true),
    'top_k' => (int) env('RAG_TOP_K', 4),
    'min_score' => (float) env('RAG_MIN_SCORE', 0.18),
    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 700),
    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 80),
    'hybrid_lexical_weight' => (float) env('RAG_LEXICAL_WEIGHT', 0.6),
    'hybrid_vector_weight' => (float) env('RAG_VECTOR_WEIGHT', 0.4),
    'embedding' => [
        'provider' => env('RAG_EMBEDDING_PROVIDER', 'openai'),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'timeout' => (int) env('RAG_EMBEDDING_TIMEOUT', 20),
        'retries' => (int) env('RAG_EMBEDDING_RETRIES', 2),
        'local_dimensions' => (int) env('RAG_LOCAL_EMBED_DIM', 128),
    ],
    'disk' => env('RAG_DISK', 'local'),
    'directory' => 'knowledge',
    'max_upload_kb' => (int) env('RAG_MAX_UPLOAD_KB', 2048),
    'allowed_mimes' => ['txt', 'md', 'html', 'htm', 'csv'],
];
