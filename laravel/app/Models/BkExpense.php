<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BkExpense extends Model
{
    protected $fillable = [
        'business_id', 'branch_id', 'user_id',
        'category', 'amount', 'note', 'expensed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expensed_at' => 'datetime',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function user() { return $this->belongsTo(User::class); }
}