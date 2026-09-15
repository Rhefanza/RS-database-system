<?php

namespace App\Http\Controllers;

use App\Models\Puskesmas;
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

    public function show(Puskesmas $puskesmas): View
    {
        abort_unless($puskesmas->status === 'AKTIF', 404);
        $puskesmas->load(['puskesmasServices' => fn ($query) => $query->where('status', 'AKTIF')
            ->with(['service', 'schedules' => fn ($schedule) => $schedule->where('status', 'AKTIF')->with([
                'queues' => fn ($queue) => $queue->whereDate('tanggal_daftar', '>=', today())->where('status_antrean', '!=', 'CANCELLED'),
            ])])]);

        return view('puskesmas-show', compact('puskesmas'));
    }
}
