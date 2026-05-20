<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\BannerResource;
use App\Models\Banner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class BannerAdminController extends AdminResourceController
{
    protected static string $model = Banner::class;
    protected static string $resource = BannerResource::class;
    protected static array $searchable = ['title', 'subtitle', 'position'];
    protected static array $sortable = ['id', 'sort_order', 'start_date', 'end_date', 'created_at'];
    protected static array $filters = [
        'is_active' => 'is_active',
        'position' => 'position',
        'link_type' => 'link_type',
    ];
    protected static string $defaultSort = 'sort_order';

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:500'],
            'image_url' => ['required', 'string', 'max:500'],
            'link_type' => ['nullable', 'string', 'in:business,category,offer,attraction,url'],
            'link_id' => ['nullable', 'integer'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'position' => ['nullable', 'string', 'max:50'],
            'target_location' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
