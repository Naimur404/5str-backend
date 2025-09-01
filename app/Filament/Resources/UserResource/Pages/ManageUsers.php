<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Log;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    // Debug: Log what data we're receiving
                    Log::info('Create User Form Data:', $data);
                    
                    // Hash the password if provided
                    if (!empty($data['password'])) {
                        $data['password'] = \Illuminate\Support\Facades\Hash::make($data['password']);
                    }
                    
                    // Remove password_confirmation as it's not needed in the database
                    unset($data['password_confirmation']);
                    
                    Log::info('Create User Form Data After Processing:', $data);
                    
                    return $data;
                }),
        ];
    }
}
