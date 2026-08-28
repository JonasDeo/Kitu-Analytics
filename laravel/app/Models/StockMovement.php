<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'user_id', 'type',
        'quantity_change', 'quantity_after', 'reference', 'note', 'moved_at',
    ];

    protected $casts = [
        'quantity_change' => 'decimal:3',
        'quantity_after' => 'decimal:3',
        'moved_at' => 'datetime',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function user() { return $this->belongsTo(User::class); }
}