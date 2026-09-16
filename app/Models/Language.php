<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    // Assume that you have an 'active' column in your languages table
    protected $fillable = ['name', 'code', 'translated_text', 'active'];

    /**
     * Scope to filter active languages
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1); // Assuming 'active' column holds a boolean or 1/0 value
    }
}
