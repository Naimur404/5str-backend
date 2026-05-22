<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'make:admin {email} {password} {name}';

    protected $description = 'Create an admin user';

    public function handle(): int
    {
        /** @var string $email */
        $email = $this->argument('email');
        /** @var string $password */
        $password = $this->argument('password');
        /** @var string $name */
        $name = $this->argument('name');

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
        );

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole($adminRole);

        $this->info('Admin user created successfully!');
        $this->info("Email: {$email}");

        return self::SUCCESS;
    }
}
