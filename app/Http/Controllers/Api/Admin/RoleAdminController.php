<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\RoleResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleAdminController extends AdminResourceController
{
    protected static string $model = Role::class;
    protected static string $resource = RoleResource::class;
    protected static array $searchable = ['name'];
    protected static array $sortable = ['id', 'name', 'created_at'];
    protected static array $with = ['permissions:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();

        return [
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($id)],
            'guard_name' => ['nullable', 'string', 'max:125'],
        ];
    }

    protected function afterStore(Model $record, Request $request, array $data): void
    {
        $this->maybeSyncPermissions($record, $request);
    }

    protected function afterUpdate(Model $record, Request $request, array $data): void
    {
        $this->maybeSyncPermissions($record, $request);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($data['permissions']);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role->load('permissions:id,name')),
        ]);
    }

    private function maybeSyncPermissions(Role $role, Request $request): void
    {
        if (! $request->has('permissions')) {
            return;
        }
        $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $role->syncPermissions((array) $request->input('permissions'));
    }
}
