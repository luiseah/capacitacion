<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Users\UserDestroyRequest;
use App\Http\Requests\Users\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', User::class);

        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(RoleName::class)],
            'sort' => ['nullable', 'array'],
            'sort.*' => ['in:asc,desc'],
        ]);

        $users = User::query()
            ->withListRelations()
            ->applyFilters($request)
            ->sort($request)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => (new UserResource($user))->resolve());

        return Response::api($users, __('Successful'));
    }

    public function show(User $user): JsonResponse
    {
        Gate::authorize('view', $user);

        return Response::api(new UserResource($user->load('roles')), __('Successful'));
    }

    public function update(UserUpdateRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $user->fill(Arr::only($data, ['name', 'lastname', 'email', 'phone']));

        // A new address has to be verified again.
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();
        $user->syncRoles($data['roles']);

        return Response::api(new UserResource($user->load('roles')), __('User updated'));
    }

    public function destroy(UserDestroyRequest $request, User $user): JsonResponse
    {
        // Requests keep their requester (restrictOnDelete), soft-deleted ones included.
        if (ServiceRequest::query()->withoutGlobalScopes()->withTrashed()->where('requester_id', $user->id)->exists()) {
            abort(409, __('This user has requests and cannot be deleted.'));
        }

        $user->delete();

        return Response::success(__('User deleted'));
    }
}
