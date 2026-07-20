<?php

namespace App\Modules\Geo\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Core\Requests\FetchRequest;
use App\Modules\Geo\Models\City;
use App\Modules\Geo\Requests\FetchCityRequest;
use Illuminate\Database\Eloquent\Builder;

class CityRepository extends BaseRepository
{
    public function __construct(City $model)
    {
        parent::__construct($model);
    }

    public function query(): Builder
    {
        return parent::query()->with('country');
    }

    protected function baseQuery(FetchRequest $request): Builder
    {
        $query = $this->query();

        if ($request instanceof FetchCityRequest && ($code = $request->countryCode()) !== null) {
            $query->whereHas('country', fn (Builder $inner) => $inner->where('iso2', $code));
        }

        return $query;
    }
}
