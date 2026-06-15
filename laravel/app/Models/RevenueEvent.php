<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueEvent extends Model
{
    protected $fillable = [
        'lender_id',
        'event_type',
        'reference',
        'amount_tzs',
        'amount_usd',
        'currency',
        'status',
        'metadata',
        'billed_at',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount_tzs' => 'decimal:2',
        'amount_usd' => 'decimal:4',
        'billed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function lender()
    {
        return $this->belongsTo(Lender::class);
    }
}