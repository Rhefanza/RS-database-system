<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Queue;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CitizenQueueController extends Controller
{
    public function index(Request $request): View
    {
        $queues = Queue::with('schedule.puskesmas', 'schedule.service')
            ->where('akun_id', $request->user()->akun_id)
            ->active()
            ->latest('tanggal_daftar')
            ->latest('nomor_antrean')
            ->get();

        return view('queues.mine', compact('queues'));
    }

    public function store(Request $request, Schedule $schedule): RedirectResponse
    {
        $validated = $request->validate(['tanggal_daftar' => ['required', 'date', 'after_or_equal:today']]);
        $date = Carbon::parse($validated['tanggal_daftar']);
        $schedule->load('puskesmas', 'service');
        abort_unless(
            $request->user()->nik && $request->user()->status_data === 'AKTIF'
            && $schedule->status === 'AKTIF'
            && $schedule->service?->status === 'AKTIF'
            && $schedule->puskesmas?->status === 'AKTIF',
            403
        );
        if ($this->dayName($date) !== $schedule->hari) {
            return back()->with('error', 'Tanggal tidak sesuai dengan hari jadwal.');
        }

        $result = DB::transaction(function () use ($request, $schedule, $date) {
            User::citizens()->whereKey($request->user()->akun_id)->lockForUpdate()->firstOrFail();
            $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            $existing = $locked->queues()->active()->where('akun_id', $request->user()->akun_id)->whereDate('tanggal_daftar', $date)->first();
            if ($existing) {
                return ['status' => 'existing', 'queue' => $existing];
            }

            $conflict = Queue::overlappingActiveFor($request->user()->akun_id, $locked, $date->toDateString());
            if ($conflict) {
                return ['status' => 'conflict', 'queue' => $conflict];
            }

            $activeCount = $locked->queues()->active()->whereDate('tanggal_daftar', $date)->count();
            if ($activeCount >= $locked->kapasitas) {
                return ['status' => 'full'];
            }

            return [
                'status' => 'created',
                'queue' => $locked->queues()->create([
                    'akun_id' => $request->user()->akun_id,
                    'nomor_antrean' => ((int) $locked->queues()->whereDate('tanggal_daftar', $date)->max('nomor_antrean')) + 1,
                    'tanggal_daftar' => $date,
                    'status_antrean' => 'WAITING',
                ]),
            ];
        });

        if ($result['status'] === 'existing') {
            return redirect()->route('my-queues.index')->with('error', 'Anda sudah memiliki antrean untuk jadwal dan tanggal ini.');
        }

        if ($result['status'] === 'conflict') {
            $conflict = $result['queue'];

            return back()->with('error', 'Jadwal bertabrakan dengan antrean aktif Anda di '
                .$conflict->schedule->puskesmas->nama_puskesmas.' · '
                .$conflict->schedule->service->nama_layanan.' ('
                .substr($conflict->schedule->jam_buka, 0, 5).'–'.substr($conflict->schedule->jam_tutup, 0, 5).').');
        }

        if ($result['status'] === 'full') {
            return back()->with('error', 'Kapasitas antrean sudah penuh.');
        }

        $queue = $result['queue'];

        return redirect()->route('my-queues.index')->with('success', 'Antrean nomor '.$queue->nomor_antrean.' berhasil diambil.');
    }

    public function destroy(Request $request, Queue $queue): RedirectResponse
    {
        abort_unless((int) $queue->akun_id === (int) $request->user()->akun_id, 403);
        $deleted = Queue::whereKey($queue->getKey())
            ->where('akun_id', $request->user()->akun_id)
            ->whereIn('status_antrean', ['WAITING', 'CANCELLED'])
            ->delete();
        if (! $deleted) {
            return back()->with('error', 'Hanya antrean menunggu atau dibatalkan yang dapat dihapus.');
        }

        return back()->with('success', 'Data antrean berhasil dihapus.');
    }

    private function dayName(Carbon $date): string
    {
        return ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][$date->format('l')];
    }
}
