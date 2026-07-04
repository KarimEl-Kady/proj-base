<?php

namespace App\Modules\City\Resources;

use App\Modules\Core\Resources\BaseResource;
use Illuminate\Http\Request;

class CityResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'is_active' => $this->is_active,
            'country' => [
                'id' => $this->whenLoaded('country', fn () => $this->country->uuid),
                'name' => $this->whenLoaded('country', fn () => $this->country->name),
                'iso2' => $this->whenLoaded('country', fn () => $this->country->iso2),
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
