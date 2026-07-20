<?php

namespace App\Modules\Geo\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Geo\Models\Country;

class CountryRepository extends BaseRepository
{
    public function __construct(Country $model)
    {
        parent::__construct($model);
    }
}
