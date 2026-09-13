<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HospitalCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_open_index_and_create_form(): void
    {
        $this->actingAs($this->admin)->get(route('admin.index'))->assertOk()->assertSee('Data rumah sakit');
        $this->actingAs($this->admin)->get(route('admin.hospitals.create'))->assertOk()->assertSee('Tambah rumah sakit');
    }

    public function test_admin_can_create_hospital_with_facilities(): void
    {
        $facility = Facility::create(['name' => 'ICU']);

        $response = $this->actingAs($this->admin)->post(route('admin.hospitals.store'), $this->validData([
            'facilities' => [$facility->id],
        ]));

        $hospital = Hospital::where('code', 'RS-TEST-01')->firstOrFail();
        $response->assertRedirect(route('admin.hospitals.edit', $hospital));
        $this->assertDatabaseHas('hospital_facilities', [
            'hospital_id' => $hospital->id,
            'facility_id' => $facility->id,
        ]);
    }

    public function test_admin_can_update_hospital_and_sync_facilities(): void
    {
        $hospital = Hospital::factory()->create();
        $oldFacility = Facility::create(['name' => 'Radiologi']);
        $newFacility = Facility::create(['name' => 'CT Scan']);
        $hospital->facilities()->attach($oldFacility);

        $this->actingAs($this->admin)->put(route('admin.hospitals.update', $hospital), $this->validData([
            'name' => 'RS Sesudah Diedit',
            'code' => $hospital->code,
            'facilities' => [$newFacility->id],
        ]))->assertRedirect();

        $this->assertDatabaseHas('hospitals', ['id' => $hospital->id, 'name' => 'RS Sesudah Diedit']);
        $this->assertDatabaseMissing('hospital_facilities', ['hospital_id' => $hospital->id, 'facility_id' => $oldFacility->id]);
        $this->assertDatabaseHas('hospital_facilities', ['hospital_id' => $hospital->id, 'facility_id' => $newFacility->id]);
    }

    public function test_admin_can_delete_hospital_and_pivot_rows_are_removed(): void
    {
        $hospital = Hospital::factory()->create();
        $facility = Facility::create(['name' => 'Ambulans']);
        $hospital->facilities()->attach($facility);

        $this->actingAs($this->admin)
            ->delete(route('admin.hospitals.destroy', $hospital))
            ->assertRedirect(route('admin.index'));

        $this->assertDatabaseMissing('hospitals', ['id' => $hospital->id]);
        $this->assertDatabaseMissing('hospital_facilities', ['hospital_id' => $hospital->id]);
    }

    public function test_validation_rejects_missing_duplicate_and_invalid_values(): void
    {
        Hospital::factory()->create(['code' => 'DUPLICATE']);

        $response = $this->actingAs($this->admin)->post(route('admin.hospitals.store'), $this->validData([
            'name' => '',
            'code' => 'DUPLICATE',
            'class' => 'Z',
            'latitude' => 100,
        ]));

        $response->assertSessionHasErrors([
            'name',
            'code' => 'kode rumah sakit sudah digunakan.',
            'class',
            'latitude',
        ]);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'RS Pengujian',
            'code' => 'RS-TEST-01',
            'class' => 'B',
            'ownership' => 'Pemerintah',
            'phone' => '0311234567',
            'emergency_phone' => '112',
            'address' => 'Jl. Pengujian No. 1',
            'city' => 'Surabaya',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'description' => 'Data rumah sakit untuk pengujian.',
            'is_emergency' => '1',
            'facilities' => [],
        ], $overrides);
    }
}
