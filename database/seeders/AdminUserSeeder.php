<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('INITIAL_ADMIN_USERNAME');
        $password = env('INITIAL_ADMIN_PASSWORD');
        if (blank($username) || blank($password)) return;

        User::updateOrCreate(
            ['username' => $username],
            ['name' => 'NMS Administrator', 'email' => null, 'role' => 'administrator', 'is_active' => true, 'password' => $password]
        );
    }
}
