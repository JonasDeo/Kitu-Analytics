<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lender extends Model
{
    protected $fillable = [
        'name',
        'type',
        'contact_email',
        'contact_phone',
        'api_key',
        'min_credit_score',
        'max_loan_amount',
        'status',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected $casts = [
        'max_loan_amount' => 'decimal:2',
        'min_credit_score' => 'integer',
    ];

    public function revenueEvents()
    {
        return $this->hasMany(RevenueEvent::class);
    }

    public function totalRevenue(): float
    {
        return $this->revenueEvents()
            ->where('status', 'paid')
            ->sum('amount_tzs');
    }
}