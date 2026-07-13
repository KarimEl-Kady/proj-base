<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property int|string|null $tenant_id
 * @property string|null $request_id
 * @property Carbon $occurred_at
 * @property Carbon|null $published_at
 * @property Carbon $available_at
 * @property Carbon|null $claimed_at
 * @property string|null $claim_token
 * @property Carbon|null $failed_at
 * @property int $attempts
 * @property string|null $last_error
 */
class OutboxMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
