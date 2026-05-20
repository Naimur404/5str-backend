<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\UserAttractionInteractionResource;
use App\Models\UserAttractionInteraction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class UserAttractionInteractionAdminController extends AdminResourceController
{
    protected static string $model = UserAttractionInteraction::class;
    protected static string $resource = UserAttractionInteractionResource::class;
    protected static array $searchable = ['notes'];
    protected static array $sortable = ['id', 'interaction_date', 'created_at'];
    protected static array $filters = [
        'attraction_id' => 'attraction_id',
        'user_id' => 'user_id',
        'interaction_type' => 'interaction_type',
        'is_public' => 'is_public',
        'is_active' => 'is_active',
    ];
    protected static array $with = ['user:id,name', 'attraction:id,name'];

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'user_rating' => ['nullable', 'numeric', 'between:1,5'],
        ];
    }
}
