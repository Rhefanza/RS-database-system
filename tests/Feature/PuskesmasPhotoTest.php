<?php

namespace Tests\Feature;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PuskesmasPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_38_facility_ids_resolve_to_supplied_photos(): void
    {
        $photos = [];
        for ($id = 1; $id <= 38; $id++) {
            $facility = new Puskesmas;
            $facility->puskesmas_id = $id;
            $url = $facility->photo_url;
            $this->assertFileExists(public_path(parse_url($url, PHP_URL_PATH)));
            $this->assertSame($url, $facility->photo_url);
            $photos[] = $url;
        }
        $this->assertCount(20, array_unique($photos));
    }

    public function test_public_and_internal_pages_use_the_same_facility_photo(): void
    {
        $facility = Puskesmas::factory()->create(['latitude' => -7.2575, 'longitude' => 112.7521]);
        $this->get(route('puskesmas.show', $facility))->assertOk()->assertSee($facility->photo_url, false);
        $this->get(route('recommendations.index'))->assertOk()->assertSee($facility->photo_url, false);
        $officer = User::factory()->create(['role' => 'PETUGAS', 'puskesmas_id' => $facility->getKey()]);
        $this->actingAs($officer)->get(route('officer.queues.index'))->assertOk()->assertSee($facility->photo_url, false);
        $this->actingAs($officer)->get(route('officer.schedules.index'))->assertOk()->assertSee($facility->photo_url, false);
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $this->actingAs($admin)->get(route('admin.master.index'))->assertOk()->assertSee($facility->photo_url, false);
    }
}
