<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'image',
        'name',
        'price',
        'stock'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }


    public function scopeSearch($query, $search)
    {
        return $query->when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        });
    }

    public function scopeByCategory($query, $category_id)
    {
        return $query->when($category_id, function ($query, $category_id) {
            $query->where('product_category_id', $category_id);
        });
    }
}
