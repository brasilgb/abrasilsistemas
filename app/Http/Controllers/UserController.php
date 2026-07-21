<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('users/index', [
            'users' => User::query()
                ->latest()
                ->get(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            ...$request->safe()->only(['name', 'email', 'password']),
            'role' => 'admin',
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário criado.']);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'managedUser' => $user->only(['id', 'name', 'email', 'email_verified_at', 'created_at', 'updated_at']),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = now();
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuário atualizado.']);

        return to_route('users.index');
    }
}
