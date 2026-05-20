<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Resources\Admin\AttractionGalleryResource;
use App\Models\AttractionGallery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AttractionGalleryAdminController extends AdminResourceController
{
    protected static string $model = AttractionGallery::class;
    protected static string $resource = AttractionGalleryResource::class;
    protected static array $searchable = ['title', 'description'];
    protected static array $sortable = ['id', 'sort_order', 'created_at'];
    protected static array $filters = [
        'attraction_id' => 'attraction_id',
        'image_type' => 'image_type',
        'is_cover' => 'is_cover',
        'is_active' => 'is_active',
    ];
    protected static string $defaultSort = 'sort_order';

    protected function validationRules(Request $request, ?Model $record = null): array
    {
        $isCreate = $record === null;

        return [
            'attraction_id' => [$isCreate ? 'required' : 'prohibited', 'integer', 'exists:attractions,id'],
            'image_url' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:500'],
            'image_path' => ['nullable', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_cover' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'image_type' => ['nullable', 'string', 'max:50'],
            'meta_data' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function afterStore(Model $record, Request $request, array $data): void
    {
        $record->forceFill(['uploaded_by' => Auth::id()])->save();
        $this->enforceCoverUniqueness($record);
    }

    protected function afterUpdate(Model $record, Request $request, array $data): void
    {
        $this->enforceCoverUniqueness($record);
    }

    private function enforceCoverUniqueness(AttractionGallery $image): void
    {
        if (! $image->is_cover) {
            return;
        }
        AttractionGallery::where('attraction_id', $image->attraction_id)
            ->where('id', '!=', $image->id)
            ->update(['is_cover' => false]);
    }
}
