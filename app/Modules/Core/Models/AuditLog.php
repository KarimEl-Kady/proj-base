<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Exceptions\MissingTenantContextException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;

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

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (! has_tenancy() || Context::get('tenancy_bypass') === true) {
                return;
            }

            $tenantId = Context::get('tenant_id');

            if ($tenantId === null) {
                // Matches HasTenantScope's strict-mode escape hatch — without
                // this check, PROJECT_TENANCY_STRICT=false stops being a
                // legacy fail-open switch the moment any Auditable model is
                // queried, since this scope would still throw regardless of
                // the flag the exception message itself tells the operator
                // to set.
                if (config('project.tenancy.strict', true)) {
                    throw MissingTenantContextException::for(self::class, 'query');
                }

                return;
            }

            $builder->where($builder->qualifyColumn('tenant_id'), $tenantId);
        });
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
