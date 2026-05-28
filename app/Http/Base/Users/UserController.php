<?php

namespace DDD\Http\Base\Users;

use DDD\App\Controllers\Controller;

// Models
use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Base\Users\Enums\RoleEnum;
use DDD\Domain\Base\Users\User;

// Requests
use DDD\Domain\Base\Users\Requests\UserRoleUpdateRequest;

// Resources
use DDD\Domain\Base\Users\Resources\UserResource;

class UserController extends Controller
{
    public function index(Organization $organization)
    {
        $users = $organization->users()->latest()->get();

        return UserResource::collection($users);
    }

    // public function show(Organization $organization, User $user)
    // {
    //     return new UserResource($user);
    // }

    // public function update(Organization $organization, User $user, Request $request)
    // {
    //     $user->update($request->all());
    //
    //     return response()->json($user);
    // }

    public function updateRole(Organization $organization, User $user, UserRoleUpdateRequest $request)
    {
        $currentUser = auth()->user();
        $role = RoleEnum::from($request->validated('role'));

        if ($currentUser->role !== RoleEnum::SuperAdmin && $user->organization_id !== $organization->id) {
            abort(404);
        }

        if ($currentUser->role !== RoleEnum::SuperAdmin && $role === RoleEnum::SuperAdmin) {
            abort(403, 'Only super admins can assign the super admin role.');
        }

        if ($currentUser->role !== RoleEnum::SuperAdmin && $user->role === RoleEnum::SuperAdmin) {
            abort(403, 'Only super admins can manage super admin users.');
        }

        $user->update([
            'role' => $role,
        ]);

        return new UserResource($user);
    }

    public function destroy(Organization $organization, User $user)
    {
        if (auth()->user()->role !== RoleEnum::SuperAdmin && $user->organization_id !== $organization->id) {
            abort(404);
        }

        $user->tokens()->delete();
        $user->delete();

        return new UserResource($user);
    }
}
