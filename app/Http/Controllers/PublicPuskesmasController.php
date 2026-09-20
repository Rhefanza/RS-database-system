<?php

namespace App\Http\Controllers;

use App\Models\Puskesmas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicPuskesmasController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('q')), 0, 100);
        $mapItems = $this->liveQueueData(true);

        return view('home', [
            'mapItems' => $mapItems,
            'search' => $search,
            'totalQueues' => $mapItems->sum('total'),
            'activeQueues' => $mapItems->sum('active'),
        ]);
    }

    public function recommendations(): View
    {
        $recommendations = $this->liveQueueData(true);

        return view('recommendations', compact('recommendations'));
    }

    public function liveQueues(): JsonResponse
    {
        $items = $this->liveQueueData();

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
            'puskesmasServices' => fn ($query) => $query->where('status', 'AKTIF')
                ->with([
                    'service',
                    'doctors' => fn ($doctors) => $doctors->where('status', 'AKTIF')->orderBy('nama_dokter'),
                    'schedules' => fn ($schedule) => $schedule->where('status', 'AKTIF')->with([
                        'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', '>=', today())->where('status_antrean', '!=', 'CANCELLED'),
                    ]),
                ]),
        ]);

        return view('puskesmas-show', compact('puskesmas'));
    }

    private function liveQueueData(bool $coordinatesOnly = false)
    {
        $query = Puskesmas::query()
            ->where('status', 'AKTIF')
            ->with([
                'district',
                'puskesmasServices' => fn ($relation) => $relation->where('status', 'AKTIF')->with([
                    'service',
                    'doctors' => fn ($doctors) => $doctors->where('status', 'AKTIF')->orderBy('nama_dokter'),
                    'schedules' => fn ($schedule) => $schedule->where('status', 'AKTIF')->with([
                        'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', today()),
                    ]),
                ]),
            ])
            ->orderBy('nama_puskesmas');

        if ($coordinatesOnly) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        return $query->get()->map(function (Puskesmas $puskesmas): array {
            $queues = $puskesmas->puskesmasServices
                ->flatMap(fn ($relation) => $relation->schedules)
                ->flatMap(fn ($schedule) => $schedule->queues);

            return [
                'id' => $puskesmas->puskesmas_id,
                'name' => $puskesmas->nama_puskesmas,
                'district' => $puskesmas->district?->nama_kecamatan ?? 'Kecamatan belum diatur',
                'address' => $puskesmas->alamat,
                'phone' => $puskesmas->nomor_telepon,
                'latitude' => (float) $puskesmas->latitude,
                'longitude' => (float) $puskesmas->longitude,
                'total' => $queues->where('status_antrean', '!=', 'CANCELLED')->count(),
                'active' => $queues->whereIn('status_antrean', ['WAITING', 'CALLED', 'SERVING'])->count(),
                'completed' => $queues->where('status_antrean', 'COMPLETED')->count(),
                'services' => $puskesmas->puskesmasServices->pluck('service.nama_layanan')->filter()->unique()->values(),
                'service_details' => $puskesmas->puskesmasServices->map(fn ($relation): array => [
                    'name' => $relation->service?->nama_layanan,
                    'description' => $relation->service?->deskripsi,
                    'doctors' => $relation->doctors->map(fn ($doctor): array => [
                        'name' => $doctor->nama_dokter,
                        'specialization' => $doctor->spesialisasi,
                    ])->values(),
                    'schedules' => $relation->schedules->map(fn ($schedule): array => [
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
