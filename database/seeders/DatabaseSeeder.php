<?php

namespace Database\Seeders;

use App\Models\Band;
use App\Models\ScheduleTemplate;
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

        // Default schedule templates
        $templates = [
            ['name' => 'Domingo 7:30 AM',  'day_of_week' => 0, 'time' => '07:30', 'sort_order' => 0],
            ['name' => 'Domingo 10 AM',    'day_of_week' => 0, 'time' => '10:00', 'sort_order' => 1],
            ['name' => 'Domingo 3 PM',     'day_of_week' => 0, 'time' => '15:00', 'sort_order' => 2],
            ['name' => 'Lunes 6:15 PM',    'day_of_week' => 1, 'time' => '18:15', 'sort_order' => 3],
            ['name' => 'Martes 6:15 PM',   'day_of_week' => 2, 'time' => '18:15', 'sort_order' => 4],
            ['name' => 'Viernes 6:15 PM',  'day_of_week' => 5, 'time' => '18:15', 'sort_order' => 5],
        ];

        foreach ($templates as $tpl) {
            ScheduleTemplate::create([...$tpl, 'band_id' => $band->id]);
        }
    }
}
