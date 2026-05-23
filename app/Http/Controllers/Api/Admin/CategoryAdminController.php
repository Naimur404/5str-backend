<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use App\Support\R2Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
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

    protected function applyFilters(Builder $query, Request $request): void
    {
        $parentId = $request->input('parent_id');
        if ($parentId === 'null') {
            $query->whereNull('parent_id');
            $originalFilters = static::$filters;
            unset(static::$filters['parent_id']);
            parent::applyFilters($query, $request);
            static::$filters = $originalFilters;
        } else {
            parent::applyFilters($query, $request);
        }
    }

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();
        $isCreate = $record === null;

        return [
            // PATCH-friendly: list-page toggles only send the changed
            // field (e.g. `{is_active: false}`) and would otherwise
            // fail with "name required / slug required".
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon_image' => ['nullable', 'string', 'max:500'],
            'banner_image' => ['nullable', 'string', 'max:500'],
            'color_code' => ['nullable', 'string', 'max:20'],
            'rating_criteria' => ['nullable', 'array'],
            'rating_criteria.*' => ['string', 'max:100'],
            'sort_order' => ['nullable', 'integer'],
            'is_featured' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * GET /api/admin/categories/tree
     * Returns the full category hierarchy as a nested tree. Useful for
     * category pickers and sidebar navigation in the Next.js admin.
     */
    public function tree(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->select('id', 'name', 'slug', 'parent_id', 'level', 'icon_image', 'color_code', 'sort_order', 'is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $categories->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $byParent) {
            return ($byParent->get($parentId) ?? collect())->map(function ($cat) use (&$build) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'level' => $cat->level,
                    'parent_id' => $cat->parent_id,
                    'icon_image' => R2Storage::urlFromValue($cat->icon_image),
                    'color_code' => $cat->color_code,
                    'sort_order' => $cat->sort_order,
                    'is_active' => (bool) $cat->is_active,
                    'children' => $build($cat->id)->values(),
                ];
            });
        };

        return response()->json([
            'success' => true,
            'data' => $build(null)->values(),
        ]);
    }
}
