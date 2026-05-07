<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name'     => 'Super Admin',
            'email'    => 'admin@marketplace.dev',
            'password' => bcrypt('admin123'),
        ]);
    }
}
