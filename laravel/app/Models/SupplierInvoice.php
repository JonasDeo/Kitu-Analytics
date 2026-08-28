<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInvoice extends Model
{
    protected $fillable = [
        'business_id', 'supplier_id', 'amount', 'amount_paid',
        'balance_due', 'due_date', 'status', 'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function business() { return $this->belongsTo(Business::class); }
    public function payments() { return $this->hasMany(SupplierPayment::class); }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['unpaid', 'partial'])
                     ->where('due_date', '<', today());
    }
}