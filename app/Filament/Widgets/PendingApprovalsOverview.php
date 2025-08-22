<?php

namespace App\Filament\Widgets;

use App\Models\Business;
use App\Models\BusinessOffering;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PendingApprovalsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole(['super-admin', 'admin', 'moderator']);
    }

    protected function getStats(): array
    {
        $pendingBusinesses = Business::where('approval_status', 'pending')->count();
        $pendingOfferings = BusinessOffering::where('approval_status', 'pending')->count();
        $rejectedBusinesses = Business::where('approval_status', 'rejected')->count();
        $approvedBusinesses = Business::where('approval_status', 'approved')->count();

        return [
            Stat::make('Pending Business Approvals', $pendingBusinesses)
                ->description('Businesses waiting for approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->url(route('filament.admin.resources.businesses.index', ['tableFilters' => ['approval_status' => ['value' => 'pending']]])),
            
            Stat::make('Pending Offering Approvals', $pendingOfferings)
                ->description('Business offerings waiting for approval')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            
            Stat::make('Approved Businesses', $approvedBusinesses)
                ->description('Total approved businesses')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            
            Stat::make('Rejected Items', $rejectedBusinesses)
                ->description('Rejected business applications')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
