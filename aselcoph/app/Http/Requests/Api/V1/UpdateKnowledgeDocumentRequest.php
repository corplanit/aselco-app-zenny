<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeDocumentRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:knowledge_categories,id'],
            'department' => ['nullable', 'string', 'max:40'],
            'service_type' => ['nullable', 'string', 'max:80'],
            'source' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'draft'])],
            'effective_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'body' => ['nullable', 'string', 'max:100000'],
            'file' => ['nullable', 'file', 'max:'.$maxKb, 'mimes:'.implode(',', config('rag.allowed_mimes', ['txt', 'md', 'html', 'htm', 'csv']))],
        ];
    }
}
