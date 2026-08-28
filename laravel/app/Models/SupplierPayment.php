<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    protected $fillable = [
        'supplier_invoice_id', 'business_id',
        'amount', 'settled_from', 'reference', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice() { return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id'); }
    public function business() { return $this->belongsTo(Business::class); }
}