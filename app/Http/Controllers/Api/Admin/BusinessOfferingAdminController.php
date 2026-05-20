<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BusinessOfferingResource;
use App\Models\BusinessOffering;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessOfferingAdminController extends AdminResourceController
{
    protected static string $model = BusinessOffering::class;
    protected static string $resource = BusinessOfferingResource::class;
    protected static array $searchable = ['name', 'description'];
    protected static array $sortable = [
        'id', 'name', 'price', 'sort_order', 'average_rating', 'total_reviews', 'created_at',
    ];
    protected static array $filters = [
        'business_id' => 'business_id',
        'category_id' => 'category_id',
        'offering_type' => 'offering_type',
        'is_available' => 'is_available',
        'is_popular' => 'is_popular',
        'is_featured' => 'is_featured',
        'approval_status' => 'approval_status',
    ];
    protected static array $with = [
        'business:id,business_name,parent_id',
        'category:id,name',
    ];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'business_id' => [$isCreate ? 'required' : 'prohibited', 'integer', 'exists:businesses,id'],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'offering_type' => [$isCreate ? 'required' : 'sometimes', Rule::in(['product', 'service', 'menu'])],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'gte:price'],
            'currency' => ['nullable', 'string', 'max:3'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'is_available' => ['nullable', 'boolean'],
            'is_popular' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'approval_status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            // Optional: cascade to all branches if business is a main brand (default true).
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

    protected function cascadeToBranches(BusinessOffering $offering): void
    {
        $business = $offering->business()->with('branches:id,parent_id')->first();
        if (! $business || $business->parent_id !== null || $business->branches->isEmpty()) {
            return;
        }

        $template = $offering->only([
            'name', 'description', 'offering_type', 'category_id',
            'price', 'price_max', 'currency', 'image_url',
            'is_available', 'is_popular', 'is_featured', 'sort_order',
            'approval_status', 'approved_by', 'approved_at',
        ]);

        foreach ($business->branches as $branch) {
            BusinessOffering::create($template + ['business_id' => $branch->id]);
        }
    }
}
