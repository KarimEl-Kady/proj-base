<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only by convention — nothing in this codebase updates or deletes
 * a row after it's written. Written by App\Modules\Core\Traits\Auditable;
 * see that trait for what gets recorded and what's filtered out.
 *
 * @property int $id
 * @property ?string $actor_type
 * @property int|string|null $actor_id
 * @property string $action
 * @property string $auditable_type
 * @property int|string $auditable_id
 * @property int|string|null $tenant_id
 * @property array{before: array<string, mixed>, after: array<string, mixed>} $changes
 * @property Carbon $occurred_at
 */
class AuditLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
