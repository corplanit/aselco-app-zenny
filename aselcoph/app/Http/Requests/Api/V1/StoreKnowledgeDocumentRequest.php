<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageTickets() === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('rag.max_upload_kb', 2048);

        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', 'exists:knowledge_categories,id'],
            'department' => ['nullable', 'string', 'max:40'],
            'service_type' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'draft'])],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_at'],
            'body' => ['nullable', 'string', 'max:100000'],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', config('rag.allowed_mimes', ['txt', 'md', 'html', 'htm', 'csv']))],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->filled('body') && ! $this->hasFile('file')) {
                $validator->errors()->add('body', 'Provide body text or an uploaded document.');
            }
        });
    }
}
