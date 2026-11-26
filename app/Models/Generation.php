<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Generation extends Model
{
    protected $fillable = [
        'user_id',
        'pixelforge_id',
        'idempotency_key',
        'prompt',
        'size',
        'quality',
        'reference_urls',
        'status',
        'image_url',
        'error_message',
        'provider_cost',
        'estimated_cost',
        'actual_cost',
        'provider_updated_at',
        'completed_at',
    ];

    protected $casts = [
        'reference_urls' => 'array',
        'provider_cost' => 'decimal:8',
        'estimated_cost' => 'decimal:8',
        'actual_cost' => 'decimal:8',
        'provider_updated_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['succeeded', 'failed'], true);
    }

    public function creditTransactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}