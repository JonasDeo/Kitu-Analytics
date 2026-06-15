<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreExplanation extends Model
{
    protected $fillable = [
        'credit_score_id',
        'business_id',
        'factor',
        'impact',
        'weight',
        'explanation_en',
        'explanation_sw',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
    ];

    public function creditScore()
    {
        return $this->belongsTo(CreditScore::class);
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}