<?php

namespace App\Modules\User\Controllers\Api;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Resources\UserResource;
use App\Modules\User\Services\UserService;
use Illuminate\Http\JsonResponse;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('api/v1/users')]
#[Middleware('api')]
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    #[Get('/', name: 'api.users.index')]
    public function index(): JsonResponse
    {
        $users = $this->userService->paginate(20);

        return $this->jsonResponse(
            UserResource::collection($users)->response()->getData(true),
            'Users retrieved successfully.'
        );
    }

    #[Post('/', name: 'api.users.store')]
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->jsonResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    #[Get('/{user}', name: 'api.users.show')]
    public function show(string $id): JsonResponse
    {
        $user = $this->userService->findOrFail($id);

        return $this->jsonResponse(
            new UserResource($user),
            'User retrieved successfully.'
        );
    }

    #[Put('/{user}', name: 'api.users.update')]
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $user = $this->userService->update($id, $request->validated());

        return $this->jsonResponse(
            new UserResource($user),
            'User updated successfully.'
        );
    }

    #[Delete('/{user}', name: 'api.users.destroy')]
    public function destroy(string $id): JsonResponse
    {
        $this->userService->delete($id);

        return $this->jsonResponse(null, 'User deleted successfully.');
    }
}
