<?php

namespace App\Modules\City\Models;

use App\Modules\Core\Models\Model;
use App\Modules\Country\Models\Country;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Global reference data, not tenant-scoped — every tenant shares the same
 * country/city list, unlike HasTenantScope models.
 */
class City extends Model
{
    protected $table = 'cities';

    /** Columns matched by the FetchRequest `word` filter. */
    protected array $searchable = ['name', 'name_ar'];

    /** Columns clients may sort by via `sort_by`. */
    protected array $sortable = ['id', 'name', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
