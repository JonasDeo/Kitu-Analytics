<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'business_id', 'branch_id', 'name', 'phone', 'note',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function sales() { return $this->hasMany(Sale::class); }
    public function payments() { return $this->hasMany(BkPayment::class); }
    public function balance() { return $this->hasOne(CustomerBalance::class); }

    public function totalOwed(): float
    {
        return (float) $this->balance?->net_balance ?? 0;
    }
}