<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Models
use DDD\Domain\Base\Users\User;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'John Doe',
            'email' => 'john@doe.com',
            'role' => 'admin',
            'organization_id' => 1,
            'email_verified_at' => now(),
            'password' => Hash::make(''),
        ]);
    }
}
