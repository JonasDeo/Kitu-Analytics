<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'business_id', 'name', 'phone', 'contact_person', 'note',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function invoices() { return $this->hasMany(SupplierInvoice::class); }

    public function totalOwed(): float
    {
        return (float) $this->invoices()->whereIn('status', ['unpaid', 'partial'])->sum('balance_due');
    }
}