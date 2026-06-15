<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'is_verified',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'is_verified' => 'boolean',
    ];

    public function businesses()
    {
        return $this->hasMany(Business::class);
    }

    public function consentRecords()
    {
        return $this->hasMany(ConsentRecord::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasConsented(string $type): bool
    {
        return $this->consentRecords()
            ->where('consent_type', $type)
            ->where('granted', true)
            ->whereNull('withdrawn_at')
            ->exists();
    }
}