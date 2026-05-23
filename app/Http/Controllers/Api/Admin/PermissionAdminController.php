<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\PermissionResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionAdminController extends AdminResourceController
{
    protected static string $model = Permission::class;
    protected static string $resource = PermissionResource::class;
    protected static array $searchable = ['name'];
    protected static array $sortable = ['id', 'name', 'created_at'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();
        $isCreate = $record === null;

        return [
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:125', Rule::unique('permissions', 'name')->ignore($id)],
            'guard_name' => ['nullable', 'string', 'max:125'],
        ];
    }
}
