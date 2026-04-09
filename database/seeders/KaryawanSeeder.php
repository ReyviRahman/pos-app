<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Karyawan;
use Illuminate\Database\Seeder;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        $branch1 = Branch::where('name', 'Sains De Resto')->first();
        $branch2 = Branch::where('name', 'Sains De Ship')->first();

        $karyawans = [
            [
                'nik' => 'KRY001',
                'nama' => 'Budi Santoso',
                'branch_id' => $branch1?->id,
                'limit_potongan_harian' => 20000,
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '17:00:00',
                'is_active' => true,
            ],
            [
                'nik' => 'KRY002',
                'nama' => 'Siti Rahayu',
                'branch_id' => $branch1?->id,
                'limit_potongan_harian' => 25000,
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '16:00:00',
                'is_active' => true,
            ],
            [
                'nik' => 'KRY003',
                'nama' => 'Ahmad Fauzi',
                'branch_id' => $branch1?->id,
                'limit_potongan_harian' => 15000,
                'jam_mulai' => '22:00:00',
                'jam_selesai' => '06:00:00',
                'is_active' => true,
            ],
            [
                'nik' => 'KRY004',
                'nama' => 'Dewi Lestari',
                'branch_id' => $branch1?->id,
                'limit_potongan_harian' => 20000,
                'jam_mulai' => '08:00:00',
                'jam_selesai' => '17:00:00',
                'is_active' => false,
            ],
            [
                'nik' => 'KRY005',
                'nama' => 'Rudi Hermawan',
                'branch_id' => $branch2?->id,
                'limit_potongan_harian' => 20000,
                'jam_mulai' => '09:00:00',
                'jam_selesai' => '18:00:00',
                'is_active' => true,
            ],
            [
                'nik' => 'KRY006',
                'nama' => 'Lisa Amelia',
                'branch_id' => $branch2?->id,
                'limit_potongan_harian' => 20000,
                'jam_mulai' => '14:00:00',
                'jam_selesai' => '22:00:00',
                'is_active' => true,
            ],
        ];

        foreach ($karyawans as $karyawan) {
            Karyawan::updateOrCreate(
                ['nik' => $karyawan['nik']],
                $karyawan
            );
        }
    }
}
