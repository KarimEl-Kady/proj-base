<?php

namespace App\Modules\Country\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\Country\Requests\CreateCountryRequest;
use App\Modules\Country\Requests\FetchCountryRequest;
use App\Modules\Country\Requests\UpdateCountryRequest;
use App\Modules\Country\Resources\CountryResource;
use App\Modules\Country\Services\CountryService;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    public function __construct(
        protected CountryService $countryService
    ) {}

    public function index(FetchCountryRequest $request): JsonResponse
    {
        $records = $this->countryService->fetch($request);

        return $this->successResponse(
            CountryResource::collection($records)->response()->getData(true),
            'Countries retrieved successfully.'
        );
    }

    public function store(CreateCountryRequest $request): JsonResponse
    {
        $record = $this->countryService->create($request->validated());

        return $this->successResponse(
            new CountryResource($record),
            'Country created successfully.',
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $record = $this->countryService->findOrFail($id);

        return $this->successResponse(
            new CountryResource($record),
            'Country retrieved successfully.'
        );
    }

    public function update(UpdateCountryRequest $request, string $id): JsonResponse
    {
        $record = $this->countryService->update($id, $request->validated());

        return $this->successResponse(
            new CountryResource($record),
            'Country updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->countryService->delete($id);

        return $this->successResponse(null, 'Country deleted successfully.');
    }
}
