<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateBusiness extends CreateRecord
{
    protected static string $resource = BusinessResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
}
