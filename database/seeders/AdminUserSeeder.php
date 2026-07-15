<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate([
            'email' => 'admin@mail.com',
        ], [
            'name' => 'Admin',
            'password' => 'admin',
            'is_admin' => true,
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);

        if (! $admin->is_admin || $admin->role !== 'super_admin') {
            $admin->update(['is_admin' => true, 'role' => 'super_admin']);
        }
    }
}
