<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdminTicketRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:ticket_categories,id'],
            'subcategory' => ['nullable', 'string', 'max:160'],
            'channel' => ['required', 'in:call,text,social/sms,app'],
            'description' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
        ];
    }
}
