<?php

namespace App\Modules\Core\Traits;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Opt-in "who changed this record and when" for any model: `use Auditable;`
 * and every create/update/delete writes an AuditLog row with a before/after
 * diff. Attributes in the model's own $hidden (passwords, tokens, secrets —
 * whatever the model already refuses to serialize) are never recorded; add
 * a model-specific `protected array $auditExclude = [...]` for anything else
 * that shouldn't end up in an audit trail.
 *
 * The actor is the currently authenticated user, or null — null means a
 * system/console-driven change (a seeder, an artisan command, a queued
 * job with no user in context), not a missing record.
 *
 * @phpstan-require-extends Model
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            static::recordAudit($model, 'created', [], $model->auditableFilter($model->getAttributes()));
        });

        static::updated(function (self $model): void {
            $after = $model->auditableFilter($model->getChanges());

            if ($after === []) {
                // getChanges() can be empty (e.g. a redundant save() or a
                // touch()) — nothing meaningful changed, nothing to log.
                return;
            }

            $before = $model->auditableFilter(array_intersect_key(
                $model->getOriginal(),
                $after,
            ));

            static::recordAudit($model, 'updated', $before, $after);
        });

        static::deleted(function (self $model): void {
            static::recordAudit($model, 'deleted', $model->auditableFilter($model->getAttributes()), []);
        });
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    protected static function recordAudit(self $model, string $action, array $before, array $after): void
    {
        $actor = Auth::user();

        AuditLog::query()->create([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'tenant_id' => tenant_id(),
            'changes' => ['before' => $before, 'after' => $after],
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableFilter(array $attributes): array
    {
        $excluded = array_flip([
            ...$this->getHidden(),
            ...(property_exists($this, 'auditExclude') ? $this->auditExclude : []),
        ]);

        return array_diff_key($attributes, $excluded);
    }
}
