<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BusinessImageResource;
use App\Models\BusinessImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessImageAdminController extends AdminResourceController
{
    protected static string $model = BusinessImage::class;
    protected static string $resource = BusinessImageResource::class;
    protected static array $sortable = ['id', 'sort_order', 'created_at'];
    protected static array $filters = [
        'business_id' => 'business_id',
        'image_type' => 'image_type',
        'is_primary' => 'is_primary',
    ];
    protected static string $defaultSort = 'sort_order';

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'business_id' => [$isCreate ? 'required' : 'prohibited', 'integer', 'exists:businesses,id'],
            'image_url' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:500'],
            'image_type' => ['nullable', Rule::in(['logo', 'cover', 'gallery'])],
            'sort_order' => ['nullable', 'integer'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * When marking an image as primary, ensure no other image of the same
     * (business, image_type) keeps the flag.
     */
    protected function afterStore(Model $record, Request $request, array $data): void
    {
        $this->enforcePrimaryUniqueness($record);
    }

    protected function afterUpdate(Model $record, Request $request, array $data): void
    {
        $this->enforcePrimaryUniqueness($record);
    }

    private function enforcePrimaryUniqueness(BusinessImage $image): void
    {
        if (! $image->is_primary) {
            return;
        }
        BusinessImage::where('business_id', $image->business_id)
            ->where('image_type', $image->image_type)
            ->where('id', '!=', $image->id)
            ->update(['is_primary' => false]);
    }
}
