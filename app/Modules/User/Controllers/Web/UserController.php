<?php

namespace App\Modules\User\Controllers\Web;

use App\Modules\Core\Controllers\Controller;
use App\Modules\User\Requests\CreateUserRequest;
use App\Modules\User\Requests\UpdateUserRequest;
use App\Modules\User\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function index(): View
    {
        $users = $this->userService->paginate(20);

        return view('user::index', compact('users'));
    }

    public function create(): View
    {
        return view('user::create');
    }

    public function store(CreateUserRequest $request): RedirectResponse
    {
        $this->userService->create($request->validated());

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(string $id): View
    {
        $user = $this->userService->findOrFail($id);

        return view('user::show', compact('user'));
    }

    public function edit(string $id): View
    {
        $user = $this->userService->findOrFail($id);

        return view('user::edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, string $id): RedirectResponse
    {
        $this->userService->update($id, $request->validated());

        return redirect()->route('users.show', $id)
            ->with('success', 'User updated successfully.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->userService->delete($id);

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
