<?php

namespace App\Modules\Country\Repositories;

use App\Modules\Core\Repositories\BaseRepository;
use App\Modules\Country\Models\Country;

class CountryRepository extends BaseRepository
{
    public function __construct(Country $model)
    {
        parent::__construct($model);
    }
}
