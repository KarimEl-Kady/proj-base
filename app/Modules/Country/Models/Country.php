<?php

namespace App\Modules\Country\Models;

use App\Modules\City\Models\City;
use App\Modules\Core\Models\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global reference data, not tenant-scoped — every tenant shares the same
 * country/city list, unlike HasTenantScope models.
 */
class Country extends Model
{
    protected $table = 'countries';

    /** Columns matched by the FetchRequest `word` filter. */
    protected array $searchable = ['name', 'name_ar', 'iso2', 'iso3'];

    /** Columns clients may sort by via `sort_by`. */
    protected array $sortable = ['id', 'name', 'iso2', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
