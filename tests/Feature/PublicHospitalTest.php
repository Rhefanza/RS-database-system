<?php

namespace Tests\Feature;

use App\Models\Facility;
use App\Models\Hospital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicHospitalTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_hospitals_and_facilities(): void
    {
        $hospital = Hospital::factory()->create(['name' => 'RS Uji Surabaya']);
        $facility = Facility::create(['name' => 'Laboratorium']);
        $hospital->facilities()->attach($facility);

        $this->get('/')
            ->assertOk()
            ->assertSee('RS Uji Surabaya')
            ->assertSee('Laboratorium');
    }

    public function test_search_and_filters_only_show_matching_hospitals(): void
    {
        Hospital::factory()->create([
            'name' => 'RS Pemerintah Utama',
            'class' => 'A',
            'ownership' => 'Pemerintah',
            'city' => 'Surabaya',
        ]);
        Hospital::factory()->create([
            'name' => 'RS Swasta Barat',
            'class' => 'C',
            'ownership' => 'Swasta',
            'city' => 'Gresik',
        ]);

        $this->get('/?q=Surabaya&class=A&ownership=Pemerintah')
            ->assertOk()
            ->assertSee('RS Pemerintah Utama')
            ->assertDontSee('RS Swasta Barat');
    }

    public function test_detail_page_is_public_and_missing_hospital_returns_404(): void
    {
        $hospital = Hospital::factory()->create(['name' => 'RS Detail']);

        $this->get(route('hospitals.show', $hospital))->assertOk()->assertSee('RS Detail');
        $this->get('/rumah-sakit/99999')->assertNotFound();
    }

    public function test_invalid_public_filter_is_safely_ignored(): void
    {
        Hospital::factory()->create(['name' => 'RS Tetap Tampil']);

        $this->get('/?class=Z')->assertOk()->assertSee('RS Tetap Tampil');
    }
}
