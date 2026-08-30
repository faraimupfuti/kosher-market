<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'seller_id', 'shop_id', 'price', 'stock', 'status', 'slug', 'currency', 'SKU',
        'weight', 'dimensions', 'product_type', 'image_url', 'vendor_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            $product->currency = 'BTC';
        });

        static::updating(function (Product $product) {
            $product->currency = 'BTC';
        });
    }

    public function translations() { return $this->hasMany(ProductTranslation::class); }
    public function translation() { return $this->hasOne(ProductTranslation::class)->where('language_code', App::getLocale()); }
    public function category() { return $this->belongsTo(Category::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function orders() { return $this->hasMany(Order::class); }

    public function getTranslation($field, $locale = 'en')
    {
        $translation = $this->translations->firstWhere('language_code', $locale);
        return $translation ? $translation->$field : null;
    }

    public function thumbnail() { return $this->hasOne(ProductImage::class)->where('type', 'thumb'); }
    public function reviews() { return $this->hasMany(ProductReview::class)->approved()->latest(); }
    public function averageRating() { return $this->reviews()->avg('rating') ?: 0; }
    public function getConvertedPriceAttribute() { return convert_price($this->price); }
    public function getConvertedDiscountPriceAttribute() { return $this->discount_price ? convert_price($this->discount_price) : null; }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function attributeValues() { return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')->with('attribute', 'translations'); }
    public function primaryVariant() { return $this->hasOne(ProductVariant::class)->where('is_primary', true); }
    public function wishlistedBy() { return $this->belongsToMany(Customer::class, 'wishlists'); }
}
