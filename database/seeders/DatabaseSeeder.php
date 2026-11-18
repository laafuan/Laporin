<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun admin. Segera ganti kata sandinya setelah aplikasi dipakai sungguhan.
        User::updateOrCreate(
            ['username' => 'admin'],
            ['name' => 'Administrator', 'password' => 'Admin1234', 'is_admin' => true]
        );
    }
}
