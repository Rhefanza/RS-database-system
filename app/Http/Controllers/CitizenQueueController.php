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
        abort_unless($this->dayName($date) === $schedule->hari, 422, 'Tanggal tidak sesuai dengan hari jadwal.');

        $queue = DB::transaction(function () use ($request, $schedule, $date) {
            $locked = Schedule::whereKey($schedule->getKey())->lockForUpdate()->firstOrFail();
            $activeCount = $locked->queues()->whereDate('tanggal_daftar', $date)->where('status_antrean', '!=', 'CANCELLED')->count();
            abort_if($activeCount >= $locked->kapasitas, 409, 'Kapasitas antrean sudah penuh.');

            $existing = $locked->queues()->where('nik', $request->user()->nik)->whereDate('tanggal_daftar', $date)->first();
            abort_if($existing, 409, 'Anda sudah memiliki antrean untuk jadwal ini.');

            return $locked->queues()->create([
                'nik' => $request->user()->nik,
                'nomor_antrean' => ((int) $locked->queues()->whereDate('tanggal_daftar', $date)->max('nomor_antrean')) + 1,
                'tanggal_daftar' => $date,
                'status_antrean' => 'WAITING',
            ]);
        });

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
