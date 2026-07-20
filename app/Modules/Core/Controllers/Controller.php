<?php

namespace App\Modules\Core\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;
use Local\DataResponse\Concerns\BuildsDataResponses;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, BuildsDataResponses, ValidatesRequests;

    /**
     * Resolve a resource collection directly into the existing nested API
     * envelope without first JSON-encoding and decoding an intermediate
     * response. Pagination metadata intentionally matches Laravel's default.
     */
    protected function successCollectionResponse(
        ResourceCollection $resource,
        Request $request,
        ?string $message = null,
    ): JsonResponse {
        $items = (array) $resource->resolve($request);
        $wrap = $resource::$wrap;

        if ($resource->resource instanceof LengthAwarePaginator) {
            $paginated = $resource->resource->toArray();
            $payload = [
                $wrap ?? 'data' => $items,
                'links' => [
                    'first' => $paginated['first_page_url'] ?? null,
                    'last' => $paginated['last_page_url'] ?? null,
                    'prev' => $paginated['prev_page_url'] ?? null,
                    'next' => $paginated['next_page_url'] ?? null,
                ],
                'meta' => Arr::except($paginated, [
                    'data',
                    'first_page_url',
                    'last_page_url',
                    'prev_page_url',
                    'next_page_url',
                ]),
            ];
        } else {
            $payload = $wrap === null ? $items : [$wrap => $items];
        }

        return $this->successResponse($payload, $message);
    }
}
