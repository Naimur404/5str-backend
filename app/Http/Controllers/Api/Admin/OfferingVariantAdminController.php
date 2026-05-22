<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\OfferingVariantResource;
use App\Models\OfferingVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class OfferingVariantAdminController extends AdminResourceController
{
    protected static string $model = OfferingVariant::class;
    protected static string $resource = OfferingVariantResource::class;
    protected static array $searchable = ['variant_name', 'variant_value'];
    protected static array $sortable = ['id', 'variant_name', 'created_at'];
    protected static array $filters = [
        'offering_id' => 'offering_id',
        'is_available' => 'is_available',
    ];
    protected static array $with = ['offering:id,name,business_id'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'offering_id' => [$isCreate ? 'required' : 'prohibited', 'integer', 'exists:business_offerings,id'],
            'variant_name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'variant_value' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'price_adjustment' => ['nullable', 'numeric'],
            'is_available' => ['nullable', 'boolean'],
        ];
    }
}
