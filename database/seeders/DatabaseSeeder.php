<?php

namespace Database\Seeders;

use App\Models\Branch;
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
        // 1. Buat Data Cabang
        $branch1 = Branch::create([
            'name' => 'Sains De Resto',
            'address' => 'Jl. Sudirman No 1',
            'phone' => '021-123456',
        ]);

        $branch2 = Branch::create([
            'name' => 'Sains De Ship',
            'address' => 'Jl. Bandung No 2',
            'phone' => '022-654321',
        ]);

        // 2. Siapkan Data User (Masing-masing 2 per role, per cabang)
        $users = [
            // --- ROLE: ADMIN ---
            [
                'name' => 'Admin Jkt 1',
                'email' => 'admin1.jkt@resto.com',
                'password' => '12345678',
                'role' => 'admin',
                'phone' => '081100000001',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Admin Jkt 2',
                'email' => 'admin2.jkt@resto.com',
                'password' => '12345678',
                'role' => 'admin',
                'phone' => '081100000002',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Admin Bdg 1',
                'email' => 'admin1.bdg@resto.com',
                'password' => '12345678',
                'role' => 'admin',
                'phone' => '081100000003',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
            [
                'name' => 'Admin Bdg 2',
                'email' => 'admin2.bdg@resto.com',
                'password' => '12345678',
                'role' => 'admin',
                'phone' => '081100000004',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],

            // --- ROLE: KASIR ---
            [
                'name' => 'Kasir Jkt 1',
                'email' => 'kasir1.jkt@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000005',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Kasir Jkt 2',
                'email' => 'kasir2.jkt@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000006',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Kasir Bdg 1',
                'email' => 'kasir1.bdg@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000007',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
            [
                'name' => 'Kasir Bdg 2',
                'email' => 'kasir2.bdg@resto.com',
                'password' => '12345678',
                'role' => 'kasir',
                'phone' => '081100000008',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],

            // --- ROLE: MANAJER ---
            [
                'name' => 'Manajer Jkt 1',
                'email' => 'manajer1.jkt@resto.com',
                'password' => '12345678',
                'role' => 'manajer',
                'phone' => '081100000009',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Manajer Jkt 2',
                'email' => 'manajer2.jkt@resto.com',
                'password' => '12345678',
                'role' => 'manajer',
                'phone' => '081100000010',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Manajer Bdg 1',
                'email' => 'manajer1.bdg@resto.com',
                'password' => '12345678',
                'role' => 'manajer',
                'phone' => '081100000011',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
            [
                'name' => 'Manajer Bdg 2',
                'email' => 'manajer2.bdg@resto.com',
                'password' => '12345678',
                'role' => 'manajer',
                'phone' => '081100000012',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],

            // --- ROLE: CHEF ---
            [
                'name' => 'Chef Jkt 1',
                'email' => 'chef1.jkt@resto.com',
                'password' => '12345678',
                'role' => 'chef',
                'phone' => '081100000013',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Chef Jkt 2',
                'email' => 'chef2.jkt@resto.com',
                'password' => '12345678',
                'role' => 'chef',
                'phone' => '081100000014',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Chef Bdg 1',
                'email' => 'chef1.bdg@resto.com',
                'password' => '12345678',
                'role' => 'chef',
                'phone' => '081100000015',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
            [
                'name' => 'Chef Bdg 2',
                'email' => 'chef2.bdg@resto.com',
                'password' => '12345678',
                'role' => 'chef',
                'phone' => '081100000016',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],

            // --- ROLE: WAITER ---
            [
                'name' => 'Waiter Jkt 1',
                'email' => 'waiter1.jkt@resto.com',
                'password' => '12345678',
                'role' => 'waiter',
                'phone' => '081100000017',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Waiter Jkt 2',
                'email' => 'waiter2.jkt@resto.com',
                'password' => '12345678',
                'role' => 'waiter',
                'phone' => '081100000018',
                'is_active' => true,
                'branch_id' => $branch1->id,
            ],
            [
                'name' => 'Waiter Bdg 1',
                'email' => 'waiter1.bdg@resto.com',
                'password' => '12345678',
                'role' => 'waiter',
                'phone' => '081100000019',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
            [
                'name' => 'Waiter Bdg 2',
                'email' => 'waiter2.bdg@resto.com',
                'password' => '12345678',
                'role' => 'waiter',
                'phone' => '081100000020',
                'is_active' => true,
                'branch_id' => $branch2->id,
            ],
        ];

        // 3. Eksekusi Insert/Update ke Database
        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']], // Patokan update berdasarkan email
                $user
            );
        }

        // 4. Seed Karyawan
        $this->call([
            KaryawanSeeder::class,
        ]);
    }
}
