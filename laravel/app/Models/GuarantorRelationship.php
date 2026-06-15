<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuarantorRelationship extends Model
{
    protected $fillable = [
        'guarantor_business_id',
        'beneficiary_business_id',
        'trust_score',
        'shared_transactions_count',
        'total_shared_volume',
        'status',
        'group_name',
        'group_type',
        'vouched_at',
    ];

    protected $casts = [
        'trust_score' => 'decimal:2',
        'total_shared_volume' => 'decimal:2',
        'vouched_at' => 'datetime',
    ];

    public function guarantor()
    {
        return $this->belongsTo(Business::class, 'guarantor_business_id');
    }

    public function beneficiary()
    {
        return $this->belongsTo(Business::class, 'beneficiary_business_id');
    }
}