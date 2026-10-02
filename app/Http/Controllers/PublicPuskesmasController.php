<?php

namespace App\Http\Controllers;

use App\Models\Puskesmas;
use App\Models\Queue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPuskesmasController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()?->role === 'PETUGAS') {
            return redirect()->route('officer.queues.index');
        }

        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $mapItems = $this->liveQueueData(true);

        return view('home', [
            'mapItems' => $mapItems,
            'search' => $search,
            'totalQueues' => $mapItems->sum('total'),
            'activeQueues' => $mapItems->sum('active'),
        ]);
    }

    public function recommendations(Request $request): View|RedirectResponse
    {
        if ($request->user()?->role === 'PETUGAS') {
            return redirect()->route('officer.queues.index');
        }

        $recommendations = $this->liveQueueData(true);

        return view('recommendations', compact('recommendations'));
    }

    public function liveQueues(): JsonResponse
    {
        $items = $this->liveQueueData(true);

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'refresh_after_seconds' => 5,
            'totals' => [
                'puskesmas' => $items->count(),
                'queues' => $items->sum('total'),
                'active' => $items->sum('active'),
                'completed' => $items->sum('completed'),
            ],
            'puskesmas' => $items->map(fn (array $item): array => [
                'id' => $item['id'],
                'name' => $item['name'],
                'district' => $item['district'],
                'latitude' => $item['latitude'],
                'longitude' => $item['longitude'],
                'total' => $item['total'],
                'active' => $item['active'],
                'completed' => $item['completed'],
            ])->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function show(Puskesmas $puskesmas): View
    {
        abort_unless($puskesmas->status === 'AKTIF', 404);
        $puskesmas->load([
            'district',
            'schedules' => fn ($query) => $query->where('status', 'AKTIF')
                ->whereHas('service', fn ($service) => $service->where('status', 'AKTIF'))
                ->with(['service', 'queues' => fn ($queue) => $queue->active()->whereDate('tanggal_daftar', '>=', today())]),
        ]);
        return view('puskesmas-show', compact('puskesmas'));
    }

    private function liveQueueData(bool $coordinatesOnly = false)
    {
        $query = Puskesmas::where('status', 'AKTIF')->with([
            'district',
            'schedules' => fn ($query) => $query->where('status', 'AKTIF')
                ->whereHas('service', fn ($service) => $service->where('status', 'AKTIF'))
                ->with(['service', 'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', today())]),
        ])->orderBy('nama_puskesmas');

        if ($coordinatesOnly) {
            $query->whereBetween('latitude', [-7.5, -7.0])
                ->whereBetween('longitude', [112.5, 113.0]);
        }

        return $query->get()->map(function (Puskesmas $puskesmas): array {
            $queues = $puskesmas->schedules
                ->flatMap(fn ($schedule) => $schedule->queues);
            $activeQueues = $queues->whereIn('status_antrean', Queue::ACTIVE_STATUSES);

            return [
                'id' => $puskesmas->puskesmas_id,
                'name' => $puskesmas->nama_puskesmas,
                'district' => $puskesmas->district?->nama_kecamatan ?? 'Kecamatan belum diatur',
                'address' => $puskesmas->alamat,
                'phone' => $puskesmas->nomor_telepon,
                'latitude' => (float) $puskesmas->latitude,
                'longitude' => (float) $puskesmas->longitude,
                'total' => $queues->count(),
                'active' => $activeQueues->count(),
                'completed' => $queues->where('status_antrean', 'COMPLETED')->count(),
                'services' => $puskesmas->schedules->pluck('service.nama_layanan')->filter()->unique()->values(),
                'service_details' => $puskesmas->schedules->groupBy('layanan_id')->map(fn ($schedules): array => [
                    'name' => $schedules->first()->service->nama_layanan,
                    'description' => $schedules->first()->service->deskripsi,
                    'doctors' => $schedules->filter(fn ($schedule) => filled($schedule->nama_dokter))
                        ->unique(fn ($schedule) => $schedule->nama_dokter.'|'.$schedule->spesialisasi)
                        ->map(fn ($schedule): array => ['name' => $schedule->nama_dokter, 'specialization' => $schedule->spesialisasi])->values(),
                    'schedules' => $schedules->map(fn ($schedule): array => [
                        'day' => ucfirst(strtolower($schedule->hari)),
                        'open' => substr($schedule->jam_buka, 0, 5),
                        'close' => substr($schedule->jam_tutup, 0, 5),
                        'capacity' => $schedule->kapasitas,
                    ])->values(),
                ])->values(),
                'url' => route('puskesmas.show', $puskesmas),
            ];
        });
    }
}
