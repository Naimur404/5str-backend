<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryAdminController extends AdminResourceController
{
    protected static string $model = Category::class;
    protected static string $resource = CategoryResource::class;
    protected static array $searchable = ['name', 'slug', 'description'];
    protected static array $sortable = ['id', 'name', 'sort_order', 'level', 'created_at', 'total_businesses'];
    protected static array $filters = [
        'parent_id' => 'parent_id',
        'level' => 'level',
        'is_active' => 'is_active',
        'is_featured' => 'is_featured',
        'is_popular' => 'is_popular',
    ];
    protected static array $with = ['parent:id,name'];
    protected static string $defaultSort = 'sort_order';

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon_image' => ['nullable', 'string', 'max:500'],
            'banner_image' => ['nullable', 'string', 'max:500'],
            'color_code' => ['nullable', 'string', 'max:20'],
            'rating_criteria' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
