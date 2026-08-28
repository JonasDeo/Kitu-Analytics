<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    protected $fillable = [
        'business_id', 'branch_id', 'user_id', 'customer_id',
        'channel', 'total_amount', 'amount_paid', 'balance_owed',
        'is_partial', 'status', 'note', 'sold_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_owed' => 'decimal:2',
        'is_partial' => 'boolean',
        'sold_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SaleItem::class); }
    public function payments() { return $this->hasMany(BkPayment::class); }

    public function scopePartial($query) { return $query->where('is_partial', true); }
    public function scopeCompleted($query) { return $query->where('status', 'completed'); }
    public function scopeToday($query) { return $query->whereDate('sold_at', today()); }
}