<?php

namespace App\Modules\Geo\Services;

use App\Modules\Core\Services\BaseService;
use App\Modules\Geo\Repositories\CountryRepository;

class CountryService extends BaseService
{
    public function __construct(CountryRepository $repository)
    {
        parent::__construct($repository);
    }
}
