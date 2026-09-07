<?php

namespace App\Services;

use App\Models\MemberProfile;
use App\Models\User;

class MemberProfileService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsert(User $user, array $payload): MemberProfile
    {
        $address = $this->composeAddress($payload);

        $profile = MemberProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'region_code' => $payload['region_code'] ?? null,
                'region_name' => $payload['region_name'],
                'province_code' => $payload['province_code'] ?? null,
                'province_name' => $payload['province_name'] ?? null,
                'city_municipality_code' => $payload['city_municipality_code'] ?? null,
                'city_municipality_name' => $payload['city_municipality_name'],
                'barangay_code' => $payload['barangay_code'] ?? null,
                'barangay_name' => $payload['barangay_name'],
                'street' => $payload['street'] ?? null,
                'sitio' => $payload['sitio'] ?? null,
                'civil_status' => $payload['civil_status'],
                'sex' => $payload['sex'],
                'contact_no' => $payload['contact_no'],
                'date_of_seminar' => $payload['date_of_seminar'] ?? null,
                'remarks' => $payload['remarks'] ?? null,
                'address' => $address,
            ]
        );

        $user->forceFill([
            'contact_no' => $payload['contact_no'],
        ])->save();

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function composeAddress(array $payload): string
    {
        $parts = array_filter([
            trim((string) ($payload['street'] ?? '')),
            trim((string) ($payload['sitio'] ?? '')),
            trim((string) ($payload['barangay_name'] ?? '')),
            trim((string) ($payload['city_municipality_name'] ?? '')),
            trim((string) ($payload['province_name'] ?? '')),
            trim((string) ($payload['region_name'] ?? '')),
        ], fn (string $part) => $part !== '');

        return implode(', ', $parts);
    }
}
