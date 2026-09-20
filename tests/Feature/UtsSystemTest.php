<?php

namespace Tests\Feature;

use App\Models\Citizen;
use App\Models\District;
use App\Models\Doctor;
use App\Models\Puskesmas;
use App\Models\PuskesmasService;
use App\Models\Queue;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UtsSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_uses_nine_business_tables(): void
    {
        foreach (['kecamatan', 'masyarakat', 'akun', 'puskesmas', 'layanan', 'puskesmas_layanan', 'jadwal', 'dokter', 'antrean'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        foreach (['hospitals', 'districts', 'facilities', 'queue_sessions', 'queue_snapshots', 'service_desks', 'saved_locations'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_citizen_can_activate_registered_nik_and_login(): void
    {
        $citizen = $this->citizen();
        $this->post(route('activation.store'), [
            'nik' => $citizen->nik,
            'email' => 'warga@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('login'));

        $account = User::where('nik', $citizen->nik)->firstOrFail();
        $this->assertSame('MASYARAKAT', $account->role);
        $this->assertTrue(Hash::check('password123', $account->password_hash));
        $this->post(route('login.store'), ['email' => 'warga@example.test', 'password' => 'password123'])
            ->assertRedirect(route('home'));
    }

    public function test_activation_rejects_unknown_inactive_and_used_nik(): void
    {
        $inactive = $this->citizen(['nik' => '3578010101900098', 'status_data' => 'NONAKTIF']);
        $used = $this->citizen(['nik' => '3578010101900097']);
        User::factory()->create(['nik' => $used->nik, 'role' => 'MASYARAKAT']);

        foreach (['3578010101900099', $inactive->nik, $used->nik] as $nik) {
            $this->post(route('activation.store'), ['nik' => $nik, 'email' => $nik.'@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
                ->assertSessionHasErrors('nik');
        }
    }

    public function test_role_boundaries_and_login_destinations_work(): void
    {
        $puskesmas = Puskesmas::factory()->create();
        $admin = User::factory()->create();
        $officer = User::factory()->create(['role' => 'PETUGAS', 'puskesmas_id' => $puskesmas->puskesmas_id]);
        $citizen = $this->citizen();
        $public = User::factory()->create(['role' => 'MASYARAKAT', 'nik' => $citizen->nik]);

        $this->actingAs($admin)->get(route('admin.master.index'))->assertOk();
        $this->actingAs($admin)->get(route('officer.schedules.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('officer.queues.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('admin.master.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('officer.schedules.index'))->assertOk();
        $this->actingAs($officer)->get(route('officer.queues.index'))->assertOk();
        $this->actingAs($public)->get(route('officer.schedules.index'))->assertForbidden();
        $this->actingAs($public)->get(route('my-queues.index'))->assertOk();

        $this->actingAs($admin)->get(route('admin.master.index'))
            ->assertDontSee(route('puskesmas.map'), false)
            ->assertDontSee(route('officer.schedules.index'), false)
            ->assertDontSee('Cara kerja');
    }

    public function test_admin_can_crud_master_data_and_create_officer(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post(route('admin.districts.store'), ['nama_kecamatan' => 'Kecamatan Uji CRUD'])->assertRedirect();
        $district = District::where('nama_kecamatan', 'Kecamatan Uji CRUD')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.citizens.store'), [
            'nik' => '3578010101900020', 'nama_lengkap' => 'Warga Uji', 'nomor_telepon' => '0812', 'alamat' => 'Surabaya', 'status_data' => 'AKTIF',
        ])->assertRedirect();
        $this->post(route('admin.puskesmas.store'), ['kecamatan_id' => $district->kecamatan_id, 'nama_puskesmas' => 'Puskesmas Uji', 'alamat' => 'Jalan Uji', 'status' => 'AKTIF'])->assertRedirect();
        $this->post(route('admin.services.store'), ['nama_layanan' => 'Poli Uji', 'deskripsi' => 'Layanan uji', 'status' => 'AKTIF'])->assertRedirect();
        $puskesmas = Puskesmas::firstOrFail();
        $service = Service::firstOrFail();
        $this->post(route('admin.relations.store'), ['puskesmas_id' => $puskesmas->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'AKTIF'])->assertRedirect();
        $relation = PuskesmasService::firstOrFail();
        $this->post(route('admin.doctors.store'), ['puskesmas_layanan_id' => $relation->puskesmas_layanan_id, 'nama_dokter' => 'dr. Uji', 'spesialisasi' => 'Dokter Umum', 'status' => 'AKTIF'])->assertRedirect();
        $this->post(route('admin.officers.store'), ['nama_lengkap' => 'Petugas Uji', 'email' => 'petugas@example.test', 'password' => 'password123', 'puskesmas_id' => $puskesmas->puskesmas_id])->assertRedirect();

        $this->assertDatabaseHas('masyarakat', ['nik' => '3578010101900020']);
        $this->assertDatabaseHas('puskesmas_layanan', ['puskesmas_id' => $puskesmas->puskesmas_id, 'layanan_id' => $service->layanan_id]);
        $this->assertDatabaseHas('akun', ['email' => 'petugas@example.test', 'role' => 'PETUGAS']);

        $citizen = Citizen::findOrFail('3578010101900020');
        $doctor = Doctor::firstOrFail();
        $officer = User::where('email', 'petugas@example.test')->firstOrFail();
        $this->put(route('admin.citizens.update', $citizen), ['nik' => $citizen->nik, 'nama_lengkap' => 'Warga Diperbarui', 'status_data' => 'NONAKTIF'])->assertRedirect();
        $this->put(route('admin.puskesmas.update', $puskesmas), ['kecamatan_id' => $district->kecamatan_id, 'nama_puskesmas' => 'Puskesmas Diperbarui', 'alamat' => 'Jalan Baru', 'status' => 'AKTIF'])->assertRedirect();
        $this->put(route('admin.services.update', $service), ['nama_layanan' => 'Poli Diperbarui', 'status' => 'AKTIF'])->assertRedirect();
        $this->put(route('admin.relations.update', $relation), ['puskesmas_id' => $puskesmas->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'NONAKTIF'])->assertRedirect();
        $this->put(route('admin.doctors.update', $doctor), ['puskesmas_layanan_id' => $relation->puskesmas_layanan_id, 'nama_dokter' => 'dr. Uji Baru', 'spesialisasi' => 'Dokter Umum', 'status' => 'AKTIF'])->assertRedirect();
        $this->put(route('admin.accounts.update', $officer), ['nama_lengkap' => 'Petugas Baru', 'email' => $officer->email, 'puskesmas_id' => $puskesmas->puskesmas_id, 'status_akun' => 'NONAKTIF'])->assertRedirect();
        $this->assertDatabaseHas('masyarakat', ['nik' => $citizen->nik, 'nama_lengkap' => 'Warga Diperbarui']);

        $this->delete(route('admin.accounts.destroy', $officer))->assertRedirect();
        $this->delete(route('admin.doctors.destroy', $doctor))->assertRedirect();
        $this->delete(route('admin.relations.destroy', $relation))->assertRedirect();
        $this->delete(route('admin.services.destroy', $service))->assertRedirect();
        $this->delete(route('admin.puskesmas.destroy', $puskesmas))->assertRedirect();
        $this->delete(route('admin.districts.destroy', $district))->assertRedirect();
        $this->delete(route('admin.citizens.destroy', $citizen))->assertRedirect();
        $this->assertDatabaseMissing('masyarakat', ['nik' => $citizen->nik]);
    }

    public function test_officer_can_crud_only_own_puskesmas_schedules(): void
    {
        [$ownRelation, $otherRelation] = $this->twoRelations();
        $officer = User::factory()->create(['role' => 'PETUGAS', 'puskesmas_id' => $ownRelation->puskesmas_id]);
        $this->actingAs($officer)->post(route('officer.schedules.store'), [
            'puskesmas_layanan_id' => $ownRelation->puskesmas_layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 40, 'status' => 'AKTIF',
        ])->assertRedirect();
        $schedule = Schedule::firstOrFail();
        $this->put(route('officer.schedules.update', $schedule), [
            'puskesmas_layanan_id' => $ownRelation->puskesmas_layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '13:00', 'kapasitas' => 60, 'status' => 'AKTIF',
        ])->assertRedirect();
        $this->assertDatabaseHas('jadwal', ['jadwal_id' => $schedule->jadwal_id, 'kapasitas' => 60]);
        $this->post(route('officer.schedules.store'), [
            'puskesmas_layanan_id' => $otherRelation->puskesmas_layanan_id, 'hari' => 'SELASA', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 40, 'status' => 'AKTIF',
        ])->assertForbidden();
        $this->delete(route('officer.schedules.destroy', $schedule))->assertRedirect();
        $this->assertDatabaseMissing('jadwal', ['jadwal_id' => $schedule->jadwal_id]);
    }

    public function test_citizen_queue_respects_capacity_and_can_be_cancelled_and_deleted(): void
    {
        [$relation] = $this->twoRelations();
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $schedule = Schedule::create(['puskesmas_layanan_id' => $relation->puskesmas_layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 1, 'status' => 'AKTIF']);
        $firstCitizen = $this->citizen();
        $first = User::factory()->create(['role' => 'MASYARAKAT', 'nik' => $firstCitizen->nik]);
        $secondCitizen = $this->citizen(['nik' => '3578010101900096']);
        $second = User::factory()->create(['role' => 'MASYARAKAT', 'nik' => $secondCitizen->nik]);

        $this->actingAs($first)->post(route('my-queues.store', $schedule), ['tanggal_daftar' => today()->toDateString()])->assertRedirect(route('my-queues.index'));
        $this->post(route('my-queues.store', $schedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('my-queues.index'))
            ->assertSessionHas('error', 'Anda sudah memiliki antrean untuk jadwal dan tanggal ini.');
        $this->assertDatabaseCount('antrean', 1);

        $this->actingAs($second)->from(route('puskesmas.show', $relation->puskesmas))->post(route('my-queues.store', $schedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('puskesmas.show', $relation->puskesmas))
            ->assertSessionHas('error', 'Kapasitas antrean sudah penuh.');
        $queue = Queue::firstOrFail();
        $this->actingAs($first)->patch(route('my-queues.cancel', $queue))->assertRedirect();
        $this->delete(route('my-queues.destroy', $queue))->assertRedirect();
        $this->assertDatabaseCount('antrean', 0);
    }

    public function test_public_detail_renders_schedules_and_remaining_capacity(): void
    {
        [$relation] = $this->twoRelations();
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        Schedule::create(['puskesmas_layanan_id' => $relation->puskesmas_layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);

        $this->get(route('puskesmas.show', $relation->puskesmas))
            ->assertOk()
            ->assertSee('Poli Umum')
            ->assertSee('Sisa 50/50');
    }

    public function test_home_shows_real_map_container_and_today_queue_totals(): void
    {
        [$relation] = $this->twoRelations();
        $relation->puskesmas->update(['latitude' => -7.2567, 'longitude' => 112.7505]);
        $outlier = Puskesmas::factory()->create([
            'nama_puskesmas' => 'Puskesmas Koordinat Salah',
            'latitude' => 0.0000001,
            'longitude' => 0.0000004,
        ]);
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $schedule = Schedule::create(['puskesmas_layanan_id' => $relation->puskesmas_layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $citizen = $this->citizen();
        Queue::create(['nik' => $citizen->nik, 'jadwal_id' => $schedule->jadwal_id, 'nomor_antrean' => 1, 'tanggal_daftar' => today(), 'status_antrean' => 'WAITING']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($relation->puskesmas->nama_puskesmas)
            ->assertDontSee($outlier->nama_puskesmas)
            ->assertSee('Peta layanan hari ini')
            ->assertSee('data-leaflet-map', false);

        $this->get(route('puskesmas.map'))->assertRedirect(route('home').'#peta-surabaya');
    }

    public function test_dummy_queue_simulator_and_live_api_work(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->artisan('queue:simulate', ['--once' => true])->assertSuccessful();

        $this->getJson(route('api.live-queues'))
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonStructure([
                'generated_at',
                'refresh_after_seconds',
                'totals' => ['puskesmas', 'queues', 'active', 'completed'],
                'puskesmas' => [['id', 'name', 'total', 'active', 'completed']],
            ]);
    }

    public function test_dummy_queue_simulator_preserves_citywide_demo_coverage(): void
    {
        $this->seed(DatabaseSeeder::class);
        $before = Queue::count();
        $this->assertGreaterThan(31, $before);
        $this->artisan('queue:simulate', ['--once' => true])->assertSuccessful();
        $this->assertGreaterThanOrEqual($before, Queue::count());
    }

    public function test_new_puskesmas_automatically_receives_realtime_dummy_queue(): void
    {
        $service = Service::create(['nama_layanan' => 'Poli Baru', 'status' => 'AKTIF']);
        $puskesmas = Puskesmas::factory()->create([
            'nama_puskesmas' => 'Puskesmas Baru Realtime',
            'latitude' => -7.28,
            'longitude' => 112.76,
        ]);
        $relation = PuskesmasService::create([
            'puskesmas_id' => $puskesmas->puskesmas_id,
            'layanan_id' => $service->layanan_id,
            'status' => 'AKTIF',
        ]);
        $tomorrow = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->addDay()->format('l')];
        Schedule::create([
            'puskesmas_layanan_id' => $relation->puskesmas_layanan_id,
            'hari' => $tomorrow,
            'jam_buka' => '08:00',
            'jam_tutup' => '12:00',
            'kapasitas' => 10,
            'status' => 'AKTIF',
        ]);
        $citizen = $this->citizen(['nama_lengkap' => 'Masyarakat Dummy Baru']);

        $this->artisan('queue:simulate', ['--once' => true])
            ->expectsOutputToContain('Puskesmas Baru Realtime')
            ->assertSuccessful();

        $todaySchedule = Schedule::where('puskesmas_layanan_id', $relation->puskesmas_layanan_id)
            ->where('hari', ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')])
            ->firstOrFail();
        $this->assertDatabaseHas('antrean', [
            'nik' => $citizen->nik,
            'jadwal_id' => $todaySchedule->jadwal_id,
            'status_antrean' => 'WAITING',
        ]);
        $this->getJson(route('api.live-queues'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Puskesmas Baru Realtime', 'total' => 1]);
    }

    public function test_officer_can_crud_only_own_puskesmas_queues(): void
    {
        [$ownRelation, $otherRelation] = $this->twoRelations();
        $ownSchedule = Schedule::create(['puskesmas_layanan_id' => $ownRelation->puskesmas_layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $otherSchedule = Schedule::create(['puskesmas_layanan_id' => $otherRelation->puskesmas_layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $citizen = $this->citizen();
        $officer = User::factory()->create(['role' => 'PETUGAS', 'puskesmas_id' => $ownRelation->puskesmas_id]);

        $this->actingAs($officer)->post(route('officer.queues.store'), ['nik' => $citizen->nik, 'jadwal_id' => $ownSchedule->jadwal_id, 'tanggal_daftar' => today()->toDateString(), 'status_antrean' => 'WAITING'])->assertRedirect();
        $queue = Queue::firstOrFail();
        $this->patch(route('officer.queues.update', $queue), ['status_antrean' => 'COMPLETED'])->assertRedirect();
        $this->assertDatabaseHas('antrean', ['antrean_id' => $queue->antrean_id, 'status_antrean' => 'COMPLETED']);
        $foreignQueue = Queue::create(['nik' => $citizen->nik, 'jadwal_id' => $otherSchedule->jadwal_id, 'nomor_antrean' => 1, 'tanggal_daftar' => today(), 'status_antrean' => 'WAITING']);
        $this->patch(route('officer.queues.update', $foreignQueue), ['status_antrean' => 'COMPLETED'])->assertForbidden();
        $this->delete(route('officer.queues.destroy', $queue))->assertRedirect();
        $this->assertDatabaseMissing('antrean', ['antrean_id' => $queue->antrean_id]);
    }

    public function test_seeder_provides_complete_synthetic_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('kecamatan', 31);
        $this->assertDatabaseCount('masyarakat', 80);
        $this->assertDatabaseCount('puskesmas', 31);
        $this->assertDatabaseCount('layanan', 6);
        $this->assertDatabaseCount('puskesmas_layanan', 93);
        $this->assertDatabaseCount('dokter', 93);
        $this->assertDatabaseCount('jadwal', 93);
        $this->assertDatabaseCount('antrean', 182);
        $this->assertDatabaseHas('akun', ['email' => 'admin@puskesmas.test', 'role' => 'ADMIN']);
        $this->assertDatabaseHas('masyarakat', ['nik' => '3578010101900004']);
        $this->assertSame(31, Puskesmas::whereNotNull('latitude')->whereNotNull('longitude')->distinct('kecamatan_id')->count('kecamatan_id'));
    }

    private function citizen(array $attributes = []): Citizen
    {
        return Citizen::create([...['nik' => '3578010101900095', 'nama_lengkap' => 'Warga Test', 'status_data' => 'AKTIF'], ...$attributes]);
    }

    private function twoRelations(): array
    {
        $service = Service::create(['nama_layanan' => 'Poli Umum', 'status' => 'AKTIF']);
        $first = Puskesmas::factory()->create();
        $second = Puskesmas::factory()->create();

        return [
            PuskesmasService::create(['puskesmas_id' => $first->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'AKTIF']),
            PuskesmasService::create(['puskesmas_id' => $second->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'AKTIF']),
        ];
    }
}
