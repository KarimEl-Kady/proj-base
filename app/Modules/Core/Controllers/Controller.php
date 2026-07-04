<?php

namespace App\Modules\Core\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Local\DataResponse\Concerns\BuildsDataResponses;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, BuildsDataResponses, ValidatesRequests;
}
