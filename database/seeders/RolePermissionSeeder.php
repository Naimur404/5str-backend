<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // User Management
            'view-users',
            'create-users',
            'edit-users',
            'delete-users',
            
            // Business Management
            'view-businesses',
            'create-businesses',
            'edit-businesses',
            'delete-businesses',
            'approve-businesses',
            
            // Category Management
            'view-categories',
            'create-categories',
            'edit-categories',
            'delete-categories',
            
            // Review Management
            'view-reviews',
            'edit-reviews',
            'delete-reviews',
            'moderate-reviews',
            
            // Analytics
            'view-analytics',
            'view-search-logs',
            'view-trending-data',
            
            // Role & Permission Management
            'view-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'view-permissions',
            'create-permissions',
            'edit-permissions',
            'delete-permissions',
            
            // System Management
            'access-admin-panel',
            'manage-system-settings',
            'view-logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Super Admin - has all permissions
        $superAdmin = Role::create(['name' => 'super-admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Admin - most permissions except system management
        $admin = Role::create(['name' => 'admin']);
        $adminPermissions = [
            'view-users', 'create-users', 'edit-users', 'delete-users',
            'view-businesses', 'create-businesses', 'edit-businesses', 'delete-businesses', 'approve-businesses',
            'view-categories', 'create-categories', 'edit-categories', 'delete-categories',
            'view-reviews', 'edit-reviews', 'delete-reviews', 'moderate-reviews',
            'view-analytics', 'view-search-logs', 'view-trending-data',
            'access-admin-panel',
        ];
        $admin->givePermissionTo($adminPermissions);

        // Moderator - content moderation and basic management
        $moderator = Role::create(['name' => 'moderator']);
        $moderatorPermissions = [
            'view-users', 'edit-users',
            'view-businesses', 'edit-businesses', 'approve-businesses',
            'view-categories',
            'view-reviews', 'edit-reviews', 'delete-reviews', 'moderate-reviews',
            'view-analytics', 'view-search-logs',
            'access-admin-panel',
        ];
        $moderator->givePermissionTo($moderatorPermissions);

        // Business Owner - can manage their own businesses
        $businessOwner = Role::create(['name' => 'business-owner']);
        $businessOwnerPermissions = [
            'view-businesses', 'edit-businesses',
            'view-reviews',
            'access-admin-panel',
        ];
        $businessOwner->givePermissionTo($businessOwnerPermissions);

        // User - basic user permissions
        $user = Role::create(['name' => 'user']);
        // Users don't need admin panel access

        // Assign super-admin role to the first user if exists
        $firstUser = User::first();
        if ($firstUser) {
            $firstUser->assignRole('super-admin');
        }

        $this->command->info('Roles and permissions created successfully!');
        $this->command->info('Available roles: super-admin, admin, moderator, business-owner, user');
        if ($firstUser) {
            $this->command->info("Super-admin role assigned to user: {$firstUser->email}");
        }
    }
}
