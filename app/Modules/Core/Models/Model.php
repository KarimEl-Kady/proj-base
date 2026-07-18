<?php

namespace App\Modules\Core\Models;

use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as Eloquent;

/**
 * @method static Builder|null forTenant(int|string|null $tenantId)
 */
abstract class Model extends Eloquent
{
    use HasFactory;
    use HasUuid;

    protected $guarded = ['id', 'uuid', 'tenant_id'];

    public function getGuarded(): array
    {
        return array_values(array_unique([
            ...parent::getGuarded(),
            (string) config('project.tenancy.tenant_column', 'tenant_id'),
        ]));
    }

    /**
     * Columns that are globally unique in none mode and unique per tenant
     * when tenancy is active.
     *
     * @return array<int, array<int, string>>
     */
    public function tenantUniqueColumns(): array
    {
        return [];
    }

    /**
     * Columns matched by the free-text `word` filter in BaseRepository::fetch().
     *
     * @var array<int, string>
     */
    protected array $searchable = [];

    /**
     * Columns clients may sort by via `sort_by`. Whitelist — anything
     * else is ignored to keep column names out of attacker control.
     *
     * @var array<int, string>
     */
    protected array $sortable = ['id', 'created_at', 'updated_at'];

    /**
     * @return array<int, string>
     */
    public function searchableColumns(): array
    {
        return $this->searchable;
    }

    /**
     * @return array<int, string>
     */
    public function sortableColumns(): array
    {
        return $this->sortable;
    }

    /**
     * Resolve factories from the module's own Database/Factories directory,
     * e.g. App\Modules\Blog\Models\Post -> App\Modules\Blog\Database\Factories\PostFactory.
     *
     * Untyped to stay signature-compatible with models that re-import
     * the HasFactory trait directly.
     *
     * @return Factory|null
     */
    protected static function newFactory()
    {
        $factory = str_replace('\\Models\\', '\\Database\\Factories\\', static::class).'Factory';

        return class_exists($factory) ? $factory::new() : null;
    }

    public function scopeForTenant(Builder $query, int|string|null $tenantId): Builder
    {
        if ($tenantId === null) {
            return $query;
        }

        $column = config('project.tenancy.tenant_column', 'tenant_id');

        return $query->where($column, $tenantId);
    }
}
