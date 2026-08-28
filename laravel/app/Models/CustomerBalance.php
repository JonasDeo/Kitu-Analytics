<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBalance extends Model
{
    protected $fillable = [
        'customer_id', 'business_id',
        'total_owed', 'total_credit', 'net_balance', 'last_transaction_at',
    ];

    protected $casts = [
        'total_owed' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'net_balance' => 'decimal:2',
        'last_transaction_at' => 'datetime',
    ];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function business() { return $this->belongsTo(Business::class); }
}