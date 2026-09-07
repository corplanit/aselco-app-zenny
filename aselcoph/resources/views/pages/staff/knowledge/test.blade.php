<x-app-layout>
    <x-slot name="title">Knowledge retrieval test</x-slot>
    <x-slot name="url_1">{"link": "{{ route('knowledge.dashboard') }}", "text": "Knowledge"}</x-slot>
    <x-slot name="url_2">{"link": "{{ route('knowledge.test') }}", "text": "Retrieval test"}</x-slot>
    <x-slot name="active">Test RAG independently of chat</x-slot>
    <x-slot name="buttons">
        <a href="{{ route('knowledge.dashboard') }}" class="ti-btn ti-btn-light ti-btn-sm">Dashboard</a>
    </x-slot>

    <div class="box mb-4">
        <div class="box-body">
            <p class="text-sm text-textmuted mb-3">
                Runs query processing and hybrid retrieval only — no OpenAI chat completion.
                Use this to verify ranking before relying on the mobile assistant.
            </p>
            <form method="POST" action="{{ route('knowledge.test') }}">
                @csrf
                <label class="ti-form-label">Test question</label>
                <textarea name="query" class="ti-form-input ul-composer-skip" rows="3" required maxlength="2000">{{ old('query', $result['query'] ?? '') }}</textarea>
                <button class="ti-btn ti-btn-primary ti-btn-sm mt-3">Retrieve</button>
            </form>
        </div>
    </div>

    @if($result)
        <div class="box">
            <div class="box-header">
                <div class="box-title">
                    Results —
                    {{ $result['sufficient'] ? 'sufficient' : 'insufficient' }}
                    (top score {{ number_format($result['top_score'], 4) }})
                </div>
            </div>
            <div class="box-body">
                @forelse($result['hits'] as $hit)
                    <div class="border rounded p-3 mb-3">
                        <div class="flex justify-between gap-2 mb-2">
                            <strong>{{ $hit->title }}</strong>
                            <span class="text-sm">score {{ number_format($hit->score, 4) }}</span>
                        </div>
                        <p class="text-xs text-textmuted mb-2">
                            {{ $hit->metadata['category_name'] ?? '' }}
                            · {{ $hit->metadata['department'] ?? '' }}
                            · v{{ $hit->metadata['version'] ?? '' }}
                        </p>
                        <pre class="whitespace-pre-wrap text-sm mb-0">{{ $hit->content }}</pre>
                    </div>
                @empty
                    <p class="text-textmuted mb-0">No chunks passed the relevance threshold. The assistant should not fabricate an answer.</p>
                @endforelse
            </div>
        </div>
    @endif
</x-app-layout>
