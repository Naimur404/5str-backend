<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BusinessResource;
use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BusinessAdminController extends AdminResourceController
{
    protected static string $model = Business::class;
    protected static string $resource = BusinessResource::class;
    protected static array $searchable = ['business_name', 'slug', 'city', 'area', 'business_phone', 'business_email'];
    protected static array $sortable = [
        'id', 'business_name', 'created_at', 'overall_rating', 'total_reviews',
        'discovery_score', 'price_range',
    ];
    protected static array $filters = [
        'category_id' => 'category_id',
        'subcategory_id' => 'subcategory_id',
        'owner_user_id' => 'owner_user_id',
        'parent_id' => 'parent_id',
        'is_active' => 'is_active',
        'is_verified' => 'is_verified',
        'is_featured' => 'is_featured',
        'is_national' => 'is_national',
        'approval_status' => 'approval_status',
        'city' => 'city',
    ];
    protected static array $with = [
        'category:id,name',
        'subcategory:id,name',
        'owner:id,name,email',
        'parentBusiness:id,business_name',
        'logoImage:id,business_id,image_url',
        'coverImage:id,business_id,image_url',
    ];

    /**
     * Override index to expose:
     *   ?only=parents  → top-level brands only (parent_id is null)
     *   ?only=branches → branch records only
     *   ?has_branches=1 → main brands that have at least one branch
     */
    protected static function baseQuery(): Builder
    {
        $query = parent::baseQuery();
        $query->withCount('branches');

        $request = request();
        $only = $request->input('only');
        if ($only === 'parents') {
            $query->whereNull('parent_id');
        } elseif ($only === 'branches') {
            $query->whereNotNull('parent_id');
        }
        if ($request->boolean('has_branches')) {
            $query->whereNull('parent_id')->has('branches');
        }

        return $query;
    }

    /**
     * Grouped business picker for offerings/offers/etc.
     * Returns: { main_brands: [...], standalone: [...], branches: [...] }
     */
    public function picker(Request $request): JsonResponse
    {
        $businesses = Business::query()
            ->select('id', 'business_name', 'parent_id')
            ->with('parentBusiness:id,business_name')
            ->withCount('branches')
            ->orderBy('business_name')
            ->get();

        $mainBrands = [];
        $standalone = [];
        $branches = [];

        foreach ($businesses as $b) {
            if ($b->parent_id) {
                $branches[] = [
                    'id' => $b->id,
                    'business_name' => $b->business_name,
                    'parent_id' => $b->parent_id,
                    'parent_name' => $b->parentBusiness?->business_name,
                    'label' => $b->business_name . ' (' . ($b->parentBusiness?->business_name ?? '—') . ')',
                ];
            } elseif ($b->branches_count > 0) {
                $mainBrands[] = [
                    'id' => $b->id,
                    'business_name' => $b->business_name,
                    'branches_count' => $b->branches_count,
                    'label' => $b->business_name . ' (' . $b->branches_count . ' branches)',
                ];
            } else {
                $standalone[] = [
                    'id' => $b->id,
                    'business_name' => $b->business_name,
                    'label' => $b->business_name,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'main_brands' => $mainBrands,
                'standalone' => $standalone,
                'branches' => $branches,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules($request));
        $branchesPayload = $this->validateBranches($request);

        return DB::transaction(function () use ($data, $branchesPayload, $request) {
            $data = $this->applyAdminApproval($data);
            $data['slug'] = $this->ensureUniqueSlug($data['slug'] ?? Str::slug($data['business_name']));

            $business = Business::create($data);

            foreach ($branchesPayload as $branchData) {
                $this->createBranch($business, $branchData);
            }

            return response()->json([
                'success' => true,
                'data' => new BusinessResource(
                    static::baseQuery()->with(['branches'])->find($business->id)
                ),
            ], 201);
        });
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $record = Business::findOrFail($id);
        $data = $request->validate($this->validationRules($request, $record));

        if (isset($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $record->id);
        }

        $record->update($data);

        return response()->json([
            'success' => true,
            'data' => new BusinessResource(static::baseQuery()->find($record->id)),
        ]);
    }

    /**
     * Attach one or many branches to an existing business.
     * POST /api/admin/businesses/{business}/branches
     * Body: { branches: [ {...}, {...} ] }  OR a single branch object.
     */
    public function storeBranches(Request $request, Business $business): JsonResponse
    {
        if ($business->parent_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add branches to a record that is itself a branch.',
            ], 422);
        }

        $payload = $this->validateBranches($request, true);

        $created = DB::transaction(function () use ($business, $payload) {
            $rows = [];
            foreach ($payload as $branchData) {
                $rows[] = $this->createBranch($business, $branchData);
            }
            return $rows;
        });

        return response()->json([
            'success' => true,
            'data' => collect($created)->map(fn ($b) => new BusinessResource($b->fresh(static::$with))),
            'meta' => ['created' => count($created)],
        ], 201);
    }

    public function approve(Request $request, Business $business): JsonResponse
    {
        $business->forceFill([
            'approval_status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new BusinessResource($business->load(static::$with)),
        ]);
    }

    public function reject(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $business->forceFill([
            'approval_status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new BusinessResource($business->load(static::$with)),
        ]);
    }

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();
        $isCreate = $record === null;

        return [
            'business_name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => [$isCreate ? 'required' : 'sometimes', 'string'],
            'category_id' => [$isCreate ? 'required' : 'sometimes', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:categories,id'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'parent_id' => ['nullable', 'integer', Rule::exists('businesses', 'id')->where(fn ($q) => $q->whereNull('parent_id'))],

            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:50'],
            'website_url' => ['nullable', 'url', 'max:500'],

            'full_address' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:1000'],
            'latitude' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'between:-90,90'],
            'longitude' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'between:-180,180'],
            'city' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'area' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:255'],

            'opening_hours' => ['nullable', 'array'],
            'price_range' => ['nullable', 'integer', 'between:1,4'],
            'has_delivery' => ['nullable', 'boolean'],
            'has_pickup' => ['nullable', 'boolean'],
            'has_parking' => ['nullable', 'boolean'],

            'is_verified' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'is_national' => ['nullable', 'boolean'],
            'service_coverage' => ['nullable', 'string', 'max:50'],
            'service_areas' => ['nullable', 'array'],
            'business_model' => ['nullable', 'string', 'max:50'],

            'product_tags' => ['nullable', 'array'],
            'business_tags' => ['nullable', 'array'],

            'approval_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    /**
     * Validate the optional `branches[]` payload (used on store and storeBranches).
     */
    protected function validateBranches(Request $request, bool $required = false): array
    {
        if (! $request->has('branches')) {
            return [];
        }

        $rules = [
            'branches' => [$required ? 'required' : 'nullable', 'array', $required ? 'min:1' : ''],
            'branches.*.business_name' => ['required', 'string', 'max:255'],
            'branches.*.full_address' => ['required', 'string', 'max:1000'],
            'branches.*.city' => ['required', 'string', 'max:100'],
            'branches.*.area' => ['required', 'string', 'max:100'],
            'branches.*.landmark' => ['nullable', 'string', 'max:255'],
            'branches.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'branches.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'branches.*.business_phone' => ['nullable', 'string', 'max:50'],
            'branches.*.business_email' => ['nullable', 'email', 'max:255'],
            'branches.*.is_active' => ['nullable', 'boolean'],
        ];

        return $request->validate(array_map(fn ($r) => array_filter($r), $rules))['branches'] ?? [];
    }

    protected function createBranch(Business $parent, array $branchData): Business
    {
        return Business::create([
            'parent_id' => $parent->id,
            'business_name' => $branchData['business_name'],
            'slug' => $this->ensureUniqueSlug(Str::slug($parent->slug . '-' . $branchData['business_name'])),
            'description' => $parent->description,
            'category_id' => $parent->category_id,
            'subcategory_id' => $parent->subcategory_id,
            'owner_user_id' => $parent->owner_user_id,
            'business_email' => $branchData['business_email'] ?? $parent->business_email,
            'business_phone' => $branchData['business_phone'] ?? $parent->business_phone,
            'website_url' => $parent->website_url,
            'full_address' => $branchData['full_address'],
            'latitude' => $branchData['latitude'],
            'longitude' => $branchData['longitude'],
            'city' => $branchData['city'],
            'area' => $branchData['area'],
            'landmark' => $branchData['landmark'] ?? null,
            'opening_hours' => $parent->opening_hours,
            'price_range' => $parent->price_range,
            'has_delivery' => $parent->has_delivery,
            'has_pickup' => $parent->has_pickup,
            'has_parking' => $parent->has_parking,
            'is_active' => $branchData['is_active'] ?? true,
            'approval_status' => $parent->approval_status,
            'approved_by' => $parent->approved_by,
            'approved_at' => $parent->approved_at,
        ]);
    }

    protected function ensureUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $i = 1;
        while (
            Business::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }

    protected function applyAdminApproval(array $data): array
    {
        if (! Auth::user() || Auth::user()->hasRole('business-owner')) {
            $data['approval_status'] = $data['approval_status'] ?? 'pending';
            return $data;
        }
        $data['approval_status'] = $data['approval_status'] ?? 'approved';
        if ($data['approval_status'] === 'approved') {
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }
        return $data;
    }
}
