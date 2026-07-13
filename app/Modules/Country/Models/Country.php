<?php

namespace App\Modules\Country\Models;

use App\Modules\City\Models\City;
use App\Modules\Core\Models\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Global reference data, not tenant-scoped — every tenant shares the same
 * country/city list, unlike HasTenantScope models.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property ?string $name_ar
 * @property string $iso2
 * @property string $iso3
 * @property ?string $phone_code
 * @property ?string $currency
 * @property ?string $currency_symbol
 * @property ?string $flag_emoji
 * @property ?string $timezone
 * @property bool $is_active
 * @property Collection<int, City> $cities
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
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
