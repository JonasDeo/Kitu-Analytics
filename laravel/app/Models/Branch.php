<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'business_id', 'name', 'address', 'is_active', 'is_main',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function products() { return $this->hasManyThrough(StockLevel::class, Product::class, 'business_id', 'product_id'); }
    public function stockLevels() { return $this->hasMany(StockLevel::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function expenses() { return $this->hasMany(BkExpense::class); }
}