<?php

namespace App\Modules\User\Controllers\Web;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\RouteAttributes\Attributes\Delete;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Put;

#[Prefix('users')]
#[Middleware('web')]
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    #[Get('/', name: 'users.index')]
    public function index(): View
    {
        $users = $this->userService->paginate(20);

        return view('user::index', compact('users'));
    }

    #[Get('/create', name: 'users.create')]
    public function create(): View
    {
        return view('user::create');
    }

    #[Post('/', name: 'users.store')]
    public function store(CreateUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    #[Get('/{user}', name: 'users.show')]
    public function show(string $id): View
    {
        $user = $this->userService->findOrFail($id);

        return view('user::show', compact('user'));
    }

    #[Get('/{user}/edit', name: 'users.edit')]
    public function edit(string $id): View
    {
        $user = $this->userService->findOrFail($id);

        return view('user::edit', compact('user'));
    }

    #[Put('/{user}', name: 'users.update')]
    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $this->userService->update($id, $request->validated());

        return redirect()->route('users.show', $id)
            ->with('success', 'User updated successfully.');
    }

    #[Delete('/{user}', name: 'users.destroy')]
    public function destroy(string $id): RedirectResponse
    {
        $this->userService->delete($id);

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
