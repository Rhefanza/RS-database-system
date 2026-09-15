<?php

namespace Tests\Feature;

use App\Models\District;
use App\Models\Hospital;
use App\Models\HospitalService;
use App\Models\Service;
use App\Models\ServiceDesk;
use App\Models\ServiceSchedule;
use App\Models\SpecialServiceSchedule;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'ADMIN', 'account_status' => 'ACTIVE']);
        $this->actingAs($this->admin);
    }

    public function test_admin_can_open_master_data_panel(): void
    {
        $this->get(route('admin.master.index'))
            ->assertOk()
            ->assertSee('Pengisian data')
            ->assertSee('Kecamatan')
            ->assertSee('Petugas & masyarakat', false);
    }

    public function test_admin_can_crud_districts_and_services(): void
    {
        $this->post(route('admin.master.districts.store'), ['name' => 'Rungkut'])->assertRedirect();
        $district = District::firstOrFail();
        $this->put(route('admin.master.districts.update', $district), ['name' => 'Gunung Anyar'])->assertRedirect();
        $this->assertDatabaseHas('districts', ['id' => $district->id, 'name' => 'Gunung Anyar']);
        $this->delete(route('admin.master.districts.destroy', $district))->assertRedirect();
        $this->assertDatabaseMissing('districts', ['id' => $district->id]);

        $this->post(route('admin.master.services.store'), ['name' => 'Poli Gigi', 'description' => 'Pelayanan gigi'])->assertRedirect();
        $service = Service::firstOrFail();
        $this->put(route('admin.master.services.update', $service), ['name' => 'Poli Gigi Anak', 'description' => 'Pelayanan anak'])->assertRedirect();
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => 'Poli Gigi Anak']);
        $this->delete(route('admin.master.services.destroy', $service))->assertRedirect();
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_admin_can_crud_hospital_service_schedules_and_desks(): void
    {
        $hospital = Hospital::factory()->create();
        $service = Service::create(['name' => 'Poli Umum']);

        $this->post(route('admin.master.hospital-services.store'), [
            'hospital_id' => $hospital->id,
            'service_id' => $service->id,
            'initial_service_duration' => 15,
            'availability_status' => 'ACTIVE',
        ])->assertRedirect();
        $hospitalService = HospitalService::firstOrFail();

        $this->put(route('admin.master.hospital-services.update', $hospitalService), [
            'hospital_id' => $hospital->id,
            'service_id' => $service->id,
            'initial_service_duration' => 20,
            'availability_status' => 'INACTIVE',
        ])->assertRedirect();
        $this->assertDatabaseHas('hospital_services', ['id' => $hospitalService->id, 'initial_service_duration' => 20, 'availability_status' => 'INACTIVE']);

        $this->post(route('admin.master.schedules.store'), [
            'hospital_service_id' => $hospitalService->id,
            'day' => 'MONDAY', 'opens_at' => '08:00', 'closes_at' => '12:00',
            'quota' => 40, 'schedule_status' => 'ACTIVE',
        ])->assertRedirect();
        $schedule = ServiceSchedule::firstOrFail();
        $this->put(route('admin.master.schedules.update', $schedule), [
            'hospital_service_id' => $hospitalService->id,
            'day' => 'TUESDAY', 'opens_at' => '09:00', 'closes_at' => '13:00',
            'quota' => 30, 'schedule_status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertDatabaseHas('service_schedules', ['id' => $schedule->id, 'day' => 'TUESDAY', 'quota' => 30]);

        $this->post(route('admin.master.special-schedules.store'), [
            'hospital_service_id' => $hospitalService->id,
            'date' => '2026-12-25', 'status' => 'CLOSED', 'reason' => 'Hari libur',
        ])->assertRedirect();
        $special = SpecialServiceSchedule::firstOrFail();
        $this->put(route('admin.master.special-schedules.update', $special), [
            'hospital_service_id' => $hospitalService->id,
            'date' => '2026-12-25', 'status' => 'CHANGED', 'reason' => 'Jam terbatas',
            'special_opens_at' => '09:00', 'special_closes_at' => '11:00',
        ])->assertRedirect();
        $this->assertDatabaseHas('special_service_schedules', ['id' => $special->id, 'status' => 'CHANGED']);

        $this->post(route('admin.master.desks.store'), [
            'hospital_service_id' => $hospitalService->id, 'name' => 'Loket A', 'desk_status' => 'ACTIVE',
        ])->assertRedirect();
        $desk = ServiceDesk::firstOrFail();
        $this->put(route('admin.master.desks.update', $desk), [
            'hospital_service_id' => $hospitalService->id, 'name' => 'Loket B', 'desk_status' => 'INACTIVE',
        ])->assertRedirect();
        $this->assertDatabaseHas('service_desks', ['id' => $desk->id, 'name' => 'Loket B', 'desk_status' => 'INACTIVE']);

        $this->delete(route('admin.master.schedules.destroy', $schedule))->assertRedirect();
        $this->delete(route('admin.master.special-schedules.destroy', $special))->assertRedirect();
        $this->delete(route('admin.master.desks.destroy', $desk))->assertRedirect();
        $this->delete(route('admin.master.hospital-services.destroy', $hospitalService))->assertRedirect();
        $this->assertDatabaseMissing('hospital_services', ['id' => $hospitalService->id]);
    }

    public function test_admin_can_crud_officer_accounts_and_assignments(): void
    {
        $hospital = Hospital::factory()->create();
        $this->post(route('admin.master.officers.store'), [
            'name' => 'Petugas Satu', 'email' => 'petugas@example.test', 'phone' => '08123456789',
            'password' => 'password123', 'role' => 'OFFICER', 'account_status' => 'ACTIVE',
            'hospital_id' => $hospital->id, 'employee_code' => 'PGW-9',
        ])->assertRedirect();
        $officer = User::where('email', 'petugas@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $officer->password));
        $assignment = StaffAssignment::firstOrFail();
        $this->put(route('admin.master.assignments.update', $assignment), [
            'user_id' => $officer->id, 'hospital_id' => $hospital->id, 'employee_code' => 'PGW-10',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31', 'assignment_status' => 'INACTIVE',
        ])->assertRedirect();
        $this->assertDatabaseHas('staff_assignments', ['id' => $assignment->id, 'employee_code' => 'PGW-10', 'assignment_status' => 'INACTIVE']);

        $this->delete(route('admin.master.assignments.destroy', $assignment))->assertRedirect();
        $this->delete(route('admin.master.users.destroy', $officer))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $officer->id]);

        $this->post(route('admin.master.users.store'), [
            'name' => 'Masyarakat Uji', 'email' => 'masyarakat@example.test', 'phone' => '080000001',
            'password' => 'password123', 'role' => 'PUBLIC', 'account_status' => 'ACTIVE',
        ])->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'masyarakat@example.test', 'role' => 'PUBLIC']);
    }

    public function test_relational_records_cannot_be_deleted_while_still_in_use(): void
    {
        $district = District::create(['name' => 'Sukolilo']);
        Hospital::factory()->create(['district_id' => $district->id]);
        $service = Service::create(['name' => 'Laboratorium']);
        HospitalService::create([
            'hospital_id' => Hospital::first()->id, 'service_id' => $service->id,
            'initial_service_duration' => 10, 'availability_status' => 'ACTIVE',
        ]);

        $this->delete(route('admin.master.districts.destroy', $district))->assertSessionHasErrors('data');
        $this->delete(route('admin.master.services.destroy', $service))->assertSessionHasErrors('data');
        $this->assertDatabaseHas('districts', ['id' => $district->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id]);
    }
}
