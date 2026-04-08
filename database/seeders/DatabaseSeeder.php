<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@resto.com',
                'password' => '12345678',
                'role' => 'admin',
                'phone' => '081100000001',
                'is_active' => true,
            ],
            [
                'name' => 'Kasir Utama',
                'email' => 'kasir@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000002',
                'is_active' => true,
            ],
            [
                'name' => 'Kasir Dua',
                'email' => 'kasir2@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000003',
                'is_active' => true,
            ],
            [
                'name' => 'Manajer Operasional',
                'email' => 'manajer@resto.com',
                'password' => '12345678',
                'role' => 'manajer',
                'phone' => '081100000003',
                'is_active' => true,
            ],
            [
                'name' => 'Chef Kepala',
                'email' => 'chef@resto.com',
                'password' => '12345678',
                'role' => 'chef',
                'phone' => '081100000004',
                'is_active' => true,
            ],
            [
                'name' => 'Pelayan Meja',
                'email' => 'waiter@resto.com',
                'password' => '12345678',
                'role' => 'waiter',
                'phone' => '081100000005',
                'is_active' => true,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
