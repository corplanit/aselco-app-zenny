<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'head_user_id',
        'contact',
        'operating_schedule',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'operating_schedule' => 'array',
    ];

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_users')
            ->withPivot(['is_primary', 'assigned_by'])
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'department_permissions');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_department', 'code');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
