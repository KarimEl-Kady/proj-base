<?php

namespace App\Modules\Country\Services;

use App\Modules\Core\Services\BaseService;
use App\Modules\Country\Repositories\CountryRepository;

class CountryService extends BaseService
{
    public function __construct(CountryRepository $repository)
    {
        parent::__construct($repository);
    }
}
