<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UploadTicketAttachmentRequest extends FormRequest
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
            'attachment' => [
                'required',
                'file',
                'max:'.config('tickets.attachment.max_kb', 10240),
                'mimes:'.implode(',', config('tickets.attachment.allowed_mimes', [])),
            ],
        ];
    }
}
