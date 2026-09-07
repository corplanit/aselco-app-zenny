<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberProfileRequest extends FormRequest
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
            'region_code' => ['nullable', 'string', 'max:20'],
            'region_name' => ['required', 'string', 'max:120'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'province_name' => ['nullable', 'string', 'max:120'],
            'city_municipality_code' => ['nullable', 'string', 'max:20'],
            'city_municipality_name' => ['required', 'string', 'max:120'],
            'barangay_code' => ['nullable', 'string', 'max:20'],
            'barangay_name' => ['required', 'string', 'max:120'],
            'street' => ['nullable', 'string', 'max:255'],
            'sitio' => ['nullable', 'string', 'max:255'],
            'civil_status' => ['required', 'string', Rule::in(['single', 'married', 'widowed', 'separated', 'divorced'])],
            'sex' => ['required', 'string', Rule::in(['male', 'female'])],
            'contact_no' => ['required', 'string', 'max:40'],
            'date_of_seminar' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
