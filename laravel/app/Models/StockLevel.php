<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'quantity', 'low_stock_threshold',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'low_stock_threshold' => 'decimal:3',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function branch() { return $this->belongsTo(Branch::class); }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }
}