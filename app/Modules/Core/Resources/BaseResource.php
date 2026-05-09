<?php

namespace App\Modules\Core\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class BaseResource extends JsonResource
{
    public static $wrap = null;

    protected function mergeWhenLoaded(string $relation, ?string $resourceClass = null): array
    {
        if (! $this->relationLoaded($relation)) {
            return [];
        }

        $data = $this->{$relation};

        if ($data === null) {
            return [$relation => null];
        }

        if ($resourceClass && class_exists($resourceClass)) {
            return [$relation => $resourceClass::collection($data instanceof Collection ? $data : collect([$data]))];
        }

        return [$relation => $data];
    }
}
