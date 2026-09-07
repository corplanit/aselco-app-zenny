<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketCategory extends Model
{
    protected $fillable = [
        'name',
        'department_code',
        'default_assignee_role',
        'sla_minutes',
        'requires_payment_check',
        'requires_tsd_check',
    ];

    protected $casts = [
        'requires_payment_check' => 'boolean',
        'requires_tsd_check' => 'boolean',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'category_id');
    }
}
