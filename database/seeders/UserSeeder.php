<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@5srt.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
            'phone' => '+8801712345678',
            'is_active' => true,
            'city' => 'Dhaka',
            'trust_level' => 5
        ]);
        $admin->assignRole('super-admin');

        // Create business owner users
        $businessOwners = [
            [
                'name' => 'Ahmed Khan',
                'email' => 'ahmed@example.com',
                'phone' => '+8801812345678'
            ],
            [
                'name' => 'Fatima Rahman',
                'email' => 'fatima@example.com',
                'phone' => '+8801912345678'
            ],
            [
                'name' => 'Mohammad Ali',
                'email' => 'ali@example.com',
                'phone' => '+8801612345678'
            ],
            [
                'name' => 'Rashida Begum',
                'email' => 'rashida@example.com',
                'phone' => '+8801512345678'
            ],
            [
                'name' => 'Karim Uddin',
                'email' => 'karim@example.com',
                'phone' => '+8801412345678'
            ]
        ];

        foreach ($businessOwners as $owner) {
            $user = User::create([
                'name' => $owner['name'],
                'email' => $owner['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'phone' => $owner['phone'],
                'is_active' => true,
                'city' => 'Dhaka',
                'trust_level' => 3
            ]);
            $user->assignRole('business-owner');
        }

        // Create regular users
        $regularUsers = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'phone' => '+8801312345678'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'phone' => '+8801212345678'
            ],
            [
                'name' => 'Rahat Ahmed',
                'email' => 'rahat@example.com',
                'phone' => '+8801112345678'
            ],
            [
                'name' => 'Sadia Islam',
                'email' => 'sadia@example.com',
                'phone' => '+8801012345678'
            ],
            [
                'name' => 'Tarek Hassan',
                'email' => 'tarek@example.com',
                'phone' => '+8801812345679'
            ],
            [
                'name' => 'Nusrat Jahan',
                'email' => 'nusrat@example.com',
                'phone' => '+8801712345679'
            ],
            [
                'name' => 'Mahmud Rahman',
                'email' => 'mahmud@example.com',
                'phone' => '+8801612345679'
            ],
            [
                'name' => 'Ayesha Khatun',
                'email' => 'ayesha@example.com',
                'phone' => '+8801512345679'
            ]
        ];

        foreach ($regularUsers as $user) {
            $newUser = User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password123'),
                'phone' => $user['phone'],
                'is_active' => true,
                'city' => 'Dhaka',
                'trust_level' => 2
            ]);
            $newUser->assignRole('user');
        }
    }
}
