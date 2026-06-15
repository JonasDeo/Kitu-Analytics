<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScoreAppeal extends Model
{
    protected $fillable = [
        'business_id',
        'credit_score_id',
        'reason',
        'status',
        'resolution_notes',
        'resolved_by',
        'score_before',
        'score_after',
        'resolved_at',
        'due_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    public function creditScore()
    {
        return $this->belongsTo(CreditScore::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at && now()->gt($this->due_at) && $this->status === 'pending';
    }
}