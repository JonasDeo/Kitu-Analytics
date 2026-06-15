<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'granted',
        'consent_version',
        'consent_text_shown',
        'channel',
        'ip_address',
        'granted_at',
        'withdrawn_at',
    ];

    protected $casts = [
        'granted' => 'boolean',
        'granted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->granted && is_null($this->withdrawn_at);
    }
}