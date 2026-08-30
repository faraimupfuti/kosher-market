<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = ['code', 'name', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_shipping_countries');
    }
}
