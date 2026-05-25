<?php

namespace Database\Seeders;

use App\Models\Band;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $band = Band::create([
            'name' => 'Praise Team',
            'code' => 'TEST01',
            'access_pin' => Hash::make('1234'),
            'edit_pin' => Hash::make('5678'),
        ]);

        User::create([
            'band_id' => $band->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);
    }
}
