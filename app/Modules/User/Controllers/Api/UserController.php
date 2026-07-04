<?php

namespace App\Modules\User\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\FetchUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Resources\UserResource;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(FetchUserRequest $request): JsonResponse
    {
        $users = $this->userService->fetch($request);

        return $this->jsonResponse(
            UserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully.'
        );
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->jsonResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    public function show(string $id): JsonResponse
    {
        $user = $this->userService->findOrFail($id);

        return $this->jsonResponse(
            new UserResource($user),
            'User retrieved successfully.'
        );
    }

    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated());

        return $this->jsonResponse(
            new UserResource($user),
            'User updated successfully.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->jsonResponse(null, 'User deleted successfully.');
    }
}
