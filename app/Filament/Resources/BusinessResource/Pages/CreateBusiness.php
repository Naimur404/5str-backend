<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use App\Models\Business;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected array $pendingBranches = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingBranches = $data['branches'] ?? [];
        unset($data['branches']);

        // Set default approval status based on user role
        if (Auth::user()?->hasRole('business-owner')) {
            $data['approval_status'] = 'pending';
            $data['owner_user_id'] = Auth::id(); // Auto-assign to current business owner
        } else {
            $data['approval_status'] = 'approved';
            $data['approved_by'] = Auth::id();
            $data['approved_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        if (empty($this->pendingBranches)) {
            return;
        }

        $parent = $this->record;

        foreach ($this->pendingBranches as $branchData) {
            Business::create([
                'parent_id' => $parent->id,
                'business_name' => $branchData['business_name'],
                'slug' => $this->generateUniqueBranchSlug($parent->slug, $branchData['business_name']),
                'description' => $parent->description,
                'category_id' => $parent->category_id,
                'subcategory_id' => $parent->subcategory_id,
                'owner_user_id' => $parent->owner_user_id,
                'business_email' => $parent->business_email,
                'business_phone' => $branchData['business_phone'] ?? $parent->business_phone,
                'website_url' => $parent->website_url,
                'full_address' => $branchData['full_address'],
                'latitude' => $branchData['latitude'],
                'longitude' => $branchData['longitude'],
                'city' => $branchData['city'],
                'area' => $branchData['area'],
                'landmark' => $branchData['landmark'] ?? null,
                'opening_hours' => $parent->opening_hours,
                'price_range' => $parent->price_range,
                'has_delivery' => $parent->has_delivery,
                'has_pickup' => $parent->has_pickup,
                'has_parking' => $parent->has_parking,
                'is_active' => true,
                'approval_status' => $parent->approval_status,
                'approved_by' => $parent->approved_by,
                'approved_at' => $parent->approved_at,
            ]);
        }
    }

    protected function generateUniqueBranchSlug(string $parentSlug, string $branchName): string
    {
        $base = Str::slug($parentSlug . '-' . $branchName);
        $slug = $base;
        $i = 1;
        while (Business::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
