<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['codigo' => 1],
            ['name' => 'Administrador', 'password' => '138257', 'is_admin' => true]
        );
    }
}