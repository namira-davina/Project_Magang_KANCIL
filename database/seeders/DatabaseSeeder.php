<?php

namespace Database\Seeders;

use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        foreach (['Paspor', 'Visa', 'Izin Tinggal', 'Layanan Umum'] as $name) {
            FaqCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
