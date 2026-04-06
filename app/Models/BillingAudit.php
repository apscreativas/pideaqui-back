<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'restaurant_id',
        'actor_type',
        'actor_id',
        'action',
        'payload',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public static function log(
        string $action,
        ?int $restaurantId = null,
        string $actorType = 'system',
        ?int $actorId = null,
        ?array $payload = null,
        ?string $ipAddress = null,
    ): self {
        return static::query()->create([
            'restaurant_id' => $restaurantId,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'payload' => $payload,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
