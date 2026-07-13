<?php

namespace App\Modules\Core\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

abstract class BaseResource extends JsonResource
{
    public static $wrap = null;

    protected function mergeWhenLoaded(string $relation, ?string $resourceClass = null): array
    {
        // Go through ->resource explicitly rather than relying on
        // JsonResource's __call proxy: the wrapped value is only a Model
        // here, and being explicit keeps the call type-checkable.
        if (! $this->resource instanceof Model || ! $this->resource->relationLoaded($relation)) {
            return [];
        }

        $data = $this->resource->getRelation($relation);

        if ($data === null) {
            return [$relation => null];
        }

        if ($resourceClass && class_exists($resourceClass)) {
            return [$relation => $resourceClass::collection($data instanceof Collection ? $data : collect([$data]))];
        }

        return [$relation => $data];
    }
}
