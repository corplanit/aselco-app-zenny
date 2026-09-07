<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AdminAiAssistRequest extends FormRequest
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
        return [
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
        ];
    }
}
