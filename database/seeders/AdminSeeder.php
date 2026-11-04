<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@flore.test'],
            [
                'name' => 'Admin Floré',
                'phone' => '081234567890',
                'role' => 'admin',
                'password' => 'password', // di-hash bcrypt otomatis oleh cast 'hashed'
                'email_verified_at' => now(),
            ]
        );
    }
}