<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\OfferResource;
use App\Models\Offer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfferAdminController extends AdminResourceController
{
    protected static string $model = Offer::class;
    protected static string $resource = OfferResource::class;
    protected static array $searchable = ['title', 'description', 'offer_code'];
    protected static array $sortable = ['id', 'title', 'valid_from', 'valid_to', 'created_at'];
    protected static array $filters = [
        'business_id' => 'business_id',
        'offer_type' => 'offer_type',
        'is_active' => 'is_active',
        'is_featured' => 'is_featured',
    ];
    protected static array $with = ['business:id,business_name,parent_id'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'business_id' => [$isCreate ? 'required' : 'prohibited', 'integer', 'exists:businesses,id'],
            'title' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:1000'],
            'offer_type' => [$isCreate ? 'required' : 'sometimes', Rule::in(['percentage', 'fixed_amount', 'bogo', 'combo'])],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'minimum_spend' => ['nullable', 'numeric', 'min:0'],
            'offer_code' => ['nullable', 'string', 'max:255'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'valid_from' => [$isCreate ? 'required' : 'sometimes', 'date'],
            'valid_to' => [$isCreate ? 'required' : 'sometimes', 'date', 'after_or_equal:valid_from'],
            'applicable_days' => ['nullable', 'array'],
            'applicable_days.*' => ['integer', 'between:0,6'],
            'banner_image' => ['nullable', 'string', 'max:500'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'cascade_to_branches' => ['nullable', 'boolean'],
        ];
    }

    protected function afterStore(Model $record, Request $request, array $data): void
    {
        if ($request->boolean('cascade_to_branches', true) === false) {
            return;
        }
        $this->cascadeToBranches($record);
    }

    protected function cascadeToBranches(Offer $offer): void
    {
        $business = $offer->business()->with('branches:id,parent_id')->first();
        if (! $business || $business->parent_id !== null || $business->branches->isEmpty()) {
            return;
        }

        $template = $offer->only([
            'title', 'description', 'offer_type',
            'discount_percentage', 'discount_amount', 'minimum_spend',
            'offer_code', 'usage_limit',
            'valid_from', 'valid_to', 'applicable_days',
            'banner_image', 'is_featured', 'is_active',
        ]);

        foreach ($business->branches as $branch) {
            Offer::create($template + [
                'business_id' => $branch->id,
                'current_usage' => 0,
            ]);
        }
    }

    /**
     * POST /api/admin/offers/{offer}/duplicate
     * Same business, "(Copy)" suffix, usage reset.
     */
    public function duplicate(Offer $offer): JsonResponse
    {
        $clone = $offer->replicate();
        $clone->title = $offer->title . ' (Copy)';
        $clone->current_usage = 0;
        $clone->save();

        return response()->json([
            'success' => true,
            'data' => new OfferResource($clone->load('business:id,business_name,parent_id')),
        ], 201);
    }
}
