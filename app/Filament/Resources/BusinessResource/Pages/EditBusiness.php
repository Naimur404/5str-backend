<?php

namespace App\Filament\Resources\BusinessResource\Pages;

use App\Filament\Resources\BusinessResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;

class EditBusiness extends EditRecord
{
    protected static string $resource = BusinessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin'])),
            Actions\Action::make('approve_changes')
                ->label('Approve Changes')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']) && 
                         $this->record->approval_status === 'pending')
                ->action(function () {
                    $this->record->update([
                        'approval_status' => 'approved',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                        'pending_changes' => null,
                    ]);
                    
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\Action::make('reject_changes')
                ->label('Reject Changes')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']) && 
                         $this->record->approval_status === 'pending')
                ->action(function () {
                    $this->record->update([
                        'approval_status' => 'rejected',
                        'approved_by' => Auth::id(),
                        'approved_at' => now(),
                    ]);
                    
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
        ];
    }

    protected function beforeSave(): void
    {
        // If user is business owner, save changes to pending_changes instead of directly updating
        if (Auth::user()?->hasRole('business-owner')) {
            $dirtyFields = $this->record->getDirty();
            
            // Remove fields that business owners shouldn't be able to change
            $restrictedFields = ['is_verified', 'is_featured', 'approval_status', 'approved_by', 'approved_at'];
            foreach ($restrictedFields as $field) {
                unset($dirtyFields[$field]);
            }
            
            if (!empty($dirtyFields)) {
                $this->record->pending_changes = $dirtyFields;
                $this->record->approval_status = 'pending';
                
                // Reset the dirty fields to prevent direct update
                foreach ($dirtyFields as $field => $value) {
                    $this->record->setAttribute($field, $this->record->getOriginal($field));
                }
            }
        }
    }
}
