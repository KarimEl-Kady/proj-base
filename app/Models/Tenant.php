<?php

namespace App\Models;

use App\Modules\Core\Support\TenantCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'subdomain',
        'is_active',
    ];

    protected static function booted(): void
    {
        // TenantMiddleware caches identifier → id resolution; any write to a
        // tenant (rename, deactivation, deletion) must drop those entries so
        // the change takes effect on the next request, not at TTL expiry.
        static::saved(fn (Tenant $tenant) => TenantCache::forgetTenant($tenant));
        static::deleted(fn (Tenant $tenant) => TenantCache::forgetTenant($tenant));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
