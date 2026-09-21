<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ACTIVE_STATUSES = ['WAITING', 'CALLED', 'SERVING'];

    public function up(): void
    {
        $accepted = [];
        $activeQueues = DB::table('antrean')
            ->join('jadwal', 'antrean.jadwal_id', '=', 'jadwal.jadwal_id')
            ->whereIn('antrean.status_antrean', self::ACTIVE_STATUSES)
            ->orderBy('antrean.tanggal_daftar')
            ->orderBy('antrean.nik')
            ->orderBy('antrean.antrean_id')
            ->get([
                'antrean.antrean_id',
                'antrean.nik',
                'antrean.tanggal_daftar',
                'jadwal.jam_buka',
                'jadwal.jam_tutup',
            ]);

        foreach ($activeQueues as $queue) {
            $key = $queue->nik.'|'.$queue->tanggal_daftar;
            $conflicts = collect($accepted[$key] ?? [])->contains(
                fn (array $interval): bool => $interval['open'] < $queue->jam_tutup
                    && $interval['close'] > $queue->jam_buka
            );

            if ($conflicts) {
                DB::table('antrean')->where('antrean_id', $queue->antrean_id)->update([
                    'status_antrean' => 'CANCELLED',
                    'updated_at' => now(),
                ]);

                continue;
            }

            $accepted[$key][] = ['open' => $queue->jam_buka, 'close' => $queue->jam_tutup];
        }

        $overCapacity = DB::table('antrean')
            ->join('jadwal', 'antrean.jadwal_id', '=', 'jadwal.jadwal_id')
            ->whereIn('antrean.status_antrean', self::ACTIVE_STATUSES)
            ->groupBy('antrean.jadwal_id', 'antrean.tanggal_daftar', 'jadwal.kapasitas')
            ->havingRaw('COUNT(*) > jadwal.kapasitas')
            ->get(['antrean.jadwal_id', 'antrean.tanggal_daftar', 'jadwal.kapasitas']);

        foreach ($overCapacity as $group) {
            $excessIds = DB::table('antrean')
                ->where('jadwal_id', $group->jadwal_id)
                ->whereDate('tanggal_daftar', $group->tanggal_daftar)
                ->whereIn('status_antrean', self::ACTIVE_STATUSES)
                ->orderBy('nomor_antrean')
                ->orderBy('antrean_id')
                ->skip((int) $group->kapasitas)
                ->pluck('antrean_id');

            if ($excessIds->isNotEmpty()) {
                DB::table('antrean')->whereIn('antrean_id', $excessIds)->update([
                    'status_antrean' => 'CANCELLED',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Perbaikan data bersifat satu arah karena status lama yang tidak valid
        // tidak boleh diaktifkan kembali secara otomatis.
    }
};
