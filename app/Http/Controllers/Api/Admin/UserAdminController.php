<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserAdminController extends AdminResourceController
{
    protected static string $model = User::class;
    protected static string $resource = UserResource::class;
    protected static array $searchable = ['name', 'email', 'phone', 'city'];
    protected static array $sortable = ['id', 'name', 'email', 'created_at', 'total_points', 'total_reviews_written'];
    protected static array $with = ['roles:id,name', 'permissions:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();
        $isCreate = $record === null;

        return [
            // PATCH-friendly: list-page toggles only send the changed
            // field (e.g. `{is_active: false}`); name/email are
            // required only when creating a new user.
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => [$isCreate ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:30'],
            'profile_image' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'current_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'current_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'trust_level' => ['nullable', 'integer', 'between:1,5'],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    protected function afterStore(Model $record, Request $request, array $data): void
    {
        $this->hashPassword($record, $data);
        $this->maybeSyncRoles($record, $request);
        $this->maybeSyncPermissions($record, $request);
    }

    protected function afterUpdate(Model $record, Request $request, array $data): void
    {
        $this->hashPassword($record, $data);
        $this->maybeSyncRoles($record, $request);
        $this->maybeSyncPermissions($record, $request);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($data['roles']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user->load('roles:id,name')),
        ]);
    }

    private function hashPassword(User $user, array $data): void
    {
        if (! empty($data['password'])) {
            $user->forceFill(['password' => Hash::make($data['password'])])->save();
        }
    }

    private function maybeSyncRoles(User $user, Request $request): void
    {
        if (! $request->has('roles')) {
            return;
        }
        $request->validate([
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);
        $user->syncRoles((array) $request->input('roles'));
    }

    private function maybeSyncPermissions(User $user, Request $request): void
    {
        if (! $request->has('permissions')) {
            return;
        }
        $user->syncPermissions((array) $request->input('permissions'));
    }
}
