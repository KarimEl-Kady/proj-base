<?php

namespace App\Modules\Geo\Models;

use App\Modules\Core\Models\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Global reference data, not tenant-scoped — every tenant shares the same
 * country/city list, unlike HasTenantScope models.
 *
 * @property int $id
 * @property string $uuid
 * @property int $country_id
 * @property string $name
 * @property ?string $name_ar
 * @property ?float $latitude
 * @property ?float $longitude
 * @property bool $is_active
 * @property ?Country $country
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
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
