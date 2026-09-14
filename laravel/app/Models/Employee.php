<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [
        'business_id', 'branch_id', 'user_id', 'name',
        'phone', 'role', 'permissions', 'is_active', 'hired_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active'   => 'boolean',
        'hired_at'    => 'date',
    ];

    public function business() { return $this->belongsTo(Business::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function user()     { return $this->belongsTo(User::class); }
    public function shifts()   { return $this->hasMany(Shift::class); }

    public function activeShift(): ?Shift
    {
        return $this->shifts()->whereNull('clock_out')->latest('clock_in')->first();
    }

    public function isClockedIn(): bool
    {
        return $this->activeShift() !== null;
    }

    public function totalSalesThisMonth(): float
    {
        return (float) $this->shifts()
            ->whereMonth('clock_in', now()->month)
            ->sum('total_sales');
    }
}