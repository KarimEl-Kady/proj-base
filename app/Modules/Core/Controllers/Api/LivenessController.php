<?php

namespace App\Modules\Core\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Local\DataResponse\DataResponse;

class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return DataResponse::raw([
            'status' => 'alive',
            'timestamp' => now()->toIso8601String(),
            'version' => config('project.version', '1.0.0'),
        ]);
    }
}
