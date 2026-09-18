<?php

namespace App\Http\Controllers;

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
        $queues = Queue::with('schedule.puskesmasService.puskesmas', 'schedule.puskesmasService.service')
            ->where('nik', $request->user()->nik)->latest('tanggal_daftar')->latest('nomor_antrean')->get();

        return view('queues.mine', compact('queues'));
    }

    public function store(Request $request, Schedule $schedule): RedirectResponse
    {
        $validated = $request->validate(['tanggal_daftar' => ['required', 'date', 'after_or_equal:today']]);
        $date = Carbon::parse($validated['tanggal_daftar']);
        abort_unless($request->user()->nik && $schedule->status === 'AKTIF', 403);
        if ($this->dayName($date) !== $schedule->hari) {
            return back()->with('error', 'Tanggal tidak sesuai dengan hari jadwal.');
        }

        $result = DB::transaction(function () use ($request, $schedule, $date) {
            $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();

            $existing = $locked->queues()->where('nik', $request->user()->nik)->whereDate('tanggal_daftar', $date)->first();
            if ($existing) {
                return ['status' => 'existing', 'queue' => $existing];
            }

            $activeCount = $locked->queues()->whereDate('tanggal_daftar', $date)->where('status_antrean', '!=', 'CANCELLED')->count();
            if ($activeCount >= $locked->kapasitas) {
                return ['status' => 'full'];
            }

            return [
                'status' => 'created',
                'queue' => $locked->queues()->create([
                    'nik' => $request->user()->nik,
                    'nomor_antrean' => ((int) $locked->queues()->whereDate('tanggal_daftar', $date)->max('nomor_antrean')) + 1,
                    'tanggal_daftar' => $date,
                    'status_antrean' => 'WAITING',
                ]),
            ];
        });

        if ($result['status'] === 'existing') {
            return redirect()->route('my-queues.index')->with('error', 'Anda sudah memiliki antrean untuk jadwal dan tanggal ini.');
        }

        if ($result['status'] === 'full') {
            return back()->with('error', 'Kapasitas antrean sudah penuh.');
        }

        $queue = $result['queue'];

        return redirect()->route('my-queues.index')->with('success', 'Antrean nomor '.$queue->nomor_antrean.' berhasil diambil.');
    }

    public function cancel(Request $request, Queue $queue): RedirectResponse
    {
        abort_unless($queue->nik === $request->user()->nik, 403);
        abort_unless($queue->status_antrean === 'WAITING', 409, 'Hanya antrean menunggu yang dapat dibatalkan.');
        $queue->update(['status_antrean' => 'CANCELLED']);

        return back()->with('success', 'Antrean berhasil dibatalkan.');
    }

    public function destroy(Request $request, Queue $queue): RedirectResponse
    {
        abort_unless($queue->nik === $request->user()->nik, 403);
        abort_unless($queue->status_antrean === 'CANCELLED', 409, 'Batalkan antrean sebelum menghapusnya.');
        $queue->delete();

        return back()->with('success', 'Data antrean berhasil dihapus.');
    }

    private function dayName(Carbon $date): string
    {
        return ['Sunday' => 'MINGGU', 'Monday' => 'SENIN', 'Tuesday' => 'SELASA', 'Wednesday' => 'RABU', 'Thursday' => 'KAMIS', 'Friday' => 'JUMAT', 'Saturday' => 'SABTU'][$date->format('l')];
    }
}
