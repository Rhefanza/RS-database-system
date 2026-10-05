<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Queue;
use App\Models\Schedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateQueues extends Command
{
    private const MAX_DUMMY_QUEUES = 300;

    private const RESET_TO_DUMMY_QUEUES = 150;

    protected $signature = 'queue:simulate {--once : Jalankan satu perubahan lalu berhenti} {--min=2 : Jeda minimum dalam detik} {--max=9 : Jeda maksimum dalam detik}';

    protected $description = 'Simulasikan perubahan antrean data dummy pada interval acak';

    public function handle(): int
    {
        $minimum = max(1, (int) $this->option('min'));
        $maximum = max($minimum, (int) $this->option('max'));
        $lock = null;

        if (! $this->option('once')) {
            $lockPath = storage_path('framework/queue-simulator.lock');
            $lock = fopen($lockPath, 'c');

            if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                $this->warn('Simulator antrean lain sudah berjalan.');

                return self::FAILURE;
            }
        }

        $this->info('Simulator antrean dummy aktif. Tekan Ctrl+C untuk berhenti.');

        try {
            do {
                $this->line('['.now()->format('H:i:s').'] '.$this->simulateTick());

                if ($this->option('once')) {
                    break;
                }

                $seconds = random_int($minimum, $maximum);
                $this->line("Perubahan berikutnya sekitar {$seconds} detik lagi.");
                sleep($seconds);
            } while (true);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }

        return self::SUCCESS;
    }

    private function simulateTick(): string
    {
        Queue::expired()->delete();
        return DB::transaction(function (): string {
            // Refresh on every tick so a puskesmas added while the long-running
            // simulator is active can immediately join the simulation.
            $this->ensureTodaySchedules();

            if ($message = $this->resetDummyQueuesAtLimit()) {
                return $message;
            }

            if ($message = $this->createRandomQueue(true)) {
                return $message;
            }

            $preferNewQueue = random_int(1, 100) <= 45;

            if ($preferNewQueue && ($message = $this->createRandomQueue())) {
                return $message;
            }

            if ($message = $this->advanceRandomQueue()) {
                return $message;
            }

            return $this->createRandomQueue() ?? 'Tidak ada data dummy yang dapat diubah saat ini.';
        });
    }

    private function resetDummyQueuesAtLimit(): ?string
    {
        $dummyQueues = Queue::query()
            ->whereDate('tanggal_daftar', today())
            ->whereHas('account', fn ($citizen) => $citizen->where('nama_lengkap', 'like', 'Masyarakat Dummy %'));

        if ((clone $dummyQueues)->count() < self::MAX_DUMMY_QUEUES) {
            return null;
        }

        $idsToKeep = (clone $dummyQueues)
            ->latest('antrean_id')
            ->limit(self::RESET_TO_DUMMY_QUEUES)
            ->pluck('antrean_id');

        $deleted = $dummyQueues->whereNotIn('antrean_id', $idsToKeep)->delete();

        return 'Batas '.self::MAX_DUMMY_QUEUES." tercapai: {$deleted} antrean dummy lama dihapus, ".self::RESET_TO_DUMMY_QUEUES.' antrean terbaru dipertahankan.';
    }

    private function advanceRandomQueue(): ?string
    {
        $queue = Queue::query()
            ->whereDate('tanggal_daftar', today())
            ->whereIn('status_antrean', ['WAITING', 'CALLED', 'SERVING'])
            ->whereHas('account', fn ($citizen) => $citizen->where('nama_lengkap', 'like', 'Masyarakat Dummy %'))
            ->lockForUpdate()
            ->inRandomOrder()
            ->first();

        if (! $queue) {
            return null;
        }

        $previous = $queue->status_antrean;
        $next = ['WAITING' => 'CALLED', 'CALLED' => 'SERVING', 'SERVING' => 'COMPLETED'][$previous];
        $queue->update(['status_antrean' => $next]);

        return "Antrean #{$queue->nomor_antrean} berubah {$previous} → {$next}.";
    }

    private function createRandomQueue(bool $prioritizeUnrepresentedPuskesmas = false): ?string
    {
        $schedules = Schedule::query()
            ->where('status', 'AKTIF')
            ->where('hari', $this->todayName())
            ->whereHas('service', fn ($service) => $service->where('status', 'AKTIF'))
            ->whereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('status', 'AKTIF'))
            ->with('puskesmas')
            ->inRandomOrder()
            ->get()
            ->filter(fn (Schedule $item) => $item->queues()->active()->whereDate('tanggal_daftar', today())->count() < $item->kapasitas);

        if ($prioritizeUnrepresentedPuskesmas) {
            $representedPuskesmasIds = Queue::query()
                ->whereDate('tanggal_daftar', today())
                ->whereHas('account', fn ($citizen) => $citizen->where('nama_lengkap', 'like', 'Masyarakat Dummy %'))
                ->with('schedule')
                ->get()
                ->pluck('schedule.puskesmas_id')
                ->filter()
                ->unique();

            $schedules = $schedules->reject(fn (Schedule $item) => $representedPuskesmasIds
                ->contains($item->puskesmas_id));
        }

        $schedule = $schedules->first();

        if (! $schedule) {
            return null;
        }

        $citizen = User::citizens()
            ->where('status_data', 'AKTIF')
            ->where('nama_lengkap', 'like', 'Masyarakat Dummy %')
            ->inRandomOrder()
            ->get()
            ->first(fn (User $candidate) => ! Queue::overlappingActiveFor($candidate->akun_id, $schedule, today()->toDateString()));

        if (! $citizen) {
            return null;
        }

        User::citizens()->whereKey($citizen->akun_id)->lockForUpdate()->firstOrFail();
        $lockedSchedule = Schedule::whereKey($schedule->jadwal_id)->lockForUpdate()->firstOrFail();

        if ($lockedSchedule->queues()->active()->whereDate('tanggal_daftar', today())->count() >= $lockedSchedule->kapasitas
            || Queue::overlappingActiveFor($citizen->akun_id, $lockedSchedule, today()->toDateString())) {
            return null;
        }

        $number = ((int) $lockedSchedule->queues()->whereDate('tanggal_daftar', today())->max('nomor_antrean')) + 1;
        $lockedSchedule->queues()->create([
            'akun_id' => $citizen->akun_id,
            'nomor_antrean' => $number,
            'tanggal_daftar' => today(),
            'status_antrean' => 'WAITING',
        ]);

        return "Antrean #{$number} masuk di {$schedule->puskesmas->nama_puskesmas}.";
    }

    private function ensureTodaySchedules(): void
    {
        $templates = Schedule::where('status', 'AKTIF')
            ->whereHas('puskesmas', fn ($query) => $query->where('status', 'AKTIF'))
            ->whereHas('service', fn ($query) => $query->where('status', 'AKTIF'))
            ->get()->unique(fn ($schedule) => $schedule->puskesmas_id.'|'.$schedule->layanan_id);
        foreach ($templates as $template) {
            Schedule::firstOrCreate([
                'puskesmas_id' => $template->puskesmas_id,
                'layanan_id' => $template->layanan_id,
                'hari' => $this->todayName(),
            ], [
                'nama_dokter' => $template->nama_dokter,
                'spesialisasi' => $template->spesialisasi,
                'jam_buka' => $template->jam_buka,
                'jam_tutup' => $template->jam_tutup,
                'kapasitas' => $template->kapasitas,
                'status' => 'AKTIF',
            ]);
        }
    }

    private function todayName(): string
    {
        return ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][today()->format('l')];
    }
}
