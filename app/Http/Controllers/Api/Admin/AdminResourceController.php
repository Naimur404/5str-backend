<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base controller for admin CRUD endpoints.
 *
 * Subclasses configure behavior via the protected static properties below.
 * Override hooks (`indexQuery`, `transformForList`, `afterStore`, etc.) for
 * resource-specific tweaks.
 */
abstract class AdminResourceController extends Controller
{
    /** Eloquent model class. */
    protected static string $model;

    /** API resource class used to transform single records. */
    protected static string $resource;

    /** Optional dedicated request class for store. Falls back to `validationRules()`. */
    protected static ?string $storeRequest = null;

    /** Optional dedicated request class for update. Falls back to `validationRules()`. */
    protected static ?string $updateRequest = null;

    /** Columns the `q=` query parameter searches with LIKE. */
    protected static array $searchable = [];

    /** Columns that may appear in `sort=` (prefix `-` for desc). */
    protected static array $sortable = ['id'];

    /** Exact-match filters: query param name => column name. */
    protected static array $filters = [];

    /** Relations eager-loaded on index/show. */
    protected static array $with = [];

    /** Default sort applied when `sort=` is absent. */
    protected static string $defaultSort = '-id';

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 200);

        $query = static::baseQuery();
        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => static::$resource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(int|string $id): JsonResponse
    {
        $record = static::baseQuery()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new (static::$resource)($record),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateStore($request);

        $record = static::$model::create($data);
        $this->afterStore($record, $request, $data);

        $record = static::baseQuery()->find($record->getKey());

        return response()->json([
            'success' => true,
            'data' => new (static::$resource)($record),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $record = static::$model::findOrFail($id);
        $data = $this->validateUpdate($request, $record);

        $record->update($data);
        $this->afterUpdate($record, $request, $data);

        $record = static::baseQuery()->find($record->getKey());

        return response()->json([
            'success' => true,
            'data' => new (static::$resource)($record),
        ]);
    }

    public function destroy(int|string $id): JsonResponse
    {
        $record = static::$model::findOrFail($id);
        $this->beforeDestroy($record);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted.',
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $count = static::$model::whereIn('id', $ids)->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} record(s) deleted.",
            'data' => ['deleted' => $count],
        ]);
    }

    /**
     * Subclasses override this for additional fixed scopes or eager-loads
     * beyond `$with`.
     */
    protected static function baseQuery(): Builder
    {
        $query = static::$model::query();
        if (! empty(static::$with)) {
            $query->with(static::$with);
        }
        return $query;
    }

    protected function applySearch(Builder $query, Request $request): void
    {
        $term = trim((string) $request->input('q', ''));
        if ($term === '' || empty(static::$searchable)) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            foreach (static::$searchable as $col) {
                $q->orWhere($col, 'like', "%{$term}%");
            }
        });
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        foreach (static::$filters as $param => $column) {
            if (! $request->has($param)) {
                continue;
            }
            $value = $request->input($param);
            if ($value === '' || $value === null) {
                continue;
            }
            $query->where($column, $value);
        }
    }

    protected function applySort(Builder $query, Request $request): void
    {
        $sort = (string) $request->input('sort', static::$defaultSort);
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, static::$sortable, true)) {
            $sort = static::$defaultSort;
            $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
            $column = ltrim($sort, '-');
        }

        $query->orderBy($column, $direction);
    }

    protected function validateStore(Request $request): array
    {
        if (static::$storeRequest) {
            return $this->runFormRequest(static::$storeRequest, $request);
        }
        return $request->validate($this->validationRules($request));
    }

    protected function validateUpdate(Request $request, Model $record): array
    {
        if (static::$updateRequest) {
            return $this->runFormRequest(static::$updateRequest, $request);
        }
        return $request->validate($this->validationRules($request, $record));
    }

    protected function runFormRequest(string $class, Request $request): array
    {
        /** @var FormRequest $instance */
        $instance = app($class);
        $instance->setContainer(app())->setRedirector(app('redirect'));
        $instance->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all(),
            $request->getContent()
        );
        $instance->setMethod($request->method());
        $instance->setUserResolver($request->getUserResolver());
        $instance->setRouteResolver($request->getRouteResolver());
        $instance->validateResolved();
        return $instance->validated();
    }

    /**
     * Inline validation rules used when no dedicated FormRequest is set.
     * Override in the subclass when convenient.
     */
    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [];
    }

    // Lifecycle hooks — override in subclasses as needed.
    protected function afterStore(Model $record, Request $request, array $data): void {}
    protected function afterUpdate(Model $record, Request $request, array $data): void {}
    protected function beforeDestroy(Model $record): void {}
}
