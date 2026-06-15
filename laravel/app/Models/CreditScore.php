<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'score',
        'grade',
        'transaction_frequency_score',
        'cash_flow_stability_score',
        'network_health_score',
        'repayment_likelihood',
        'factors',
        'calculated_at',
    ];

    protected $casts = [
        'factors' => 'array',
        'calculated_at' => 'datetime',
        'transaction_frequency_score' => 'decimal:2',
        'cash_flow_stability_score' => 'decimal:2',
        'network_health_score' => 'decimal:2',
        'repayment_likelihood' => 'decimal:2',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function explanations()
    {
        return $this->hasMany(ScoreExplanation::class);
    }

    public function appeals()
    {
        return $this->hasMany(ScoreAppeal::class);
    }

    public function getGradeAttribute(): string
    {
        return match(true) {
            $this->score >= 800 => 'A',
            $this->score >= 650 => 'B',
            $this->score >= 500 => 'C',
            $this->score >= 350 => 'D',
            default => 'F',
        };
    }
}