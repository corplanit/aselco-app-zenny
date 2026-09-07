<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAiEscalateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'conversation_id' => ['nullable', 'integer', 'exists:ai_conversations,id'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'category_hint' => ['nullable', 'string', 'in:TSD,COMD,CCAD,AO-CDS,ISD-CCSMDD,FOCAL'],
            'message' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
