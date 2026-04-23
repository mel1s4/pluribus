<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RootUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'root'],
            [
                'name' => 'Root',
                'email' => 'root@pluribus.local',
                'password' => 'ChangeMe2026',
                'is_root' => true,
                'user_type' => 'root',
                'email_verified_at' => now(),
            ],
        );
    }
}
