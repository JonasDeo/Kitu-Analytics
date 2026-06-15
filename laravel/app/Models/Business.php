<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'industry',
        'location',
        'phone',
        'monthly_revenue_estimate',
        'employee_count',
        'status',
    ];

    protected $casts = [
        'monthly_revenue_estimate' => 'decimal:2',
        'employee_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function creditScores()
    {
        return $this->hasMany(CreditScore::class);
    }

    public function latestCreditScore()
    {
        return $this->hasOne(CreditScore::class)->latestOfMany();
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function scoreAppeals()
    {
        return $this->hasMany(ScoreAppeal::class);
    }

    public function guarantorRelationships()
    {
        return $this->hasMany(GuarantorRelationship::class, 'guarantor_business_id');
    }

    public function beneficiaryRelationships()
    {
        return $this->hasMany(GuarantorRelationship::class, 'beneficiary_business_id');
    }
}