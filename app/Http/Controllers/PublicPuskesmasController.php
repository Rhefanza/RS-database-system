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
        $items = Puskesmas::query()->where('status', 'AKTIF')
            ->with(['services' => fn ($query) => $query->where('layanan.status', 'AKTIF')])
            ->when($search, fn ($query) => $query->where(fn ($nested) => $nested
                ->where('nama_puskesmas', 'like', "%{$search}%")
                ->orWhere('alamat', 'like', "%{$search}%")
                ->orWhereHas('services', fn ($service) => $service->where('nama_layanan', 'like', "%{$search}%"))))
            ->orderBy('nama_puskesmas')->get();

        return view('home', compact('items', 'search'));
    }

    public function map(): View
    {
        $mapItems = $this->liveQueueData(true);

        if ($mapItems->isNotEmpty()) {
            $minLatitude = $mapItems->min('latitude');
            $maxLatitude = $mapItems->max('latitude');
            $minLongitude = $mapItems->min('longitude');
            $maxLongitude = $mapItems->max('longitude');

            $mapItems = $mapItems->map(function (array $item) use ($minLatitude, $maxLatitude, $minLongitude, $maxLongitude): array {
                $longitudeRange = max($maxLongitude - $minLongitude, 0.000001);
                $latitudeRange = max($maxLatitude - $minLatitude, 0.000001);
                $item['x'] = 15 + (($item['longitude'] - $minLongitude) / $longitudeRange * 70);
                $item['y'] = 15 + (($maxLatitude - $item['latitude']) / $latitudeRange * 70);

                return $item;
            });
        }

        return view('puskesmas-map', [
            'mapItems' => $mapItems,
            'totalQueues' => $mapItems->sum('total'),
            'activeQueues' => $mapItems->sum('active'),
        ]);
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
                'total' => $item['total'],
                'active' => $item['active'],
                'completed' => $item['completed'],
            ])->values(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function show(Puskesmas $puskesmas): View
    {
        abort_unless($puskesmas->status === 'AKTIF', 404);
        $puskesmas->load(['puskesmasServices' => fn ($query) => $query->where('status', 'AKTIF')
            ->with(['service', 'schedules' => fn ($schedule) => $schedule->where('status', 'AKTIF')->with([
                'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', '>=', today())->where('status_antrean', '!=', 'CANCELLED'),
            ])])]);

        return view('puskesmas-show', compact('puskesmas'));
    }

    private function liveQueueData(bool $coordinatesOnly = false)
    {
        $query = Puskesmas::query()
            ->where('status', 'AKTIF')
            ->with(['puskesmasServices' => fn ($relation) => $relation->where('status', 'AKTIF')->with([
                'service',
                'schedules' => fn ($schedule) => $schedule->where('status', 'AKTIF')->with([
                    'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', today()),
                ]),
            ])])
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
                'address' => $puskesmas->alamat,
                'phone' => $puskesmas->nomor_telepon,
                'latitude' => (float) $puskesmas->latitude,
                'longitude' => (float) $puskesmas->longitude,
                'total' => $queues->where('status_antrean', '!=', 'CANCELLED')->count(),
                'active' => $queues->whereIn('status_antrean', ['WAITING', 'CALLED', 'SERVING'])->count(),
                'completed' => $queues->where('status_antrean', 'COMPLETED')->count(),
                'services' => $puskesmas->puskesmasServices->pluck('service.nama_layanan')->filter()->unique()->values(),
                'url' => route('puskesmas.show', $puskesmas),
            ];
        });
    }
}
