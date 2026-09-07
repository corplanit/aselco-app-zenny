<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'region_code' => $this->region_code,
            'region_name' => $this->region_name,
            'province_code' => $this->province_code,
            'province_name' => $this->province_name,
            'city_municipality_code' => $this->city_municipality_code,
            'city_municipality_name' => $this->city_municipality_name,
            'barangay_code' => $this->barangay_code,
            'barangay_name' => $this->barangay_name,
            'street' => $this->street,
            'sitio' => $this->sitio,
            'civil_status' => $this->civil_status,
            'sex' => $this->sex,
            'contact_no' => $this->contact_no,
            'date_of_seminar' => $this->date_of_seminar?->toDateString(),
            'remarks' => $this->remarks,
            'address' => $this->address,
        ];
    }
}
