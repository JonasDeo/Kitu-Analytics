<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    protected $fillable = [
        'employee_id', 'branch_id', 'business_id',
        'clock_in', 'clock_out', 'total_sales', 'sales_count', 'note',
    ];

    protected $casts = [
        'clock_in'    => 'datetime',
        'clock_out'   => 'datetime',
        'total_sales' => 'decimal:2',
    ];

    public function employee() { return $this->belongsTo(Employee::class); }
    public function branch()   { return $this->belongsTo(Branch::class); }
    public function business() { return $this->belongsTo(Business::class); }

    public function durationMinutes(): int
    {
        $end = $this->clock_out ?? now();
        return (int) $this->clock_in->diffInMinutes($end);
    }

    public function durationFormatted(): string
    {
        $mins = $this->durationMinutes();
        $h    = intdiv($mins, 60);
        $m    = $mins % 60;
        return "{$h}h {$m}m";
    }
}