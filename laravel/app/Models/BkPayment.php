<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkPayment extends Model
{
    protected $fillable = [
        'business_id', 'sale_id', 'customer_id',
        'amount', 'method', 'is_partial', 'reference', 'note', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_partial' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function sale() { return $this->belongsTo(Sale::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
}