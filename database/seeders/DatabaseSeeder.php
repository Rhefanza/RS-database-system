<?php

namespace Database\Seeders;

use App\Models\Citizen;
use App\Models\District;
use App\Models\Doctor;
use App\Models\Puskesmas;
use App\Models\PuskesmasService;
use App\Models\Queue;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'admin@puskesmas.test'], [
            'nama_lengkap' => 'Admin Dinas Kesehatan Demo',
            'email' => 'admin@puskesmas.test',
            'password_hash' => 'password',
            'role' => 'ADMIN',
            'status_akun' => 'AKTIF',
        ]);

        $citizens = collect(range(1, 80))->map(function (int $number) {
            $suffix = str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            return Citizen::updateOrCreate(['nik' => '357801010190'.$suffix], [
                'nama_lengkap' => 'Masyarakat Dummy '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'nomor_telepon' => '08123000'.$suffix,
                'alamat' => 'Alamat sintetis nomor '.$number.', Surabaya',
                'status_data' => 'AKTIF',
            ]);
        });

        foreach ($citizens->take(3) as $index => $citizen) {
            User::updateOrCreate(['email' => 'masyarakat'.($index + 1).'@puskesmas.test'], [
                'nik' => $citizen->nik,
                'nama_lengkap' => $citizen->nama_lengkap,
                'password_hash' => 'password',
                'role' => 'MASYARAKAT',
                'status_akun' => 'AKTIF',
            ]);
        }

        $services = collect([
            ['nama_layanan' => 'Poli Umum', 'deskripsi' => 'Pemeriksaan kesehatan umum dan keluhan dasar.'],
            ['nama_layanan' => 'Poli Gigi', 'deskripsi' => 'Pemeriksaan dan perawatan kesehatan gigi.'],
            ['nama_layanan' => 'KIA', 'deskripsi' => 'Pelayanan kesehatan ibu dan anak.'],
            ['nama_layanan' => 'Imunisasi', 'deskripsi' => 'Pelayanan imunisasi dasar dan lanjutan.'],
            ['nama_layanan' => 'Poli Gizi', 'deskripsi' => 'Konsultasi gizi dan pemantauan tumbuh kembang.'],
            ['nama_layanan' => 'Laboratorium', 'deskripsi' => 'Pemeriksaan laboratorium dasar.'],
        ])->map(fn (array $data) => Service::updateOrCreate(['nama_layanan' => $data['nama_layanan']], [...$data, 'status' => 'AKTIF']))->values();

        $clinics = [
            ['Asemrowo', -7.2479, 112.7024], ['Benowo', -7.2376, 112.6508], ['Bubutan', -7.2455, 112.7281],
            ['Bulak', -7.2290, 112.7903], ['Dukuh Pakis', -7.2890, 112.7063], ['Gayungan', -7.3298, 112.7247],
            ['Genteng', -7.2606, 112.7442], ['Gubeng', -7.2756, 112.7557], ['Gunung Anyar', -7.3377, 112.7818],
            ['Jambangan', -7.3218, 112.7145], ['Karang Pilang', -7.3370, 112.6903], ['Kenjeran', -7.2472, 112.7864],
            ['Krembangan', -7.2239, 112.7274], ['Lakarsantri', -7.3094, 112.6494], ['Mulyorejo', -7.2676, 112.7985],
            ['Pabean Cantian', -7.2219, 112.7408], ['Pakal', -7.2571, 112.6197], ['Rungkut', -7.3228, 112.7767],
            ['Sambikerep', -7.2787, 112.6530], ['Sawahan', -7.2811, 112.7243], ['Semampir', -7.2191, 112.7501],
            ['Simokerto', -7.2388, 112.7519], ['Sukolilo', -7.2854, 112.7975], ['Sukomanunggal', -7.2740, 112.7085],
            ['Tambaksari', -7.2517, 112.7682], ['Tandes', -7.2570, 112.6927], ['Tegalsari', -7.2767, 112.7396],
            ['Tenggilis Mejoyo', -7.3134, 112.7584], ['Wiyung', -7.3147, 112.6924], ['Wonocolo', -7.3192, 112.7367],
            ['Wonokromo', -7.3051, 112.7331],
        ];

        $doctorNames = [
            'dr. Nara Pratama', 'dr. Alya Maheswari', 'dr. Bima Santosa', 'dr. Citra Lestari',
            'dr. Damar Wicaksono', 'dr. Elina Putri', 'dr. Farhan Nugraha', 'dr. Gita Anindya',
            'dr. Hadi Kurniawan', 'dr. Intan Permata', 'dr. Jati Raharjo', 'dr. Kirana Dewi',
        ];
        $specializations = [
            'Poli Umum' => 'Dokter Umum', 'Poli Gigi' => 'Dokter Gigi', 'KIA' => 'Dokter Umum',
            'Imunisasi' => 'Dokter Umum', 'Poli Gizi' => 'Dokter Umum', 'Laboratorium' => 'Dokter Umum',
        ];
        $today = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];

        $activeCitizenCursor = 0;

        foreach ($clinics as $clinicIndex => [$districtName, $latitude, $longitude]) {
            $district = District::updateOrCreate(['nama_kecamatan' => $districtName]);
            $clinicName = match ($districtName) {
                'Genteng' => 'Puskesmas Ketabang',
                'Mulyorejo' => 'Puskesmas Mulyorejo',
                'Wonokromo' => 'Puskesmas Jagir',
                default => 'Puskesmas '.$districtName,
            };
            $puskesmas = Puskesmas::where('nama_puskesmas', $clinicName)->first()
                ?? Puskesmas::where('nama_puskesmas', 'Puskesmas Demo '.$districtName)->first();
            $puskesmasData = [
                'kecamatan_id' => $district->kecamatan_id,
                'nama_puskesmas' => $clinicName,
                'alamat' => 'Jl. Raya '.$districtName.' No. '.($clinicIndex + 1).', Surabaya',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'nomor_telepon' => '0317000'.str_pad((string) ($clinicIndex + 1), 3, '0', STR_PAD_LEFT),
                'status' => 'AKTIF',
            ];
            if ($puskesmas) {
                $puskesmas->update($puskesmasData);
            } else {
                $puskesmas = Puskesmas::create($puskesmasData);
            }

            $officerEmail = 'petugas_'.Str::slug(preg_replace('/^Puskesmas\s+/i', '', $clinicName), '_').'@test';
            User::updateOrCreate(['email' => $officerEmail], [
                'puskesmas_id' => $puskesmas->puskesmas_id,
                'nama_lengkap' => 'Petugas Demo '.$districtName,
                'password_hash' => 'password',
                'role' => 'PETUGAS',
                'status_akun' => 'AKTIF',
            ]);

            $schedules = collect();
            foreach ([0, 1, 3] as $offset) {
                $service = $services[($clinicIndex + $offset) % $services->count()];
                $relation = PuskesmasService::updateOrCreate([
                    'puskesmas_id' => $puskesmas->puskesmas_id,
                    'layanan_id' => $service->layanan_id,
                ], [
                    'status' => 'AKTIF',
                ]);
                $doctorName = $doctorNames[($clinicIndex + $offset * 2) % count($doctorNames)];
                Doctor::updateOrCreate([
                    'puskesmas_layanan_id' => $relation->puskesmas_layanan_id,
                    'nama_dokter' => $doctorName,
                ], [
                    'spesialisasi' => $specializations[$service->nama_layanan],
                    'status' => 'AKTIF',
                ]);
                $schedules->push(Schedule::updateOrCreate([
                    'puskesmas_layanan_id' => $relation->puskesmas_layanan_id,
                    'hari' => $today,
                ], [
                    'jam_buka' => $offset === 3 ? '09:00' : '08:00',
                    'jam_tutup' => $offset === 3 ? '13:00' : '12:00',
                    'kapasitas' => 50,
                    'status' => 'AKTIF',
                ]));
            }

            // Maksimal 80 antrean aktif agar satu masyarakat tidak muncul pada
            // dua poli yang waktunya bertabrakan dalam data simulasi awal.
            $queueCount = 2 + (($clinicIndex * 3) % 4);
            $schedule = $schedules->first();
            for ($queueIndex = 0; $queueIndex < $queueCount; $queueIndex++) {
                $citizen = $queueIndex === 0
                    ? $citizens[$clinicIndex % $citizens->count()]
                    : $citizens[$activeCitizenCursor++ % $citizens->count()];
                Queue::updateOrCreate([
                    'jadwal_id' => $schedule->jadwal_id,
                    'nomor_antrean' => $queueIndex + 1,
                    'tanggal_daftar' => today(),
                ], [
                    'nik' => $citizen->nik,
                    'status_antrean' => match ($queueIndex) {
                        0 => 'COMPLETED',
                        1 => 'SERVING',
                        2 => 'CALLED',
                        default => 'WAITING',
                    },
                ]);
            }
        }
    }
}
