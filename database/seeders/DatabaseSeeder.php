<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Facility;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\Queue;
use App\Models\QueueSession;
use App\Models\QueueSnapshot;
use App\Models\SavedLocation;
use App\Models\Service;
use App\Models\ServiceDesk;
use App\Models\ServiceSchedule;
use App\Models\SpecialServiceSchedule;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoPassword = Hash::make('password');
        $admin = User::updateOrCreate(
            ['email' => 'admin@rujuk.test'],
            ['name' => 'Admin Rujuk', 'password' => $demoPassword, 'role' => 'ADMIN', 'account_status' => 'ACTIVE']
        );
        $officers = collect(range(1, 6))->mapWithKeys(function (int $number) use ($demoPassword) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            $email = $number === 1 ? 'petugas@rujuk.test' : "petugas{$suffix}@rujuk.test";

            return [$number => User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => "Petugas Demo {$suffix}",
                    'phone' => "08000000{$suffix}",
                    'password' => $demoPassword,
                    'role' => 'OFFICER',
                    'account_status' => 'ACTIVE',
                ]
            )];
        });

        $publicUsers = collect(range(1, 5))->mapWithKeys(function (int $number) use ($demoPassword) {
            $suffix = str_pad((string) $number, 2, '0', STR_PAD_LEFT);

            return [$number => User::updateOrCreate(
                ['email' => "masyarakat{$suffix}@example.test"],
                [
                    'name' => "Masyarakat Demo {$suffix}",
                    'phone' => "08990000{$suffix}",
                    'password' => $demoPassword,
                    'role' => 'PUBLIC',
                    'account_status' => 'ACTIVE',
                ]
            )];
        });

        $districtNames = ['Tambaksari', 'Mulyorejo', 'Pabean Cantian', 'Sukolilo', 'Pakal', 'Wonokromo'];
        foreach ($districtNames as $name) {
            District::firstOrCreate(['name' => $name]);
        }

        $serviceNames = ['Instalasi Gawat Darurat', 'Rawat Jalan', 'Rawat Inap', 'Laboratorium', 'Radiologi', 'Hemodialisis'];
        foreach ($serviceNames as $name) {
            Service::firstOrCreate(['name' => $name]);
        }

        $rawatJalanId = Service::where('name', 'Rawat Jalan')->value('id');

        $facilityNames = [
            'Ambulans', 'ICU', 'Laboratorium', 'Radiologi', 'Farmasi 24 Jam',
            'Hemodialisis', 'CT Scan', 'Ruang Operasi', 'Poliklinik Spesialis',
        ];

        foreach ($facilityNames as $name) {
            Facility::firstOrCreate(['name' => $name]);
        }

        $hospitals = [
            [
                'name' => 'RSUD Dr. Soetomo', 'code' => 'RS-SBY-001', 'class' => 'A',
                'ownership' => 'Pemerintah', 'phone' => '(031) 5501078', 'emergency_phone' => '112',
                'address' => 'Jl. Mayjen Prof. Dr. Moestopo No. 6-8', 'city' => 'Surabaya',
                'latitude' => -7.2675000, 'longitude' => 112.7580000,
                'description' => 'Rumah sakit rujukan dengan layanan spesialistik dan subspesialistik untuk wilayah Jawa Timur.',
                'is_emergency' => true, 'facilities' => $facilityNames,
                'district' => 'Tambaksari', 'services' => $serviceNames,
            ],
            [
                'name' => 'RS Universitas Airlangga', 'code' => 'RS-SBY-002', 'class' => 'B',
                'ownership' => 'Pemerintah', 'phone' => '(031) 5916290', 'emergency_phone' => '(031) 5916290',
                'address' => 'Kampus C Universitas Airlangga, Jl. Mulyorejo', 'city' => 'Surabaya',
                'latitude' => -7.2708000, 'longitude' => 112.7847000,
                'description' => 'Rumah sakit pendidikan dengan pelayanan umum, spesialistik, serta dukungan kegiatan akademik dan penelitian.',
                'is_emergency' => true,
                'facilities' => ['Ambulans', 'ICU', 'Laboratorium', 'Radiologi', 'Farmasi 24 Jam', 'CT Scan', 'Ruang Operasi', 'Poliklinik Spesialis'],
                'district' => 'Mulyorejo', 'services' => ['Instalasi Gawat Darurat', 'Rawat Jalan', 'Rawat Inap', 'Laboratorium', 'Radiologi'],
            ],
            [
                'name' => 'RS PHC Surabaya', 'code' => 'RS-SBY-003', 'class' => 'B',
                'ownership' => 'BUMN', 'phone' => '(031) 3294801', 'emergency_phone' => '(031) 3294801',
                'address' => 'Jl. Prapat Kurung Selatan No. 1', 'city' => 'Surabaya',
                'latitude' => -7.2195000, 'longitude' => 112.7325000,
                'description' => 'Rumah sakit milik BUMN dengan layanan kegawatdaruratan, rawat inap, dan poliklinik spesialis.',
                'is_emergency' => true,
                'facilities' => ['Ambulans', 'ICU', 'Laboratorium', 'Radiologi', 'Farmasi 24 Jam', 'Hemodialisis', 'Ruang Operasi', 'Poliklinik Spesialis'],
                'district' => 'Pabean Cantian', 'services' => $serviceNames,
            ],
            [
                'name' => 'RS Premier Surabaya', 'code' => 'RS-SBY-004', 'class' => 'B',
                'ownership' => 'Swasta', 'phone' => '(031) 5993211', 'emergency_phone' => '(031) 5993211',
                'address' => 'Jl. Nginden Intan Barat Blok B', 'city' => 'Surabaya',
                'latitude' => -7.3049000, 'longitude' => 112.7663000,
                'description' => 'Rumah sakit swasta dengan layanan terpadu dan sejumlah pusat layanan spesialis.',
                'is_emergency' => true,
                'facilities' => ['Ambulans', 'ICU', 'Laboratorium', 'Farmasi 24 Jam', 'CT Scan', 'Ruang Operasi', 'Poliklinik Spesialis'],
                'district' => 'Sukolilo', 'services' => ['Instalasi Gawat Darurat', 'Rawat Jalan', 'Rawat Inap', 'Laboratorium', 'Radiologi'],
            ],
            [
                'name' => 'RSUD Bhakti Dharma Husada', 'code' => 'RS-SBY-005', 'class' => 'B',
                'ownership' => 'Pemerintah', 'phone' => '(031) 7409135', 'emergency_phone' => '(031) 7409135',
                'address' => 'Jl. Raya Kendung No. 115-117', 'city' => 'Surabaya',
                'latitude' => -7.2565000, 'longitude' => 112.6676000,
                'description' => 'Rumah sakit umum daerah yang melayani masyarakat Surabaya bagian barat dan sekitarnya.',
                'is_emergency' => true,
                'facilities' => ['Ambulans', 'ICU', 'Laboratorium', 'Radiologi', 'Farmasi 24 Jam', 'Ruang Operasi', 'Poliklinik Spesialis'],
                'district' => 'Pakal', 'services' => ['Instalasi Gawat Darurat', 'Rawat Jalan', 'Rawat Inap', 'Laboratorium', 'Radiologi'],
            ],
            [
                'name' => 'RS Islam Surabaya A. Yani', 'code' => 'RS-SBY-006', 'class' => 'B',
                'ownership' => 'Swasta', 'phone' => '(031) 8284505', 'emergency_phone' => '(031) 8284505',
                'address' => 'Jl. Achmad Yani No. 2-4', 'city' => 'Surabaya',
                'latitude' => -7.3097000, 'longitude' => 112.7341000,
                'description' => 'Rumah sakit umum swasta dengan pelayanan rawat jalan, rawat inap, penunjang, dan kegawatdaruratan.',
                'is_emergency' => true,
                'facilities' => ['Ambulans', 'ICU', 'Laboratorium', 'Radiologi', 'Farmasi 24 Jam', 'Hemodialisis', 'Ruang Operasi', 'Poliklinik Spesialis'],
                'district' => 'Wonokromo', 'services' => $serviceNames,
            ],
        ];

        foreach ($hospitals as $hospitalIndex => $data) {
            $facilityIds = Facility::whereIn('name', $data['facilities'])->pluck('id');
            $serviceIds = Service::whereIn('name', $data['services'])->pluck('id');
            $data['district_id'] = District::where('name', $data['district'])->value('id');
            unset($data['facilities'], $data['services'], $data['district']);
            $hospital = Hospital::updateOrCreate(['code' => $data['code']], $data);
            $hospital->facilities()->sync($facilityIds);

            foreach ($serviceIds as $serviceId) {
                $hospitalService = HospitalService::updateOrCreate(
                    ['hospital_id' => $hospital->id, 'service_id' => $serviceId],
                    ['initial_service_duration' => 20, 'availability_status' => 'ACTIVE']
                );

                ServiceDesk::firstOrCreate(
                    ['hospital_service_id' => $hospitalService->id, 'name' => 'Loket 1'],
                    ['desk_status' => 'ACTIVE']
                );

                if ($serviceId === $rawatJalanId) {
                    $secondDesk = ServiceDesk::firstOrCreate(
                        ['hospital_service_id' => $hospitalService->id, 'name' => 'Loket 2'],
                        ['desk_status' => 'ACTIVE']
                    );

                    foreach (['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY'] as $day) {
                        ServiceSchedule::firstOrCreate(
                            ['hospital_service_id' => $hospitalService->id, 'day' => $day],
                            ['opens_at' => '08:00', 'closes_at' => '14:00', 'quota' => 50, 'schedule_status' => 'ACTIVE']
                        );
                    }

                    $holiday = now()->setDate(now()->year, 12, 25)->startOfDay();
                    SpecialServiceSchedule::updateOrCreate(
                        ['hospital_service_id' => $hospitalService->id, 'date' => $holiday],
                        ['status' => 'CLOSED', 'reason' => 'Hari libur nasional — data dummy']
                    );

                    $session = QueueSession::firstOrCreate(
                        ['hospital_service_id' => $hospitalService->id, 'session_date' => today()],
                        [
                            'opened_by_user_id' => $admin->id,
                            'started_at' => now(),
                            'session_status' => 'OPEN',
                        ]
                    );

                    $firstDesk = ServiceDesk::where('hospital_service_id', $hospitalService->id)
                        ->where('name', 'Loket 1')->firstOrFail();
                    $this->seedQueues($session, $firstDesk, $secondDesk);
                }
            }

            $officerNumber = $hospitalIndex + 1;
            StaffAssignment::updateOrCreate(
                ['user_id' => $officers[$officerNumber]->id, 'hospital_id' => $hospital->id],
                [
                    'employee_code' => 'PGW-'.str_pad((string) $officerNumber, 3, '0', STR_PAD_LEFT),
                    'starts_on' => today()->subMonths(3),
                    'ends_on' => null,
                    'assignment_status' => 'ACTIVE',
                ]
            );
        }

        foreach ($publicUsers as $number => $publicUser) {
            SavedLocation::updateOrCreate(
                ['user_id' => $publicUser->id, 'label' => 'Rumah Demo'],
                [
                    'address' => "Alamat sintetis masyarakat {$number}, Surabaya",
                    'latitude' => -7.25 - ($number * 0.005),
                    'longitude' => 112.73 + ($number * 0.005),
                    'is_primary' => true,
                ]
            );
        }
    }

    private function seedQueues(QueueSession $session, ServiceDesk $firstDesk, ServiceDesk $secondDesk): void
    {
        $startedAt = today()->setTime(8, 0);
        $definitions = [
            1 => ['queue_status' => 'COMPLETED', 'service_desk_id' => $firstDesk->id, 'called_at' => $startedAt->copy()->addMinutes(2), 'service_started_at' => $startedAt->copy()->addMinutes(3), 'service_ended_at' => $startedAt->copy()->addMinutes(18)],
            2 => ['queue_status' => 'COMPLETED', 'service_desk_id' => $secondDesk->id, 'called_at' => $startedAt->copy()->addMinutes(5), 'service_started_at' => $startedAt->copy()->addMinutes(6), 'service_ended_at' => $startedAt->copy()->addMinutes(22)],
            3 => ['queue_status' => 'SERVING', 'service_desk_id' => $firstDesk->id, 'called_at' => $startedAt->copy()->addMinutes(20), 'service_started_at' => $startedAt->copy()->addMinutes(22)],
            4 => ['queue_status' => 'CALLED', 'service_desk_id' => $secondDesk->id, 'called_at' => $startedAt->copy()->addMinutes(24)],
            5 => ['queue_status' => 'WAITING'],
            6 => ['queue_status' => 'WAITING'],
            7 => ['queue_status' => 'WAITING'],
            8 => ['queue_status' => 'CANCELLED', 'service_ended_at' => $startedAt->copy()->addMinutes(15)],
        ];

        foreach ($definitions as $number => $attributes) {
            Queue::firstOrCreate(
                ['queue_session_id' => $session->id, 'queue_number' => $number],
                ['public_token' => (string) Str::uuid(), ...$attributes]
            );
        }

        $counts = $session->queues()->selectRaw('queue_status, count(*) as total')
            ->groupBy('queue_status')->pluck('total', 'queue_status');
        $activeDesks = $session->hospitalService->desks()->where('desk_status', 'ACTIVE')->count();
        QueueSnapshot::updateOrCreate(
            ['hospital_service_id' => $session->hospital_service_id, 'captured_at' => today()->setTime(9, 0)],
            [
                'waiting_count' => $counts->get('WAITING', 0),
                'serving_count' => $counts->get('SERVING', 0) + $counts->get('CALLED', 0),
                'completed_count' => $counts->get('COMPLETED', 0),
                'active_desk_count' => $activeDesks,
                'estimated_wait_minutes' => (int) ceil(($counts->get('WAITING', 0) * 20) / max($activeDesks, 1)),
            ]
        );
    }
}
