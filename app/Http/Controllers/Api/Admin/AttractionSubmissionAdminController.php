<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\AttractionResource as AttractionTransformer;
use App\Http\Resources\Admin\AttractionSubmissionResource;
use App\Models\Attraction;
use App\Models\AttractionSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttractionSubmissionAdminController extends AdminResourceController
{
    protected static string $model = AttractionSubmission::class;
    protected static string $resource = AttractionSubmissionResource::class;
    protected static array $searchable = ['name', 'description', 'city', 'address'];
    protected static array $sortable = ['id', 'name', 'status', 'created_at', 'reviewed_at'];
    protected static array $filters = [
        'status' => 'status',
        'user_id' => 'user_id',
        'city' => 'city',
        'type' => 'type',
    ];
    protected static array $with = [
        'user:id,name,email',
        'reviewer:id,name',
    ];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ];
    }

    public function approve(Request $request, AttractionSubmission $submission): JsonResponse
    {
        if ($submission->status === 'approved' && $submission->approved_attraction_id) {
            return response()->json([
                'success' => false,
                'message' => 'Submission already approved.',
            ], 422);
        }

        $overrides = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'subcategory' => ['nullable', 'string', 'max:100'],
            'is_free' => ['nullable', 'boolean'],
            'currency' => ['nullable', 'string', 'max:3'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $attraction = DB::transaction(function () use ($submission, $overrides) {
            $attraction = Attraction::create([
                'name' => $submission->name,
                'slug' => $this->ensureUniqueAttractionSlug(Str::slug($submission->name)),
                'description' => $submission->description,
                'type' => $submission->type,
                'category' => $overrides['category'] ?? $submission->type,
                'subcategory' => $overrides['subcategory'] ?? null,
                'address' => $submission->address,
                'city' => $submission->city,
                'latitude' => $submission->latitude,
                'longitude' => $submission->longitude,
                'is_free' => $overrides['is_free'] ?? ($submission->entry_fee == 0 || $submission->entry_fee === null),
                'entry_fee' => $submission->entry_fee,
                'currency' => $overrides['currency'] ?? 'BDT',
                'opening_hours' => is_string($submission->visiting_hours)
                    ? json_decode($submission->visiting_hours, true)
                    : $submission->visiting_hours,
                'best_time_to_visit' => is_string($submission->best_time_to_visit)
                    ? [$submission->best_time_to_visit]
                    : $submission->best_time_to_visit,
                'facilities' => is_string($submission->facilities)
                    ? json_decode($submission->facilities, true)
                    : $submission->facilities,
                'is_verified' => true,
                'is_featured' => $overrides['is_featured'] ?? false,
                'is_active' => $overrides['is_active'] ?? true,
                'status' => 'approved',
                'created_by' => $submission->user_id,
                'verified_by' => Auth::id(),
                'verified_at' => now(),
            ]);

            $submission->forceFill([
                'status' => 'approved',
                'admin_notes' => $overrides['admin_notes'] ?? $submission->admin_notes,
                'reviewed_at' => now(),
                'reviewed_by' => Auth::id(),
                'approved_attraction_id' => $attraction->id,
            ])->save();

            return $attraction;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'submission' => new AttractionSubmissionResource($submission->fresh(static::$with)),
                'attraction' => new AttractionTransformer($attraction),
            ],
        ]);
    }

    public function reject(Request $request, AttractionSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'admin_notes' => ['required', 'string', 'max:1000'],
        ]);

        $submission->forceFill([
            'status' => 'rejected',
            'admin_notes' => $data['admin_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => new AttractionSubmissionResource($submission->fresh(static::$with)),
        ]);
    }

    private function ensureUniqueAttractionSlug(string $slug): string
    {
        $base = $slug;
        $i = 1;
        while (Attraction::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
