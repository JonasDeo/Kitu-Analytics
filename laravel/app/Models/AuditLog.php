<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event',
        'auditable_type',
        'auditable_id',
        'user_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'model_version',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::creating(function ($log) {
            $last = static::latest('created_at')->value('hash');
            $log->created_at = now();
            $log->hash = hash('sha256', ($last ?? '') . json_encode($log->new_values) . $log->created_at);
        });
    }
}