<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AccessSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('access_settings')) {
                return config('access.'.$key, $default);
            }
        } catch (\Throwable) {
            return config('access.'.$key, $default);
        }

        $stored = Cache::remember('access_setting.'.$key, 60, function () use ($key) {
            return static::query()->where('key', $key)->value('value');
        });

        if ($stored === null) {
            return config('access.'.$key, $default);
        }

        $decoded = json_decode($stored, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $stored;
    }

    public static function putValue(string $key, mixed $value, ?int $actorId = null): self
    {
        $encoded = is_string($value) ? $value : json_encode($value);

        $row = static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $encoded, 'updated_by' => $actorId],
        );

        Cache::forget('access_setting.'.$key);

        return $row;
    }
}
