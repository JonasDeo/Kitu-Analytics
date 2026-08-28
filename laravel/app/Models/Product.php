<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'business_id', 'name', 'sku', 'barcode', 'unit',
        'category', 'cost_price', 'sale_price', 'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function stockLevels() { return $this->hasMany(StockLevel::class); }
    public function stockMovements() { return $this->hasMany(StockMovement::class); }
    public function saleItems() { return $this->hasMany(SaleItem::class); }

    public function stockForBranch(int $branchId): ?StockLevel
    {
        return $this->stockLevels()->where('branch_id', $branchId)->first();
    }

    public function profitMargin(): float
    {
        if ($this->sale_price <= 0) return 0;
        return round((($this->sale_price - $this->cost_price) / $this->sale_price) * 100, 2);
    }
}