<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the user pages only; they receive the user id and load their data through the API.
 */
class UserController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('users/index');
    }

    public function show(User $user): Response
    {
        Gate::authorize('view', $user);

        return Inertia::render('users/show', ['userId' => $user->id]);
    }

    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/form', ['userId' => $user->id]);
    }
}
