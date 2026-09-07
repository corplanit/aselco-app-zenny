<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberProfile extends Model
{
    protected $fillable = [
        'user_id',
        'region_code',
        'region_name',
        'province_code',
        'province_name',
        'city_municipality_code',
        'city_municipality_name',
        'barangay_code',
        'barangay_name',
        'street',
        'sitio',
        'civil_status',
        'sex',
        'contact_no',
        'date_of_seminar',
        'remarks',
        'address',
    ];

    protected $casts = [
        'date_of_seminar' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function civilStatusLabel(): string
    {
        return match ($this->civil_status) {
            'single' => 'Single',
            'married' => 'Married',
            'widowed' => 'Widowed',
            'separated' => 'Separated',
            'divorced' => 'Divorced',
            default => (string) $this->civil_status,
        };
    }

    public function sexLabel(): string
    {
        return match ($this->sex) {
            'male' => 'Male',
            'female' => 'Female',
            default => (string) $this->sex,
        };
    }
}
