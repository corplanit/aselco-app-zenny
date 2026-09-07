<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'sort_order',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'category_id');
    }
}
