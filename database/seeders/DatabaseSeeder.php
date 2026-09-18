<?php

namespace Database\Seeders;

use App\Models\Citizen;
use App\Models\Puskesmas;
use App\Models\PuskesmasService;
use App\Models\Queue;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'nama_lengkap' => 'Admin Dinas Kesehatan',
            'email' => 'admin@puskesmas.test',
            'password_hash' => 'password',
            'role' => 'ADMIN',
            'status_akun' => 'AKTIF',
        ]);

        $citizens = collect(range(1, 15))->map(function (int $number) {
            $suffix = str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            return Citizen::create([
                'nik' => '357801010190'.$suffix,
                'nama_lengkap' => 'Masyarakat Dummy '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'nomor_telepon' => '08123000'.$suffix,
                'alamat' => 'Alamat sintetis nomor '.$number.', Surabaya',
                'status_data' => 'AKTIF',
            ]);
        });

        foreach ($citizens->take(3) as $index => $citizen) {
            User::create([
                'nik' => $citizen->nik,
                'nama_lengkap' => $citizen->nama_lengkap,
                'email' => 'masyarakat'.($index + 1).'@puskesmas.test',
                'password_hash' => 'password',
                'role' => 'MASYARAKAT',
                'status_akun' => 'AKTIF',
            ]);
        }

        $puskesmasItems = collect([
            ['nama_puskesmas' => 'Puskesmas Ketabang', 'alamat' => 'Jl. Jaksa Agung Suprapto No. 10, Surabaya', 'latitude' => -7.2567000, 'longitude' => 112.7505000, 'nomor_telepon' => '0315344508'],
            ['nama_puskesmas' => 'Puskesmas Mulyorejo', 'alamat' => 'Jl. Mulyorejo Utara No. 201, Surabaya', 'latitude' => -7.2676000, 'longitude' => 112.7985000, 'nomor_telepon' => '0315921780'],
            ['nama_puskesmas' => 'Puskesmas Jagir', 'alamat' => 'Jl. Bendul Merisi No. 1, Surabaya', 'latitude' => -7.3066000, 'longitude' => 112.7444000, 'nomor_telepon' => '0318435980'],
        ])->map(fn (array $data) => Puskesmas::create([...$data, 'status' => 'AKTIF']));

        $services = collect([
            ['nama_layanan' => 'Poli Umum', 'deskripsi' => 'Pemeriksaan kesehatan umum.'],
            ['nama_layanan' => 'Poli Gigi', 'deskripsi' => 'Pemeriksaan dan perawatan gigi.'],
            ['nama_layanan' => 'KIA', 'deskripsi' => 'Pelayanan kesehatan ibu dan anak.'],
            ['nama_layanan' => 'Imunisasi', 'deskripsi' => 'Pelayanan imunisasi dasar.'],
            ['nama_layanan' => 'Laboratorium', 'deskripsi' => 'Pemeriksaan laboratorium dasar.'],
        ])->map(fn (array $data) => Service::create([...$data, 'status' => 'AKTIF']));

        $today = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $schedules = collect();
        foreach ($puskesmasItems as $puskesmasIndex => $puskesmas) {
            User::create([
                'puskesmas_id' => $puskesmas->puskesmas_id,
                'nama_lengkap' => 'Petugas '.$puskesmas->nama_puskesmas,
                'email' => $puskesmasIndex === 0 ? 'petugas@puskesmas.test' : 'petugas'.($puskesmasIndex + 1).'@puskesmas.test',
                'password_hash' => 'password',
                'role' => 'PETUGAS',
                'status_akun' => 'AKTIF',
            ]);

            foreach ($services->take(3) as $service) {
                $relation = PuskesmasService::create([
                    'puskesmas_id' => $puskesmas->puskesmas_id,
                    'layanan_id' => $service->layanan_id,
                    'status' => 'AKTIF',
                ]);
                $schedules->push(Schedule::create([
                    'puskesmas_layanan_id' => $relation->puskesmas_layanan_id,
                    'hari' => $today,
                    'jam_buka' => '08:00',
                    'jam_tutup' => '12:00',
                    'kapasitas' => 50,
                    'status' => 'AKTIF',
                ]));
            }
        }

        foreach ($citizens->take(8) as $index => $citizen) {
            Queue::create([
                'nik' => $citizen->nik,
                'jadwal_id' => $schedules->first()->jadwal_id,
                'nomor_antrean' => $index + 1,
                'tanggal_daftar' => today(),
                'status_antrean' => match (true) {
                    $index < 2 => 'COMPLETED',
                    $index === 2 => 'SERVING',
                    default => 'WAITING',
                },
            ]);
        }
    }
}
