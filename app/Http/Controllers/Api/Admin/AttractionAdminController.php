<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\AttractionResource;
use App\Models\Attraction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttractionAdminController extends AdminResourceController
{
    protected static string $model = Attraction::class;
    protected static string $resource = AttractionResource::class;
    protected static array $searchable = ['name', 'slug', 'description', 'city', 'area', 'address'];
    protected static array $sortable = [
        'id', 'name', 'overall_rating', 'total_reviews', 'total_views',
        'discovery_score', 'created_at',
    ];
    protected static array $filters = [
        'type' => 'type',
        'category' => 'category',
        'subcategory' => 'subcategory',
        'city' => 'city',
        'is_free' => 'is_free',
        'is_verified' => 'is_verified',
        'is_featured' => 'is_featured',
        'is_active' => 'is_active',
        'status' => 'status',
    ];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $id = $record?->getKey();
        $isCreate = $record === null;

        return [
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('attractions', 'slug')->ignore($id)],
            'description' => [$isCreate ? 'required' : 'sometimes', 'string'],
            'type' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'latitude' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'between:-90,90'],
            'longitude' => [$isCreate ? 'required' : 'sometimes', 'numeric', 'between:-180,180'],
            'address' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:1000'],
            'city' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_free' => ['nullable', 'boolean'],
            'entry_fee' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'opening_hours' => ['nullable', 'array'],
            'contact_info' => ['nullable', 'array'],
            'facilities' => ['nullable', 'array'],
            'best_time_to_visit' => ['nullable', 'array'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:0'],
            'difficulty_level' => ['nullable', 'string', 'max:50'],
            'accessibility_info' => ['nullable', 'array'],
            'is_verified' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:50'],
            'meta_data' => ['nullable', 'array'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->validationRules($request));
        $data['slug'] = $this->ensureUniqueSlug($data['slug'] ?? Str::slug($data['name']));
        $data['created_by'] = Auth::id();

        $attraction = Attraction::create($data);

        return response()->json([
            'success' => true,
            'data' => new AttractionResource($attraction),
        ], 201);
    }

    public function update(Request $request, int|string $id): JsonResponse
    {
        $record = Attraction::findOrFail($id);
        $data = $request->validate($this->validationRules($request, $record));

        if (isset($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $record->id);
        }

        $record->update($data);

        return response()->json([
            'success' => true,
            'data' => new AttractionResource($record->fresh()),
        ]);
    }

    public function verify(Attraction $attraction): JsonResponse
    {
        $attraction->forceFill([
            'is_verified' => true,
            'verified_by' => Auth::id(),
            'verified_at' => now(),
            'status' => 'approved',
            'rejection_reason' => null,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new AttractionResource($attraction),
        ]);
    }

    public function reject(Request $request, Attraction $attraction): JsonResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $attraction->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new AttractionResource($attraction),
        ]);
    }

    private function ensureUniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug;
        $i = 1;
        while (
            Attraction::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
