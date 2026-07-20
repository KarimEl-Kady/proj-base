<?php

namespace App\Modules\Geo\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\Geo\Requests\CreateCityRequest;
use App\Modules\Geo\Requests\FetchCityRequest;
use App\Modules\Geo\Requests\UpdateCityRequest;
use App\Modules\Geo\Resources\CityResource;
use App\Modules\Geo\Services\CityService;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    public function __construct(
        protected CityService $cityService
    ) {}

    public function index(FetchCityRequest $request): JsonResponse
    {
        $records = $this->cityService->fetch($request);

        return $this->successResponse(
            CityResource::collection($records)->response()->getData(true),
            'Cities retrieved successfully.'
        );
    }

    public function store(CreateCityRequest $request): JsonResponse
    {
        $record = $this->cityService->create($request->validated());

        return $this->successResponse(
            new CityResource($record),
            'City created successfully.',
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $record = $this->cityService->findOrFail($id);

        return $this->successResponse(
            new CityResource($record),
            'City retrieved successfully.'
        );
    }

    public function update(UpdateCityRequest $request, string $id): JsonResponse
    {
        $record = $this->cityService->update($id, $request->validated());

        return $this->successResponse(
            new CityResource($record),
            'City updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->cityService->delete($id);

        return $this->successResponse(null, 'City deleted successfully.');
    }
}
