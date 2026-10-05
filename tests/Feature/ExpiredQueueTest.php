<?php

namespace Tests\Feature;

use App\Models\Puskesmas;
use App\Models\Queue;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiredQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiration_uses_service_time_not_booking_time_and_preserves_history(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 11:59:59', 'Asia/Jakarta'));
        $clinic = Puskesmas::factory()->create();
        $service = Service::create(['nama_layanan' => 'Poli Uji', 'status' => 'AKTIF']);
        $schedule = Schedule::create(['puskesmas_id' => $clinic->puskesmas_id, 'layanan_id' => $service->layanan_id,
            'hari' => 'MINGGU', 'jam_buka' => '08:00', 'jam_tutup' => '12:00', 'kapasitas' => 50, 'status' => 'AKTIF']);
        $user = User::factory()->create(['role' => 'MASYARAKAT', 'nik' => '3578010101900088']);
        $make = fn ($date, $number, $status) => Queue::create(['akun_id' => $user->akun_id,
            'jadwal_id' => $schedule->jadwal_id, 'tanggal_daftar' => $date,
            'nomor_antrean' => $number, 'status_antrean' => $status]);
        $old = [];
        foreach (Queue::ACTIVE_STATUSES as $index => $status) {
            $old[] = $make('2026-09-22', $index + 1, $status);
        }
        $boundary = $make('2026-10-04', 1, 'WAITING');
        $today = $make('2026-10-05', 1, 'WAITING');
        $future = $make('2026-10-11', 1, 'WAITING');
        $future->update(['created_at' => '2026-09-01 08:00:00']);
        $completed = $make('2026-09-22', 4, 'COMPLETED');
        $cancelled = $make('2026-09-22', 5, 'CANCELLED');
        $this->artisan('queue:prune-expired')->expectsOutput('3 antrean aktif kedaluwarsa dihapus.')->assertSuccessful();
        foreach ($old as $queue) {
            $this->assertDatabaseMissing('antrean', ['antrean_id' => $queue->antrean_id]);
        }
        foreach ([$boundary, $today, $future, $completed, $cancelled] as $queue) {
            $this->assertDatabaseHas('antrean', ['antrean_id' => $queue->antrean_id]);
        }
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 12:00:00', 'Asia/Jakarta'));
        $this->actingAs($user)->get(route('my-queues.index'))->assertOk();
        $this->assertDatabaseMissing('antrean', ['antrean_id' => $boundary->antrean_id]);
        $this->assertDatabaseCount('antrean', 4);
        $this->artisan('queue:prune-expired')->expectsOutput('0 antrean aktif kedaluwarsa dihapus.')->assertSuccessful();
    }
}
