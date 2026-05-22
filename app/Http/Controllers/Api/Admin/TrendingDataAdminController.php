<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\TrendingDataResource;
use App\Models\TrendingData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrendingDataAdminController extends AdminResourceController
{
    protected static string $model = TrendingData::class;
    protected static string $resource = TrendingDataResource::class;
    protected static array $searchable = ['item_name', 'location_area'];
    protected static array $sortable = [
        'id', 'trend_score', 'hybrid_score', 'search_count', 'view_count',
        'date_period', 'created_at',
    ];
    protected static array $filters = [
        'item_type' => 'item_type',
        'item_id' => 'item_id',
        'time_period' => 'time_period',
        'location_area' => 'location_area',
        'date_period' => 'date_period',
    ];
    protected static string $defaultSort = '-trend_score';

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'item_type' => [$isCreate ? 'required' : 'sometimes', Rule::in(['business', 'category', 'offering', 'attraction'])],
            'item_id' => [$isCreate ? 'required' : 'sometimes', 'integer'],
            'item_name' => ['nullable', 'string', 'max:255'],
            'location_area' => ['nullable', 'string', 'max:100'],
            'trend_score' => ['nullable', 'numeric'],
            'hybrid_score' => ['nullable', 'numeric'],
            'search_count' => ['nullable', 'integer', 'min:0'],
            'view_count' => ['nullable', 'integer', 'min:0'],
            'time_period' => [$isCreate ? 'required' : 'sometimes', Rule::in(['daily', 'weekly', 'monthly'])],
            'date_period' => [$isCreate ? 'required' : 'sometimes', 'date'],
        ];
    }
}
