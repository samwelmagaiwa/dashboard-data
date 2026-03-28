<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Dr. Executive Director',
                'email' => 'ed@mloganzila.or.tz',
                'password' => Hash::make('12345678'),
                'role' => 'ED',
            ],
            [
                'name' => 'Dr. Deputy Director',
                'email' => 'ded@mloganzila.or.tz',
                'password' => Hash::make('12345678'),
                'role' => 'DED',
            ],
            [
                'name' => 'ICT Director',
                'email' => 'dict@mloganzila.or.tz',
                'password' => Hash::make('12345678'),
                'role' => 'DICT',
            ],
            [
                'name' => 'Nurse Supervisor',
                'email' => 'nurse@mloganzila.or.tz',
                'password' => Hash::make('12345678'),
                'role' => 'Nurse',
            ],
        ];

        foreach ($users as $userData) {
            \App\Models\User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
