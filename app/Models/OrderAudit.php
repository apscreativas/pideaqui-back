<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'user_id',
        'action',
        'changes',
        'reason',
        'old_total',
        'new_total',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'old_total' => 'decimal:2',
            'new_total' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
