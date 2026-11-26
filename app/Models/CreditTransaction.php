<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'generation_id',
        'type', // 'topup', 'generation_charge', 'refund'
        'amount',
        'balance_after',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_after' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}