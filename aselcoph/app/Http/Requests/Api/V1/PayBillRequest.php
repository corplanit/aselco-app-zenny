<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PayBillRequest extends FormRequest
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
            'billing_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
