<?php

namespace App\Modules\Country\Resources;

use App\Modules\Core\Resources\BaseResource;
use App\Modules\Country\Models\Country;
use Illuminate\Http\Request;

/**
 * @mixin Country
 */
class CountryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'phone_code' => $this->phone_code,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'flag_emoji' => $this->flag_emoji,
            'timezone' => $this->timezone,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
