<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Puskesmas;
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

    public function test_database_uses_six_business_tables(): void
    {
        foreach (['kecamatan', 'akun', 'puskesmas', 'layanan', 'jadwal', 'antrean'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        foreach (['cache', 'cache_locks', 'sessions'] as $frameworkTable) {
            $this->assertTrue(Schema::hasTable($frameworkTable));
        }
        foreach (['masyarakat', 'dokter', 'puskesmas_layanan', 'hospitals', 'districts', 'facilities', 'queue_sessions', 'queue_snapshots', 'service_desks', 'saved_locations'] as $table) {
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
        $this->account(['nik' => $used->nik, 'role' => 'MASYARAKAT']);

        foreach (['3578010101900099', $inactive->nik, $used->nik] as $nik) {
            $this->post(route('activation.store'), ['nik' => $nik, 'email' => $nik.'@example.test', 'password' => 'password123', 'password_confirmation' => 'password123'])
                ->assertSessionHasErrors('nik');
        }
    }

    public function test_role_boundaries_and_login_destinations_work(): void
    {
        $puskesmas = Puskesmas::factory()->create();
        $admin = $this->account();
        $officer = $this->account(['role' => 'PETUGAS', 'puskesmas_id' => $puskesmas->puskesmas_id]);
        $citizen = $this->citizen();
        $public = $this->account(['role' => 'MASYARAKAT', 'nik' => $citizen->nik]);

        $this->actingAs($admin)->get(route('admin.master.index'))->assertOk();
        $this->actingAs($admin)->get(route('officer.schedules.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('officer.queues.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('admin.master.index'))->assertForbidden();
        $this->actingAs($officer)->get(route('officer.schedules.index'))->assertOk();
        $this->actingAs($officer)->get(route('officer.queues.index'))->assertOk();
        $this->get(route('home'))->assertRedirect(route('officer.queues.index'));
        $this->get(route('recommendations.index'))->assertRedirect(route('officer.queues.index'));
        $this->get(route('officer.schedules.index'))
            ->assertSee('Kelola antrean')
            ->assertSee('Jadwal layanan')
            ->assertDontSee('Cara kerja')
            ->assertDontSee('Lihat rekomendasi');
        $this->post(route('logout'));
        $this->withSession(['url.intended' => route('recommendations.index')])
            ->post(route('login.store'), ['email' => $officer->email, 'password' => 'password'])
            ->assertRedirect(route('officer.queues.index'));
        $this->actingAs($public)->get(route('officer.schedules.index'))->assertForbidden();
        $this->actingAs($public)->get(route('my-queues.index'))->assertOk();

        $this->actingAs($admin)->get(route('admin.master.index'))
            ->assertDontSee(route('puskesmas.map'), false)
            ->assertDontSee(route('officer.schedules.index'), false)
            ->assertDontSee('Cara kerja');
    }

    public function test_admin_can_crud_master_data_and_create_officer(): void
    {
        $admin = $this->account();
        $this->actingAs($admin)->post(route('admin.districts.store'), ['nama_kecamatan' => 'Kecamatan Uji CRUD'])->assertRedirect();
        $district = District::where('nama_kecamatan', 'Kecamatan Uji CRUD')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.citizens.store'), [
            'nik' => '3578010101900020', 'nama_lengkap' => 'Warga Uji', 'nomor_telepon' => '0812', 'alamat' => 'Surabaya', 'status_data' => 'AKTIF',
        ])->assertRedirect();
        $this->post(route('admin.puskesmas.store'), ['kecamatan_id' => $district->kecamatan_id, 'nama_puskesmas' => 'Puskesmas Uji', 'alamat' => 'Jalan Uji', 'status' => 'AKTIF'])->assertRedirect();
        $this->post(route('admin.services.store'), ['nama_layanan' => 'Poli Uji', 'deskripsi' => 'Layanan uji', 'status' => 'AKTIF'])->assertRedirect();
        $puskesmas = Puskesmas::firstOrFail();
        $service = Service::firstOrFail();
        $this->post(route('admin.officers.store'), ['nama_lengkap' => 'Petugas Uji', 'password' => 'password123', 'puskesmas_id' => $puskesmas->puskesmas_id])->assertRedirect();

        $this->assertDatabaseHas('akun', ['nik' => '3578010101900020']);
        $this->assertDatabaseHas('akun', ['email' => 'petugas_uji@test', 'role' => 'PETUGAS', 'puskesmas_id' => $puskesmas->puskesmas_id]);

        $citizen = User::where('nik', '3578010101900020')->firstOrFail();
        $officer = User::where('email', 'petugas_uji@test')->firstOrFail();
        $this->put(route('admin.citizens.update', $citizen), ['nik' => $citizen->nik, 'nama_lengkap' => 'Warga Diperbarui', 'status_data' => 'NONAKTIF'])->assertRedirect();
        $this->put(route('admin.puskesmas.update', $puskesmas), ['kecamatan_id' => $district->kecamatan_id, 'nama_puskesmas' => 'Puskesmas Diperbarui', 'alamat' => 'Jalan Baru', 'status' => 'AKTIF'])->assertRedirect();
        $this->put(route('admin.services.update', $service), ['nama_layanan' => 'Poli Diperbarui', 'status' => 'AKTIF'])->assertRedirect();
        $this->put(route('admin.accounts.update', $officer), ['nama_lengkap' => 'Petugas Baru', 'puskesmas_id' => $puskesmas->puskesmas_id, 'status_akun' => 'NONAKTIF'])->assertRedirect();
        $this->assertDatabaseHas('akun', ['nik' => $citizen->nik, 'nama_lengkap' => 'Warga Diperbarui']);

        $this->delete(route('admin.accounts.destroy', $officer))->assertRedirect();
        $this->delete(route('admin.services.destroy', $service))->assertRedirect();
        $this->delete(route('admin.puskesmas.destroy', $puskesmas))->assertRedirect();
        $this->delete(route('admin.districts.destroy', $district))->assertRedirect();
        $this->delete(route('admin.citizens.destroy', $citizen))->assertRedirect();
        $this->assertDatabaseMissing('akun', ['nik' => $citizen->nik]);
    }

    public function test_admin_account_section_only_lists_and_manages_officers(): void
    {
        $admin = $this->account(['email' => 'admin-section@example.test']);
        $puskesmas = Puskesmas::factory()->create();
        $citizen = $this->citizen();
        $citizenAccount = $this->account([
            'nik' => $citizen->nik,
            'email' => 'citizen-section@example.test',
            'role' => 'MASYARAKAT',
        ]);
        $officer = $this->account([
            'puskesmas_id' => $puskesmas->puskesmas_id,
            'email' => 'officer-section@example.test',
            'role' => 'PETUGAS',
        ]);

        $this->actingAs($admin)->get(route('admin.master.index').'#akun')
            ->assertOk()
            ->assertSee('Akun petugas')
            ->assertSee($officer->email)
            ->assertDontSee($citizenAccount->email)
            ->assertDontSee($admin->email);

        $payload = [
            'nama_lengkap' => $citizenAccount->nama_lengkap,
            'email' => $citizenAccount->email,
            'status_akun' => 'AKTIF',
        ];
        $this->put(route('admin.accounts.update', $citizenAccount), $payload)->assertNotFound();
        $this->delete(route('admin.accounts.destroy', $citizenAccount))->assertNotFound();
        $this->delete(route('admin.accounts.destroy', $admin))->assertNotFound();
        $this->assertDatabaseHas('akun', ['akun_id' => $admin->akun_id, 'role' => 'ADMIN']);
    }

    public function test_officer_must_have_an_active_assigned_puskesmas(): void
    {
        $inactivePuskesmas = Puskesmas::factory()->create(['status' => 'NONAKTIF']);
        $officer = $this->account([
            'role' => 'PETUGAS',
            'puskesmas_id' => $inactivePuskesmas->puskesmas_id,
            'email' => 'petugas_nonaktif@test',
        ]);

        $this->post(route('login.store'), ['email' => $officer->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $officer->update(['puskesmas_id' => null]);
        $this->actingAs($officer)->get(route('officer.schedules.index'))->assertForbidden();
    }

    public function test_officer_can_crud_only_own_puskesmas_schedules(): void
    {
        [$ownRelation, $otherRelation] = $this->twoRelations();
        $officer = $this->account(['role' => 'PETUGAS', 'puskesmas_id' => $ownRelation->puskesmas_id]);
        $this->actingAs($officer)->post(route('officer.schedules.store'), [
            'puskesmas_id' => $ownRelation->puskesmas_id, 'layanan_id' => $ownRelation->layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 40, 'status' => 'AKTIF',
        ])->assertRedirect();
        $schedule = Schedule::firstOrFail();
        $this->put(route('officer.schedules.update', $schedule), [
            'puskesmas_id' => $ownRelation->puskesmas_id, 'layanan_id' => $ownRelation->layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '13:00', 'kapasitas' => 60, 'status' => 'AKTIF',
        ])->assertRedirect();
        $this->assertDatabaseHas('jadwal', ['jadwal_id' => $schedule->jadwal_id, 'kapasitas' => 60]);
        $this->post(route('officer.schedules.store'), [
            'puskesmas_id' => $otherRelation->puskesmas_id, 'layanan_id' => $otherRelation->layanan_id, 'hari' => 'SELASA', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 40, 'status' => 'AKTIF',
        ])->assertForbidden();
        $this->delete(route('officer.schedules.destroy', $schedule))->assertRedirect();
        $this->assertDatabaseMissing('jadwal', ['jadwal_id' => $schedule->jadwal_id]);
    }

    public function test_schedule_rejects_duplicate_day_and_capacity_below_active_queues(): void
    {
        [$relation] = $this->twoRelations();
        $officer = $this->account(['role' => 'PETUGAS', 'puskesmas_id' => $relation->puskesmas_id]);
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $schedule = Schedule::create(['puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 2, 'status' => 'AKTIF']);
        $first = $this->citizen();
        $second = $this->citizen(['nik' => '3578010101900096']);
        Queue::create(['akun_id' => $first->akun_id, 'jadwal_id' => $schedule->jadwal_id, 'nomor_antrean' => 1, 'tanggal_daftar' => today(), 'status_antrean' => 'WAITING']);
        Queue::create(['akun_id' => $second->akun_id, 'jadwal_id' => $schedule->jadwal_id, 'nomor_antrean' => 2, 'tanggal_daftar' => today(), 'status_antrean' => 'SERVING']);

        $this->actingAs($officer)->post(route('officer.schedules.store'), [
            'puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '13:00', 'jam_tutup' => '15:00', 'kapasitas' => 10, 'status' => 'AKTIF',
        ])->assertSessionHasErrors('hari');
        $this->put(route('officer.schedules.update', $schedule), [
            'puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 1, 'status' => 'AKTIF',
        ])->assertSessionHasErrors('kapasitas');

        $this->assertDatabaseHas('jadwal', ['jadwal_id' => $schedule->jadwal_id, 'kapasitas' => 2]);
    }

    public function test_citizen_queue_respects_capacity_and_can_be_cancelled_and_deleted(): void
    {
        [$relation] = $this->twoRelations();
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $schedule = Schedule::create(['puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 1, 'status' => 'AKTIF']);
        $firstCitizen = $this->citizen();
        $first = $this->account(['role' => 'MASYARAKAT', 'nik' => $firstCitizen->nik]);
        $secondCitizen = $this->citizen(['nik' => '3578010101900096']);
        $second = $this->account(['role' => 'MASYARAKAT', 'nik' => $secondCitizen->nik]);

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

        $this->actingAs($second)->post(route('my-queues.store', $schedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('my-queues.index'));
        $secondQueue = Queue::where('akun_id', $secondCitizen->akun_id)->firstOrFail();
        $secondQueue->update(['status_antrean' => 'COMPLETED']);

        $this->actingAs($first)->post(route('my-queues.store', $schedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('my-queues.index'));
        $this->assertSame(1, Queue::active()->count());
        $this->assertDatabaseCount('antrean', 3);
        $this->get(route('my-queues.index'))
            ->assertOk()
            ->assertSee('Antrean aktif saya')
            ->assertDontSee('CANCELLED')
            ->assertDontSee('COMPLETED');
    }

    public function test_citizen_cannot_take_two_overlapping_active_queues(): void
    {
        [$firstRelation, $secondRelation] = $this->twoRelations();
        $thirdService = Service::create(['nama_layanan' => 'Laboratorium', 'status' => 'AKTIF']);
        $thirdRelation = (object) [
            'puskesmas_id' => $secondRelation->puskesmas_id,
            'layanan_id' => $thirdService->layanan_id,
            'status' => 'AKTIF',
        ];
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        $firstSchedule = Schedule::create(['puskesmas_id' => $firstRelation->puskesmas_id, 'layanan_id' => $firstRelation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 10, 'status' => 'AKTIF']);
        $overlappingSchedule = Schedule::create(['puskesmas_id' => $secondRelation->puskesmas_id, 'layanan_id' => $secondRelation->layanan_id, 'hari' => $day, 'jam_buka' => '10:00', 'jam_tutup' => '14:00', 'kapasitas' => 10, 'status' => 'AKTIF']);
        $laterSchedule = Schedule::create(['puskesmas_id' => $thirdRelation->puskesmas_id, 'layanan_id' => $thirdRelation->layanan_id, 'hari' => $day, 'jam_buka' => '12:00', 'jam_tutup' => '15:00', 'kapasitas' => 10, 'status' => 'AKTIF']);
        $citizen = $this->citizen();
        $user = $this->account(['role' => 'MASYARAKAT', 'nik' => $citizen->nik]);

        $this->actingAs($user)->post(route('my-queues.store', $firstSchedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('my-queues.index'));
        $this->from(route('puskesmas.show', $secondRelation->puskesmas))->post(route('my-queues.store', $overlappingSchedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('puskesmas.show', $secondRelation->puskesmas))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Jadwal bertabrakan'));
        $this->post(route('my-queues.store', $laterSchedule), ['tanggal_daftar' => today()->toDateString()])
            ->assertRedirect(route('my-queues.index'));

        $this->assertSame(2, Queue::active()->where('akun_id', $citizen->akun_id)->count());
    }

    public function test_public_detail_renders_schedules_and_remaining_capacity(): void
    {
        [$relation] = $this->twoRelations();
        $day = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
        Schedule::create(['puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);

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
        $schedule = Schedule::create(['puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id, 'hari' => $day, 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $citizen = $this->citizen();
        $completedCitizen = $this->citizen(['nik' => '3578010101900096']);
        Queue::create(['akun_id' => $citizen->akun_id, 'jadwal_id' => $schedule->jadwal_id, 'nomor_antrean' => 1, 'tanggal_daftar' => today(), 'status_antrean' => 'WAITING']);
        Queue::create(['akun_id' => $completedCitizen->akun_id, 'jadwal_id' => $schedule->jadwal_id, 'nomor_antrean' => 2, 'tanggal_daftar' => today(), 'status_antrean' => 'COMPLETED']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($relation->puskesmas->nama_puskesmas)
            ->assertDontSee($outlier->nama_puskesmas)
            ->assertSee('Peta layanan hari ini')
            ->assertSee('Total antrean')
            ->assertSee('data-live-summary-total', false)
            ->assertSee('data-journey-showcase', false)
            ->assertSee('data-step-card', false)
            ->assertSee('data-scroll-blur', false)
            ->assertSee('data-leaflet-map', false);

        $this->getJson(route('api.live-queues'))
            ->assertOk()
            ->assertJsonPath('totals.queues', 2)
            ->assertJsonPath('totals.active', 1);

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
        $relation = (object) [
            'puskesmas_id' => $puskesmas->puskesmas_id,
            'layanan_id' => $service->layanan_id,
            'status' => 'AKTIF',
        ];
        $tomorrow = ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->addDay()->format('l')];
        Schedule::create([
            'puskesmas_id' => $relation->puskesmas_id, 'layanan_id' => $relation->layanan_id,
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

        $todaySchedule = Schedule::where('puskesmas_id', $relation->puskesmas_id)->where('layanan_id', $relation->layanan_id)
            ->where('hari', ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')])
            ->firstOrFail();
        $this->assertDatabaseHas('antrean', [
            'akun_id' => $citizen->akun_id,
            'jadwal_id' => $todaySchedule->jadwal_id,
            'status_antrean' => 'WAITING',
        ]);
        $this->getJson(route('api.live-queues'))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Puskesmas Baru Realtime', 'total' => 1]);
    }

    public function test_officer_can_crud_only_own_puskesmas_queues(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 09:00:00'));
        [$ownRelation, $otherRelation] = $this->twoRelations();
        $ownSchedule = Schedule::create(['puskesmas_id' => $ownRelation->puskesmas_id, 'layanan_id' => $ownRelation->layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $otherSchedule = Schedule::create(['puskesmas_id' => $otherRelation->puskesmas_id, 'layanan_id' => $otherRelation->layanan_id, 'hari' => 'SENIN', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $citizen = $this->citizen();
        $officer = $this->account(['role' => 'PETUGAS', 'puskesmas_id' => $ownRelation->puskesmas_id]);

        $this->actingAs($officer)->post(route('officer.queues.store'), ['nik' => $citizen->nik, 'jadwal_id' => $ownSchedule->jadwal_id, 'tanggal_daftar' => today()->toDateString(), 'status_antrean' => 'WAITING'])->assertRedirect();
        $queue = Queue::firstOrFail();
        $this->patch(route('officer.queues.update', $queue), ['status_antrean' => 'COMPLETED'])->assertStatus(409);
        $this->patch(route('officer.queues.update', $queue), ['status_antrean' => 'CALLED'])->assertRedirect();
        $this->patch(route('officer.queues.update', $queue), ['status_antrean' => 'SERVING'])->assertRedirect();
        $this->patch(route('officer.queues.update', $queue), ['status_antrean' => 'COMPLETED'])->assertRedirect();
        $this->assertDatabaseHas('antrean', ['antrean_id' => $queue->antrean_id, 'status_antrean' => 'COMPLETED']);
        $foreignQueue = Queue::create(['akun_id' => $citizen->akun_id, 'jadwal_id' => $otherSchedule->jadwal_id, 'nomor_antrean' => 1, 'tanggal_daftar' => today(), 'status_antrean' => 'WAITING']);
        $this->patch(route('officer.queues.update', $foreignQueue), ['status_antrean' => 'CALLED'])->assertForbidden();
        $this->delete(route('officer.queues.destroy', $queue))->assertRedirect();
        $this->assertDatabaseMissing('antrean', ['antrean_id' => $queue->antrean_id]);
    }

    public function test_seeder_provides_complete_synthetic_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('kecamatan', 31);
        $this->assertSame(80, User::citizens()->count());
        $this->assertDatabaseCount('puskesmas', 31);
        $this->assertDatabaseCount('layanan', 6);
        $this->assertDatabaseCount('jadwal', 93);
        $this->assertDatabaseCount('antrean', 109);
        $this->assertDatabaseHas('akun', ['email' => 'admin@puskesmas.test', 'role' => 'ADMIN']);
        $this->assertDatabaseHas('akun', ['email' => 'petugas_asemrowo@test', 'role' => 'PETUGAS']);
        $this->assertDatabaseHas('akun', ['nik' => '3578010101900004']);
        $this->assertSame(31, Puskesmas::whereNotNull('latitude')->whereNotNull('longitude')->distinct('kecamatan_id')->count('kecamatan_id'));
    }

    private function citizen(array $attributes = []): User
    {
        return User::create([...['role' => 'MASYARAKAT', 'status_akun' => 'NONAKTIF', 'nik' => '3578010101900095', 'nama_lengkap' => 'Warga Test', 'status_data' => 'AKTIF'], ...$attributes]);
    }

    private function account(array $attributes = []): User
    {
        $data = User::factory()->raw($attributes);
        return isset($attributes['nik'])
            ? User::updateOrCreate(['nik' => $attributes['nik']], $data)
            : User::create($data);
    }

    private function twoRelations(): array
    {
        $service = Service::create(['nama_layanan' => 'Poli Umum', 'status' => 'AKTIF']);
        $first = Puskesmas::factory()->create();
        $second = Puskesmas::factory()->create();

        return [
            (object) ['puskesmas' => $first, 'puskesmas_id' => $first->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'AKTIF'],
            (object) ['puskesmas' => $second, 'puskesmas_id' => $second->puskesmas_id, 'layanan_id' => $service->layanan_id, 'status' => 'AKTIF'],
        ];
    }
}
